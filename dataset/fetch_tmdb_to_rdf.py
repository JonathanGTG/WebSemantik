"""
fetch_tmdb_to_rdf.py
=====================
Generate dataset RDF (film.ttl) untuk CineGraph dari TMDB API, dengan
mapping ke DBpedia via Wikidata QID sebagai jembatan.

v2 - disinkronkan dengan FilmController.php yang sudah ada di repo:
  - namespace film: = http://example.org/ontology/ (bukan cinegraph.example.org)
  - schema: = https://schema.org/ (https, bukan http)
  - base URI film = http://example.org/film/{tmdbId} (bukan .../resource/movie/)
  - genre/director/actor/country DIBUAT SEBAGAI RESOURCE (bukan literal),
    dihubungkan lewat properti schema.org biasa (schema:genre, schema:director,
    schema:actor, schema:countryOfOrigin) - supaya fitur "Film Sejenis" di
    controller bisa graph-traversal lewat resource yang sama persis.
  - tambah schema:image (poster URL, dipakai controller & fitur Film Sejenis)

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

FILM_NS = Namespace("http://example.org/ontology/")     # film:tmdbId, film:boxOfficeUSD, dst
SCHEMA = Namespace("https://schema.org/")                # https, sesuai controller
MOVIE_NS = Namespace("http://example.org/film/")         # base URI film -> cocok dgn buildFilmUri()
PERSON_NS = Namespace("http://example.org/person/")      # resource sutradara & aktor
COUNTRY_NS = Namespace("http://example.org/country/")    # resource negara
GENRE_NS = Namespace("http://example.org/genre/")        # resource genre

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
            "append_to_response": "credits,external_ids",
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
    g = Graph()
    g.bind("film", FILM_NS)
    g.bind("schema", SCHEMA)
    g.bind("owl", OWL)

    genre_seen, person_seen, country_seen = {}, {}, {}
    skipped_no_wikidata, skipped_no_dbpedia = [], []

    def get_genre_uri(name):
        slug = slugify(name)
        if slug not in genre_seen:
            uri = GENRE_NS[slug]
            g.add((uri, RDF.type, FILM_NS.Genre))
            g.add((uri, RDFS.label, Literal(name, lang="en")))
            genre_seen[slug] = uri
        return genre_seen[slug]

    def get_person_uri(name, profile_path=None):
        slug = slugify(name)
        if slug not in person_seen:
            uri = PERSON_NS[slug]
            g.add((uri, RDF.type, SCHEMA.Person))
            g.add((uri, SCHEMA.name, Literal(name, lang="en")))
            if profile_path:
                g.add((uri, SCHEMA.image, Literal(f"{TMDB_PROFILE_IMAGE_BASE}{profile_path}")))
            person_seen[slug] = uri
        return person_seen[slug]

    def get_country_uri(name):
        slug = slugify(name)
        if slug not in country_seen:
            uri = COUNTRY_NS[slug]
            g.add((uri, RDF.type, SCHEMA.Country))
            g.add((uri, SCHEMA.name, Literal(name, lang="en")))
            country_seen[slug] = uri
        return country_seen[slug]

    for movie in movies:
        tmdb_id = movie["id"]
        movie_uri = MOVIE_NS[str(tmdb_id)]

        g.add((movie_uri, RDF.type, SCHEMA.Movie))
        g.add((movie_uri, SCHEMA.name, Literal(movie.get("title", ""), lang="en")))
        g.add((movie_uri, FILM_NS.tmdbId, Literal(tmdb_id, datatype=XSD.integer)))

        if movie.get("release_date"):
            g.add((movie_uri, SCHEMA.datePublished, Literal(movie["release_date"], datatype=XSD.date)))

        if movie.get("runtime"):
            g.add((movie_uri, SCHEMA.duration, Literal(f"PT{movie['runtime']}M", datatype=XSD.duration)))

        if movie.get("poster_path"):
            g.add((movie_uri, SCHEMA.image, Literal(f"{TMDB_IMAGE_BASE}{movie['poster_path']}")))

        # revenue == 0 di TMDB biasanya berarti data TIDAK DIKETAHUI, bukan Rp0.
        if movie.get("revenue"):
            g.add((movie_uri, FILM_NS.boxOfficeUSD, Literal(movie["revenue"], datatype=XSD.decimal)))

        for genre in movie.get("genres", []):
            g.add((movie_uri, SCHEMA.genre, get_genre_uri(genre["name"])))

        for country in movie.get("production_countries", []):
            if country.get("name"):
                g.add((movie_uri, SCHEMA.countryOfOrigin, get_country_uri(country["name"])))

        credits = movie.get("credits", {})
        for crew in credits.get("crew", []):
            if crew.get("job") == "Director" and crew.get("name"):
                g.add((movie_uri, SCHEMA.director, get_person_uri(crew["name"], crew.get("profile_path"))))
        for cast in credits.get("cast", [])[:MAX_CAST]:
            if cast.get("name"):
                g.add((movie_uri, SCHEMA.actor, get_person_uri(cast["name"], cast.get("profile_path"))))

        # --- mapping ke DBpedia via Wikidata QID ---
        wikidata_id = movie.get("external_ids", {}).get("wikidata_id")
        if not wikidata_id:
            skipped_no_wikidata.append(tmdb_id)
        else:
            g.add((movie_uri, FILM_NS.wikidataId, Literal(wikidata_id)))
            dbpedia_uri = get_dbpedia_uri_from_wikidata(wikidata_id)
            if dbpedia_uri:
                g.add((movie_uri, OWL.sameAs, URIRef(dbpedia_uri)))
            else:
                skipped_no_dbpedia.append(tmdb_id)

    if skipped_no_wikidata:
        logger.warning(f"{len(skipped_no_wikidata)} film tanpa wikidata_id: {skipped_no_wikidata}")
    if skipped_no_dbpedia:
        logger.warning(f"{len(skipped_no_dbpedia)} film tanpa sitelink enwiki (gagal mapping DBpedia): {skipped_no_dbpedia}")

    return g


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

    graph.serialize(destination=args.output, format="turtle")
    logger.info(f"Selesai. {len(graph)} triples ditulis ke {args.output}")


if __name__ == "__main__":
    main()
