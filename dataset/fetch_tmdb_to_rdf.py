"""
fetch_tmdb_to_rdf.py
=====================
Generate dataset RDF (film.ttl) untuk CineGraph dari TMDB API, dengan
mapping ke DBpedia via Wikidata QID sebagai jembatan.

Versi CineGraph mengikuti pola Turtle yang ditentukan:
  - namespace dan URI resource memakai http://pilem.org/cinegraph/...
  - film diidentifikasi memakai IMDb ID (mis. movie:tt0468569) jika tersedia;
    jika tidak, memakai ID fallback movie:tmdb-{id}.
  - genre, sutradara, aktor, dan negara merupakan resource tersendiri, bukan literal.
  - metadata film dikelompokkan memakai schema.org dan properti tambahan film:.
  - rating dari TMDB disimpan sebagai film:tmdbRating, bukan film:imdbRating,
    karena nilai dari endpoint TMDB bukan rating IMDb.
  - sertifikasi rating usia AS (film:rated) diisi hanya jika data tersedia dari TMDB.

Proses ini OFFLINE/BATCH, bukan runtime. Jalankan ulang tiap refresh dataset,
lalu load ulang film.ttl ke Fuseki.

Environment variable (pilih salah satu):
  TMDB_API_KEY       -> API key v3 (32 karakter hex)
  TMDB_ACCESS_TOKEN  -> Bearer token v4

Instalasi:
  pip install -r requirements.txt --break-system-packages

Contoh pemakaian:
  export TMDB_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
  python3 fetch_tmdb_to_rdf.py --count 150 --output film.ttl
"""

import os
import re
import time
import logging
import argparse
from pathlib import Path
from urllib.parse import quote

import requests
from rdflib import Graph, Namespace, URIRef, Literal, RDF, RDFS, OWL, XSD

# ---------------------------------------------------------------------------
# Konfigurasi & namespace (SINKRON dengan FilmController.php)
# ---------------------------------------------------------------------------

TMDB_API_BASE = "https://api.themoviedb.org/3"
TMDB_IMAGE_BASE = "https://image.tmdb.org/t/p/w500"
TMDB_PROFILE_IMAGE_BASE = "https://image.tmdb.org/t/p/w185"  # foto orang, ukuran lebih kecil dari poster
WIKIDATA_API_BASE = "https://www.wikidata.org/w/api.php"

FILM_NS = Namespace("http://pilem.org/cinegraph/ontology#")
SCHEMA = Namespace("https://schema.org/")
MOVIE_NS = Namespace("http://pilem.org/cinegraph/resource/movie/")
PERSON_NS = Namespace("http://pilem.org/cinegraph/resource/person/")
GENRE_NS = Namespace("http://pilem.org/cinegraph/resource/genre/")
COUNTRY_NS = Namespace("http://pilem.org/cinegraph/resource/country/")

REQUEST_TIMEOUT = 10
RATE_LIMIT_DELAY = 0.3
MAX_CAST = 10

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("fetch_tmdb_to_rdf")


def slugify(name: str) -> str:
    """Ubah nama jadi slug URL-safe, dipakai untuk URI person/country/genre.
    Deterministik: nama yang sama selalu hasilkan URI yang sama, ini PENTING
    supaya fitur 'Film Sejenis' (match resource yang sama) jalan benar."""
    slug = name.strip().lower()
    slug = re.sub(r"[^a-z0-9]+", "-", slug)
    return slug.strip("-") or "unknown"


# ---------------------------------------------------------------------------
# TMDB client kecil, dengan retry sederhana untuk rate limit (429)
# ---------------------------------------------------------------------------

