# Setup Dataset CineGraph (Ontology + Generator Script)

Bagian ini: `cinegraph-ontology.ttl` + `fetch_tmdb_to_rdf.py`. Sesuai PRD §8 & §6 (FR-8).

## 1. Install dependency
```bash
pip install -r requirements.txt --break-system-packages
```

## 2. Set API key TMDB (env var, JANGAN hardcode di kode)
```bash
export TMDB_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
Dapatkan di https://www.themoviedb.org/settings/api (gratis, daftar akun dulu).

## 3. Generate film.ttl
```bash
# default: film Disney + Marvel Studios + Pixar (keputusan tim), sampai 150 film
python3 fetch_tmdb_to_rdf.py --count 150 --output film.ttl

# studio lain / tambahan (pisah koma, OR antar studio):
python3 fetch_tmdb_to_rdf.py --studios "Walt Disney Pictures,Marvel Studios,Pixar,Lucasfilm" --output film.ttl

# tanpa filter studio sama sekali (balik ke daftar film populer umum):
python3 fetch_tmdb_to_rdf.py --studios "" --count 150 --output film.ttl

# atau kalau mau daftar movie_id manual:
# buat movie_ids.txt isi TMDB movie_id satu per baris, lalu:
python3 fetch_tmdb_to_rdf.py --movie-ids movie_ids.txt --output film.ttl
```
Nama studio di-resolve ke `company_id` TMDB secara otomatis lewat `/search/company` saat script jalan (bukan di-hardcode) — perhatikan log `Studio 'X' -> TMDB company_id=...` di awal run untuk pastikan studio yang dimaksud benar ketemu (bukan studio lain yang kebetulan nama mirip).
Perhatikan log warning di akhir run — itu daftar film yang **tidak** dapat mapping DBpedia (tidak ada `wikidata_id` di TMDB, atau tidak ada sitelink Wikipedia Inggris). Film itu tetap masuk dataset, cuma tidak akan tampil sinopsis federated-nya (sesuai edge case PRD §9).

## 4. Load ke dataset Fuseki (pakai dataset `film` yang SUDAH kalian buat, JANGAN bikin baru)
`FilmController.php` sudah hardcode default endpoint ke dataset bernama **`film`** (`config('services.fuseki.endpoint')` default `http://localhost:3030/film/sparql`). Kalau kalian generate dataset baru dengan nama lain (`/cinegraph`, dst), aplikasi Laravel tidak akan nemu — jadi JANGAN jalankan `--mem /cinegraph`, itu bikin dataset in-memory baru yang terpisah dari `film` yang sudah ada.

Kalau dataset `film` kalian dibuat sebagai **persistent (TDB2)** lewat UI Fuseki (bukan `--mem`), cukup jalankan Fuseki server tanpa flag pembuatan dataset — dataset yang sudah ada otomatis ke-load dari folder `run/configuration/` bawaan Fuseki:
```bash
./fuseki-server        # Linux/Mac
fuseki-server.bat      # Windows
```
Lalu upload data ke dataset `film` yang sudah ada — lewat UI (localhost:3030 → pilih dataset `film` → tab "upload data") atau via curl:
```bash
curl -X POST --data-binary @cinegraph-ontology.ttl -H "Content-Type: text/turtle" \
     http://localhost:3030/film/data
curl -X POST --data-binary @film.ttl -H "Content-Type: text/turtle" \
     http://localhost:3030/film/data
```
**Kalau ternyata dataset `film` kalian dibuat sebagai in-memory** (pakai `--mem` waktu bikin), datanya hilang tiap restart server — kalau itu masalahnya, kasih tau saya caranya kalian bikin dataset `film` kemarin, biar saya sesuaikan instruksinya.

## 5. Tes federated query manual (bukti untuk dosen, PRD FR-8)
Buka http://localhost:3030 → pilih dataset **`film`** → tab Query, jalankan query contoh dari PRD FR-2 (ganti resource URI sesuai `film:tmdbId` yang benar-benar ada di dataset kalian setelah generate):
```sparql
PREFIX schema: <https://schema.org/>
PREFIX film:   <http://example.org/ontology/>
PREFIX dbo:    <http://dbpedia.org/ontology/>
PREFIX owl:    <http://www.w3.org/2002/07/owl#>

SELECT ?title ?year ?abstract WHERE {
  ?movie schema:name ?title ;
         schema:datePublished ?year ;
         owl:sameAs ?dbpediaResource .
  FILTER(?movie = <http://example.org/film/GANTI_TMDB_ID>)
  SERVICE <https://dbpedia.org/sparql> {
    ?dbpediaResource dbo:abstract ?abstract .
    FILTER(lang(?abstract) = "en")
  }
}
```
Kalau ini jalan dan `?abstract` muncul dari server DBpedia (bukan hardcode), itu bukti federated SPARQL paling kuat untuk rubrik Aplikasi.

## Yang sudah diputuskan (tidak perlu ditanya ulang)
- Mapping ke DBpedia: **via Wikidata QID** (TMDB `external_ids.wikidata_id` → Wikidata sitelink enwiki → URI DBpedia).
- Namespace ontology **SUDAH FINAL**, diambil dari `FilmController.php` yang sudah ada di repo (bukan placeholder lagi): `film:` = `http://example.org/ontology/`, `schema:` = `https://schema.org/` (https!), base film = `http://example.org/film/{tmdbId}`. `cinegraph-ontology.ttl` dan `fetch_tmdb_to_rdf.py` sudah pakai namespace ini.
- Genre, sutradara, aktor, dan negara dimodelkan sebagai **resource** (bukan literal teks) — supaya fitur "Film Sejenis" di `FilmController.php` bisa mencocokkan lewat resource URI yang sama persis.
- **Cakupan dataset MVP (Open Question #3): film Disney, Marvel Studios, dan Pixar** — di-resolve otomatis ke `company_id` TMDB lewat `/search/company`, lalu diambil via `/discover/movie` (OR antar studio, urut popularitas). Default `--count 150`.

## Yang masih perlu diputuskan tim (lihat PRD §10)
- Bahasa sinopsis DBpedia — script/query di atas ambil `@en`, belum handle fallback `@id` (Open Question #4).
- Kalau total film Disney+Marvel+Pixar di TMDB kurang dari `--count` yang diminta, script otomatis berhenti begitu hasil `/discover/movie` habis (tidak error, cuma dataset-nya lebih kecil dari target) — cek log jumlah akhir film yang diproses.