class TMDBClient:
    def __init__(self, api_key=None, access_token=None):
        if not api_key and not access_token:
            raise ValueError(
                "Wajib set env var TMDB_API_KEY atau TMDB_ACCESS_TOKEN sebelum jalankan script ini."
            )
        self.api_key = api_key
        self.session = requests.Session()
        if access_token:
            self.session.headers.update({"Authorization": f"Bearer {access_token}"})

    def get(self, path, params=None):
        params = dict(params or {})
        if self.api_key and "Authorization" not in self.session.headers:
            params["api_key"] = self.api_key
        url = f"{TMDB_API_BASE}{path}"
        for attempt in range(3):
            resp = self.session.get(url, params=params, timeout=REQUEST_TIMEOUT)
            if resp.status_code == 429:
                wait = int(resp.headers.get("Retry-After", 2))
                logger.warning(f"Kena rate limit TMDB, tunggu {wait} detik...")
                time.sleep(wait)
                continue
            resp.raise_for_status()
            time.sleep(RATE_LIMIT_DELAY)
            return resp.json()
        raise RuntimeError(f"Gagal fetch {url} setelah 3 percobaan (rate limit terus).")

    def get_popular_movie_ids(self, count):
        ids = []
        page = 1
        while len(ids) < count:
            data = self.get("/movie/popular", {"language": "en-US", "page": page})
            results = data.get("results", [])
            if not results:
                break
            ids.extend([m["id"] for m in results])
            page += 1
            if page > data.get("total_pages", 1):
                break
        return ids[:count]

    def search_company_id(self, name):
        """Cari company_id TMDB dari nama studio (mis. 'Pixar', 'Marvel
        Studios'). Dicari via API, BUKAN di-hardcode, supaya tidak salah ID."""
        data = self.get("/search/company", {"query": name})
        results = data.get("results", [])
        if not results:
            return None
        return results[0]["id"], results[0].get("name", name)

    def get_movie_ids_by_companies(self, company_ids, count):
        """Ambil movie_id via /discover/movie, filter with_companies.
        Pemisah '|' di TMDB discover berarti OR (Disney ATAU Marvel ATAU
        Pixar), beda dengan ',' yang berarti AND -- jangan sampai ketuker."""
        companies_param = "|".join(str(c) for c in company_ids)
        ids = []
        page = 1
        while len(ids) < count:
            data = self.get("/discover/movie", {
                "with_companies": companies_param,
                "sort_by": "popularity.desc",
                "page": page,
                "language": "en-US",
            })
            results = data.get("results", [])
            if not results:
                break
            ids.extend([m["id"] for m in results])
            page += 1
            if page > data.get("total_pages", 1):
                break
        return ids[:count]

    def get_movie_full(self, movie_id):
        return self.get(f"/movie/{movie_id}", {
            "language": "en-US",
            "append_to_response": "credits,external_ids,release_dates",
        })


# ---------------------------------------------------------------------------
# Mapping ke DBpedia via Wikidata QID
# ---------------------------------------------------------------------------

def get_dbpedia_uri_from_wikidata(wikidata_id):
    if not wikidata_id:
        return None
    try:
        headers = {"User-Agent": "CineGraph-Tubes-WebSemantik/1.0 (kontak: kelompok@example.ac.id)"}
        resp = requests.get(WIKIDATA_API_BASE, params={
            "action": "wbgetentities",
            "ids": wikidata_id,
            "props": "sitelinks",
            "sitefilter": "enwiki",
            "format": "json",
        }, headers=headers, timeout=REQUEST_TIMEOUT)
        resp.raise_for_status()
        data = resp.json()
        entity = data.get("entities", {}).get(wikidata_id, {})
        enwiki = entity.get("sitelinks", {}).get("enwiki")
        if not enwiki:
            return None
        title = enwiki["title"].replace(" ", "_")
        return f"http://dbpedia.org/resource/{quote(title, safe='_(),')}"
    except requests.RequestException as e:
        logger.warning(f"Gagal query Wikidata untuk {wikidata_id}: {e}")
        return None


# ---------------------------------------------------------------------------
# Bangun RDF graph
# ---------------------------------------------------------------------------

def build_graph(movies):
    """Bangun graph RDF dengan struktur konsisten seperti contoh Turtle CineGraph."""
    g = Graph()
    g.bind("schema", SCHEMA, replace=True)
    g.bind("film", FILM_NS, replace=True)
    g.bind("movie", MOVIE_NS, replace=True)
    g.bind("person", PERSON_NS, replace=True)
    g.bind("genre", GENRE_NS, replace=True)
    g.bind("country", COUNTRY_NS, replace=True)
    g.bind("rdfs", RDFS, replace=True)
    g.bind("xsd", XSD, replace=True)
    g.bind("owl", OWL, replace=True)

    genre_seen, person_seen, country_seen = {}, {}, {}
    skipped_no_imdb, skipped_no_wikidata, skipped_no_dbpedia = [], [], []

    def get_genre_uri(name):
        slug = slugify(name)
        if slug not in genre_seen:
            uri = GENRE_NS[slug]
            g.add((uri, RDF.type, FILM_NS.Genre))
            g.add((uri, RDFS.label, Literal(name)))
            genre_seen[slug] = uri
        return genre_seen[slug]

    def get_person_uri(name, profile_path=None):
        slug = slugify(name)
        if slug not in person_seen:
            uri = PERSON_NS[slug]
            g.add((uri, RDF.type, SCHEMA.Person))
            g.add((uri, SCHEMA.name, Literal(name)))
            if profile_path:
                g.add((uri, SCHEMA.image, Literal(f"{TMDB_PROFILE_IMAGE_BASE}{profile_path}")))
            person_seen[slug] = uri
        elif profile_path:
            # Jika orang yang sama muncul lagi dan gambar belum tersedia, lengkapi resource.
            uri = person_seen[slug]
            if (uri, SCHEMA.image, None) not in g:
                g.add((uri, SCHEMA.image, Literal(f"{TMDB_PROFILE_IMAGE_BASE}{profile_path}")))
        return person_seen[slug]

    def get_country_uri(name):
        slug = slugify(name)
        if slug not in country_seen:
            uri = COUNTRY_NS[slug]
            g.add((uri, RDF.type, SCHEMA.Country))
            g.add((uri, SCHEMA.name, Literal(name)))
            country_seen[slug] = uri
        return country_seen[slug]

    def get_us_certification(movie):
        """Ambil sertifikasi usia AS dari data release_dates TMDB, bila tersedia."""
        release_data = movie.get("release_dates", {})
        for region in release_data.get("results", []):
            if region.get("iso_3166_1") != "US":
                continue
            for release in region.get("release_dates", []):
                certification = (release.get("certification") or "").strip()
                if certification:
                    return certification
        return None

    for movie in movies:
        tmdb_id = movie["id"]
        external_ids = movie.get("external_ids", {}) or {}
        imdb_id = (external_ids.get("imdb_id") or "").strip()

        # Mengutamakan pola URI pada contoh: movie:tt0468569.
        # Fallback tetap stabil untuk judul yang tidak memiliki IMDb ID.
        if imdb_id:
            movie_uri = MOVIE_NS[imdb_id]
        else:
            movie_uri = MOVIE_NS[f"tmdb-{tmdb_id}"]
            skipped_no_imdb.append(tmdb_id)

        g.add((movie_uri, RDF.type, SCHEMA.Movie))

        title = (movie.get("title") or movie.get("original_title") or "").strip()
        if title:
            g.add((movie_uri, SCHEMA.name, Literal(title)))

        description = (movie.get("overview") or "").strip()
        if description:
            g.add((movie_uri, SCHEMA.description, Literal(description, lang="en")))

        # ID TMDB dipertahankan sebagai metadata tambahan untuk pelacakan sumber data.
        g.add((movie_uri, FILM_NS.tmdbId, Literal(tmdb_id, datatype=XSD.integer)))
        if imdb_id:
            g.add((movie_uri, FILM_NS.imdbId, Literal(imdb_id)))

        release_date = (movie.get("release_date") or "").strip()
        if release_date:
            try:
                # Hindari literal bertipe xsd:date jika sumber mengirim tanggal tidak lengkap.
                from datetime import date
                date.fromisoformat(release_date)
                g.add((movie_uri, SCHEMA.datePublished, Literal(release_date, datatype=XSD.date)))
            except ValueError:
                logger.warning("Tanggal rilis tidak valid untuk TMDB ID %s: %r", tmdb_id, release_date)

        runtime = movie.get("runtime")
        if runtime is not None:
            try:
                runtime_minutes = int(runtime)
                if runtime_minutes > 0:
                    g.add((movie_uri, SCHEMA.duration, Literal(f"PT{runtime_minutes}M", datatype=XSD.duration, normalize=False)))
                    g.add((movie_uri, FILM_NS.runtimeMinutes, Literal(runtime_minutes, datatype=XSD.integer)))
            except (TypeError, ValueError):
                logger.warning("Runtime tidak valid untuk TMDB ID %s: %r", tmdb_id, runtime)

        poster_path = movie.get("poster_path")
        if poster_path:
            g.add((movie_uri, SCHEMA.image, Literal(f"{TMDB_IMAGE_BASE}{poster_path}")))

        # Revenue TMDB diperlakukan sebagai box office USD; data 0 tidak dianggap diketahui.
        revenue = movie.get("revenue")
        if revenue:
            g.add((movie_uri, FILM_NS.boxOfficeUSD, Literal(int(revenue), datatype=XSD.integer)))

        # vote_average adalah nilai TMDB, jadi tidak dilabeli sebagai IMDb rating.
        tmdb_rating = movie.get("vote_average")
        if tmdb_rating is not None:
            try:
                g.add((movie_uri, FILM_NS.tmdbRating, Literal(float(tmdb_rating), datatype=XSD.decimal)))
            except (TypeError, ValueError):
                logger.warning("Rating TMDB tidak valid untuk TMDB ID %s: %r", tmdb_id, tmdb_rating)

        certification = get_us_certification(movie)
        if certification:
            g.add((movie_uri, FILM_NS.rated, Literal(certification)))

        for genre_data in movie.get("genres", []):
            genre_name = (genre_data.get("name") or "").strip()
            if genre_name:
                g.add((movie_uri, SCHEMA.genre, get_genre_uri(genre_name)))

        for country_data in movie.get("production_countries", []):
            country_name = (country_data.get("name") or "").strip()
            if country_name:
                g.add((movie_uri, SCHEMA.countryOfOrigin, get_country_uri(country_name)))

        credits = movie.get("credits", {}) or {}
        for crew in credits.get("crew", []):
            if crew.get("job") == "Director" and crew.get("name"):
                g.add((movie_uri, SCHEMA.director,
                       get_person_uri(crew["name"], crew.get("profile_path"))))

        for cast in (credits.get("cast", []) or [])[:MAX_CAST]:
            if cast.get("name"):
                g.add((movie_uri, SCHEMA.actor,
                       get_person_uri(cast["name"], cast.get("profile_path"))))

        # Hubungkan resource film dengan DBpedia jika TMDB menyediakan Wikidata QID.
        wikidata_id = external_ids.get("wikidata_id")
        if not wikidata_id:
            skipped_no_wikidata.append(tmdb_id)
        else:
            g.add((movie_uri, FILM_NS.wikidataId, Literal(wikidata_id)))
            dbpedia_uri = get_dbpedia_uri_from_wikidata(wikidata_id)
            if dbpedia_uri:
                g.add((movie_uri, OWL.sameAs, URIRef(dbpedia_uri)))
            else:
                skipped_no_dbpedia.append(tmdb_id)

    if skipped_no_imdb:
        logger.warning(
            "%s film tidak memiliki IMDb ID; URI fallback movie:tmdb-{id} digunakan: %s",
            len(skipped_no_imdb), skipped_no_imdb,
        )
    if skipped_no_wikidata:
        logger.warning("%s film tanpa wikidata_id: %s", len(skipped_no_wikidata), skipped_no_wikidata)
    if skipped_no_dbpedia:
        logger.warning(
            "%s film tanpa sitelink enwiki (gagal mapping DBpedia): %s",
            len(skipped_no_dbpedia), skipped_no_dbpedia,
        )

    return g


def serialize_turtle_ordered(graph: Graph, output_path: str) -> None:
    """Serialize Turtle in a readable, deterministic order.

    For each movie, rdf:type is written first and schema:name immediately after
    it, so the movie title appears near the top of every movie block. RDF itself
    is unordered; this only controls the human-readable Turtle presentation.
    """
    prefixes = [
        ("schema", SCHEMA),
        ("film", FILM_NS),
        ("movie", MOVIE_NS),
        ("person", PERSON_NS),
        ("genre", GENRE_NS),
        ("country", COUNTRY_NS),
        ("rdfs", RDFS),
        ("xsd", XSD),
        ("owl", OWL),
    ]

    # Bind these prefixes explicitly so term.n3() emits the expected CURIEs.
    for prefix, namespace in prefixes:
        graph.bind(prefix, namespace, replace=True)
    ns_manager = graph.namespace_manager

    property_order = {
        RDF.type: 0,
        SCHEMA.name: 1,
        RDFS.label: 2,
        SCHEMA.description: 3,
        SCHEMA.datePublished: 4,
        SCHEMA.duration: 5,
        FILM_NS.runtimeMinutes: 6,
        SCHEMA.genre: 7,
        SCHEMA.director: 8,
        SCHEMA.actor: 9,
        SCHEMA.countryOfOrigin: 10,
        FILM_NS.tmdbRating: 11,
        FILM_NS.rated: 12,
        FILM_NS.boxOfficeUSD: 13,
        FILM_NS.tmdbId: 14,
        FILM_NS.imdbId: 15,
        FILM_NS.wikidataId: 16,
        SCHEMA.image: 17,
        OWL.sameAs: 18,
    }

    def object_sort_key(term):
        return (str(term), term.n3(namespace_manager=ns_manager))

    def subject_title(subject):
        values = list(graph.objects(subject, SCHEMA.name))
        if values:
            return str(values[0]).casefold()
        labels = list(graph.objects(subject, RDFS.label))
        return str(labels[0]).casefold() if labels else str(subject).casefold()

    def subject_sort_key(subject):
        uri = str(subject)
        if uri.startswith(str(MOVIE_NS)):
            group = 0  # All movie blocks first, sorted by title.
        elif uri.startswith(str(GENRE_NS)):
            group = 1
        elif uri.startswith(str(PERSON_NS)):
            group = 2
        elif uri.startswith(str(COUNTRY_NS)):
            group = 3
        else:
            group = 4
        return (group, subject_title(subject), uri)

    lines = [f"@prefix {prefix}: <{namespace}> ." for prefix, namespace in prefixes]
    lines.append("")

    for subject in sorted(set(graph.subjects()), key=subject_sort_key):
        predicate_objects = {}
        for predicate, obj in graph.predicate_objects(subject):
            predicate_objects.setdefault(predicate, []).append(obj)

        predicates = sorted(
            predicate_objects,
            key=lambda pred: (property_order.get(pred, 100), str(pred)),
        )
        if not predicates:
            continue

        subject_text = subject.n3(namespace_manager=ns_manager)
        for index, predicate in enumerate(predicates):
            if predicate == RDF.type and len(predicate_objects[predicate]) == 1:
                predicate_text = "a"
            else:
                predicate_text = predicate.n3(namespace_manager=ns_manager)

            objects = sorted(predicate_objects[predicate], key=object_sort_key)
            object_text = ",\n        ".join(
                item.n3(namespace_manager=ns_manager) for item in objects
            )
            if index == 0:
                line = f"{subject_text} {predicate_text} {object_text}"
            else:
                line = f"    {predicate_text} {object_text}"
            line += " ;" if index < len(predicates) - 1 else " ."
            lines.append(line)
        lines.append("")

    Path(output_path).write_text("\n".join(lines).rstrip() + "\n", encoding="utf-8")


def main():
    parser = argparse.ArgumentParser(description="Generate film.ttl dari TMDB untuk CineGraph")
    parser.add_argument("--count", type=int, default=150, help="Jumlah film maksimum yang diambil (default 150)")
    parser.add_argument("--output", default="film.ttl", help="Path file output Turtle")
    parser.add_argument("--movie-ids", help="Optional: path .txt berisi daftar TMDB movie_id (satu per baris) -- override --studios")
    parser.add_argument(
        "--studios",
        default="Walt Disney Pictures,Marvel Studios,Pixar",
        help=(
            "Nama studio TMDB, pisah koma, dipakai untuk filter /discover/movie "
            "(OR antar studio). Default: Disney, Marvel, Pixar (keputusan tim, "
            "resolve PRD Open Question #3). Kosongkan ('') untuk pakai daftar "
            "film populer biasa (tanpa filter studio)."
        ),
    )
    args = parser.parse_args()

    api_key = os.environ.get("TMDB_API_KEY")
    access_token = os.environ.get("TMDB_ACCESS_TOKEN")
    client = TMDBClient(api_key=api_key, access_token=access_token)

    if args.movie_ids:
        with open(args.movie_ids) as f:
            movie_ids = [int(line.strip()) for line in f if line.strip()]
        logger.info(f"Pakai {len(movie_ids)} movie_id dari {args.movie_ids}")
    elif args.studios.strip():
        studio_names = [s.strip() for s in args.studios.split(",") if s.strip()]
        company_ids = []
        for name in studio_names:
            found = client.search_company_id(name)
            if found:
                cid, resolved_name = found
                logger.info(f"Studio '{name}' -> TMDB company_id={cid} ('{resolved_name}')")
                company_ids.append(cid)
            else:
                logger.warning(f"Studio '{name}' TIDAK ditemukan di TMDB, dilewati.")

        if not company_ids:
            raise RuntimeError(
                "Tidak ada satupun nama studio di --studios yang berhasil "
                "ditemukan di TMDB. Cek ejaan nama studio-nya."
            )

        logger.info(f"Ambil sampai {args.count} film dari studio: {studio_names}...")
        movie_ids = client.get_movie_ids_by_companies(company_ids, args.count)
    else:
        logger.info(f"Ambil {args.count} film populer dari TMDB (tanpa filter studio)...")
        movie_ids = client.get_popular_movie_ids(args.count)

    if not movie_ids:
        raise RuntimeError("Tidak ada movie_id yang berhasil diambil -- cek koneksi/API key/nama studio.")

    logger.info(f"Total {len(movie_ids)} film akan diproses.")

    movies = []
    for i, movie_id in enumerate(movie_ids, start=1):
        logger.info(f"[{i}/{len(movie_ids)}] Fetch movie_id={movie_id}")
        try:
            movies.append(client.get_movie_full(movie_id))
        except Exception as e:
            logger.error(f"Gagal fetch movie_id={movie_id}: {e}")

    logger.info("Bangun RDF graph...")
    graph = build_graph(movies)

    serialize_turtle_ordered(graph, args.output)
    logger.info(f"Selesai. {len(graph)} triples ditulis ke {args.output}")


if __name__ == "__main__":
    main()
