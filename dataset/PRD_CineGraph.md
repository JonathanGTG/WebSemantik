# PRD — CineGraph
### Federated Semantic Movie Search Engine (Tugas Besar Web Semantik)

**Versi:** 2.0 (disinkronkan dengan kode `FilmController.php` yang sudah ada di repo) · **Tanggal:** 17 September 2026 · **Target submission:** KOM C, 20–27 November 2026

**Keputusan yang mengunci scope dokumen ini** (hasil klarifikasi + sinkronisasi kode, tidak berubah tanpa alasan kuat):
| Parameter | Keputusan |
|---|---|
| Audiens PRD | Fokus penilaian dosen / demo akademik — bukan produk publik |
| Prioritas fitur bonus | 1) Chatbot → 2) Trailer + OST → 3) Info streaming → 4) Box office IDR |
| Penyimpanan data film | RDF/Fuseki satu-satunya sumber utama. **Tidak ada tabel film di MySQL.** |
| Chatbot | LLM API OpenAI (`gpt-4o-mini`), boleh menjawab pengetahuan umum di luar dataset sendiri |
| Definisi selesai | Jalan stabil di localhost saat presentasi + source code. Tidak wajib deploy publik |
| Kelas / deadline | KOM C — dikumpulkan & presentasi 20–27 November 2026, sertakan hardcopy paper |
| Mapping ke DBpedia | Via Wikidata QID (TMDB `external_ids.wikidata_id` → sitelink enwiki → URI DBpedia) |
| Namespace ontology | `film:` = `http://example.org/ontology/`, `schema:` = `https://schema.org/`, base film = `http://example.org/film/{tmdbId}` (**diambil dari `FilmController.php` yang sudah ada**, bukan lagi placeholder) |
| OST | **iTunes Search API** (gratis, tanpa API key/OAuth) — bukan Spotify, lihat FR-4 kenapa |

---

## 1. Problem Statement

**Yang dirugikan dan kenapa:**

1. **Dosen/asisten penilai** — rubrik "Aplikasi" (50% nilai) menuntut bukti nyata *federated SPARQL query* antara dataset buatan sendiri dan endpoint eksternal (DBpedia/Wikidata), bukan sekadar wrapper ke satu API komersial. Tanpa desain yang eksplisit memisahkan lapisan RDF/SPARQL dari lapisan API biasa, sulit dibedakan mana yang benar-benar "semantic web" dan mana yang cuma REST call — dan itu langsung memotong nilai.
2. **Pengguna hipotetis (persona demo)** — pecinta film yang riset satu judul harus buka banyak sumber terpisah: IMDB/TMDB untuk metadata dasar, Wikipedia untuk sinopsis lengkap, YouTube untuk trailer, layanan musik untuk OST, JustWatch/platform streaming untuk ketersediaan tonton, dan situs kurs untuk mengonversi box office USD ke Rupiah. Tidak ada satu titik yang menggabungkan semuanya secara live.
3. **Akar masalah teknis**: data film tersebar antara *proprietary API* (TMDB) dan *Linked Open Data* (DBpedia/Wikidata) tanpa jembatan semantik standar. Developer yang ingin menggabungkan harus melakukan integrasi manual satu-satu, hasilnya tidak reusable dan tidak bisa di-query sebagai satu graph.

CineGraph menjawab ini dengan satu resource film yang: metadata dasarnya berasal dari dataset RDF sendiri (dengan genre/sutradara/aktor/negara dimodelkan sebagai **resource**, bukan literal teks, supaya bisa di-graph-traversal), sinopsis lengkapnya ditarik *real-time* dari DBpedia lewat federated SPARQL, dan lapisan pengayaan (trailer, OST, streaming, kurs, review, chatbot) ditarik dari API eksternal saat request.

---

## 2. Target User

Karena PRD ini eksplisit **fokus ke penilaian dosen/demo**, target user dibagi dua peran, bukan satu:

- **Primer — Dosen/asisten penilai.** Tidak butuh onboarding atau UX polish; butuh bisa memverifikasi: (a) dataset RDF sendiri ada dan bisa di-query, (b) federated query ke DBpedia benar-benar jalan (bukan hardcode), (c) fitur bonus berfungsi saat demo 1–3 menit.
- **Sekunder — Persona demo ("pengguna film enthusiast Indonesia").** Persona ini **hipotetis**, dipakai sebagai skenario cerita saat presentasi (mis. "Andi mau nonton weekend ini, cari film, lihat sinopsis, trailer, cek platform streaming, tanya chatbot rekomendasi mirip"). Tidak perlu riset user riil, tidak perlu wawancara, tidak perlu data demografis nyata.

**Bukan target:** pengguna publik produksi, retensi, analytics, SEO.

---

## 3. Goals dan Non-Goals

### Goals
- Membuktikan federated SPARQL query nyata: 1 query dari Fuseki (dataset sendiri) yang memakai klausa `SERVICE` ke endpoint DBpedia, hasilnya tergabung dalam satu response.
- Menunjukkan reuse ontologi (schema.org, termasuk `schema:Person`/`schema:Country` sebagai resource) + ekstensi vocab sendiri (`film:`) secara konsisten di seluruh dataset.
- Menunjukkan query SPARQL lanjutan (bukan cuma SELECT dasar): agregasi `GROUP BY`+`COUNT` (fitur Statistik) dan graph traversal lewat resource yang sama (fitur Film Sejenis) — nilai tambah kuat untuk rubrik "maksimalkan SPARQL".
- Landing page + halaman pencarian + halaman detail film yang menggabungkan: metadata sendiri, sinopsis DBpedia, trailer YouTube, OST, ketersediaan streaming, review, box office dalam IDR, dan chatbot.
- RDFa dan Open Graph Protocol tertanam di halaman detail film (syarat wajib dari dokumen tugas).
- Semua fitur di atas stabil dijalankan di localhost untuk demo 1–3 menit + penjelasan kode utama.
- Dataset cukup besar untuk meyakinkan (bukan skala produksi) — **jumlah pastinya masih Open Question #3**.

### Non-Goals
- Tidak ada akun pengguna, login, watchlist, atau rating/review dari user asli.
- Tidak ada rekomendasi personalized berbasis machine learning.
- Tidak deploy ke server publik / domain sendiri (sesuai definisi "selesai").
- Tidak menyimpan data film di MySQL sebagai penyimpanan utama — MySQL hanya untuk hal non-film (lihat §7).
- Tidak menangani concurrency multi-user skala produksi.
- Tidak membuat versi mobile/PWA (di luar scope tugas ini).
- Chatbot **tidak wajib** melakukan RAG/NL2SPARQL ke dataset sendiri (keputusan tim: general knowledge LLM) — lihat trade-off di Open Question #6.
- PRD ini **bukan pengganti** paper jurnal akademik yang wajib dikumpulkan terpisah (Abstrak, Pendahuluan, Tinjauan Pustaka, dst).

---

## 4. User Stories

**Persona demo (pengguna):**
1. Sebagai pengguna, saya ingin mencari film berdasarkan judul/genre/tahun/negara, agar saya cepat menemukan film yang saya cari.
2. Sebagai pengguna, saya ingin membuka halaman detail satu film yang menggabungkan metadata dasar, sinopsis lengkap, dan poster dalam satu tampilan, agar saya tidak perlu berpindah situs.
3. Sebagai pengguna, saya ingin memutar trailer YouTube langsung di halaman film, agar saya bisa preview tanpa pindah tab.
4. Sebagai pengguna, saya ingin mendengarkan preview soundtrack film, agar saya dapat merasakan nuansa musik film sebelum menonton.
5. Sebagai pengguna, saya ingin tahu platform streaming apa saja yang menyediakan film ini di Indonesia, agar saya tahu di mana bisa menonton.
6. Sebagai pengguna, saya ingin melihat box office film dalam Rupiah, agar saya punya konteks angka yang familiar.
7. Sebagai pengguna, saya ingin bertanya bebas ke chatbot seputar film (misal "siapa sutradara Inception?", "film mirip Interstellar apa saja?"), agar saya bisa eksplorasi tanpa harus tahu cara search manual.
8. Sebagai pengguna, saya ingin melihat daftar "Film Sejenis" (sutradara/genre sama) di halaman detail, agar saya dapat rekomendasi tontonan lanjutan.
9. Sebagai pengguna, saya ingin melihat statistik jumlah film per genre, agar saya dapat gambaran isi dataset.

**Dosen/penilai:**
10. Sebagai penilai, saya ingin melihat query SPARQL federated benar-benar tereksekusi (bukan data statis/hardcode), agar saya bisa memverifikasi pemenuhan syarat tugas.
11. Sebagai penilai, saya ingin bisa membuka endpoint Fuseki dan menjalankan query manual pada dataset, agar saya bisa memverifikasi originalitas dan struktur dataset.

**Developer (anggota kelompok):**
12. Sebagai anggota kelompok, saya ingin dataset TMDB otomatis ter-generate jadi RDF triples lewat script, agar saya tidak input data manual satu per satu.

---

## 5. Daftar Fitur — MVP / V2 / Nanti

### MVP (wajib untuk submission KOM C)
| # | Fitur | Status saat ini | Kenapa MVP |
|---|---|---|---|
| 1 | Pencarian film (judul/genre/tahun/negara) via SPARQL ke Fuseki | ✅ Sudah ada (`FilmController::search`) | Fondasi seluruh aplikasi |
| 2 | Halaman detail film — federated query (dataset sendiri + `SERVICE` DBpedia) | ✅ Sudah ada (`FilmController::detail`) | Inti pembuktian tugas, bobot terbesar rubrik Aplikasi |
| 3 | Dataset RDF sendiri (`film.ttl`) + endpoint Fuseki bisa di-query manual | 🔧 Script generator sudah dibuat, dataset belum di-generate & di-load | Syarat wajib dokumen tugas |
| 4 | RDFa markup + Open Graph Protocol di halaman detail | ⏳ Belum ada (Blade view belum dibuat) | Syarat wajib eksplisit dokumen tugas |
| 5 | Chatbot LLM (OpenAI gpt-4o-mini, general knowledge) | ⏳ Belum ada route/controller | Prioritas #1 dari ranking tim |
| 6 | Trailer YouTube (via TMDB videos endpoint) | ⏳ **Belum ada** — tidak ditemukan di `fetchExternalServices()` saat ini | Bagian dari prioritas #2 |
| 7 | OST via iTunes Search API (audio preview) | ✅ Sudah ada, sebagai `soundtrack` di `Http::pool()` | Bagian dari prioritas #2 |
| 8 | Info ketersediaan streaming (TMDB watch/providers, region ID) | ✅ Sudah ada, sebagai `streaming` di `Http::pool()` | Prioritas #3 |
| 9 | Box office converter ke IDR (ExchangeRate API) | ✅ Sudah ada (`boxOfficeIDR` di `fetchExternalServices()`) | Prioritas #4 |
| 10 | Film Sejenis (SPARQL graph traversal via sutradara/genre resource yang sama) | ✅ Sudah ada (`fetchSimilarFilms`) — **naik dari V2**, showcase SPARQL lanjutan | Bukti kuat "maksimalkan SPARQL" |
| 11 | Statistik genre (SPARQL `GROUP BY`+`COUNT`) | ✅ Sudah ada (`FilmController::statistik`) — **naik dari tidak terdaftar** | Bukti kuat "maksimalkan SPARQL" |
| 12 | Live Reviews TMDB | ✅ Sudah ada, sebagai `reviews` di `Http::pool()` — **naik dari V2** | Nilai tambah, effort kecil karena satu pool dengan #8 |

### V2 (kalau waktu masih ada setelah MVP solid)
- Autocomplete/typeahead pada search bar
- Sinopsis multi-bahasa (fallback ke `@id` dari DBpedia/Wikidata kalau tersedia)
- Upgrade chatbot jadi RAG/NL2SPARQL ke dataset sendiri
- Caching layer (file cache Laravel atau Redis) untuk hasil federated query & response iTunes/TMDB yang sering diakses

### Nanti (di luar scope tugas ini sepenuhnya)
- Akun pengguna, watchlist, rating personal
- Deploy produksi ke domain publik
- Aplikasi mobile/PWA
- Scaling multi-user produksi
- Moderasi konten chatbot tingkat produksi
- Rekomendasi personalized berbasis ML

---

## 6. Functional Requirements — Detail per Fitur MVP

### FR-1 · Pencarian Film ✅
- **Input:** teks judul (partial match) + filter opsional genre, tahun, negara (`?q`, `?genre`, `?tahun`, `?negara`).
- **Sumber data:** SPARQL `SELECT` ke Fuseki, `FILTER(CONTAINS(LCASE(?nama), LCASE("...")))` pada `schema:name`, filter serupa untuk `?genreLabel`/`?negaraLabel` (via resource) dan `FILTER(YEAR(?tahun) = ...)`.
- **Output:** daftar hasil (uri, id, nama, tahun, genre gabungan, negara gabungan, poster), `LIMIT 50`, `ORDER BY ?nama`.
- **Validasi:** input di-escape manual (`FilmController::escape()`) sebelum masuk string SPARQL — mitigasi SPARQL injection dasar. **Catatan:** escape saat ini cuma `str_replace` kutip; kalau mau lebih aman pertimbangkan parameterized query builder EasyRDF.
- **Empty state:** hasil kosong → tampilkan "Film tidak ditemukan" (implementasi di Blade view, belum dibuat).

### FR-2 · Halaman Detail Film (inti federated query) ✅
Alur data, urut (sesuai `FilmController::detail`):
1. **Query Fuseki (dataset sendiri)** by resource URI (`http://example.org/film/{id}`) → nama, tahun, sutradara (resource → `schema:name`), genre (resource → `rdfs:label`), aktor (resource → `schema:name`), negara (resource → `schema:name`), poster (`schema:image`), durasi, `film:tmdbId`, `film:boxOfficeUSD`, `owl:sameAs`.
2. **Federated SPARQL** (`fetchAbstractFromDbpedia`) — query terpisah yang dikirim ke Fuseki dengan klausa `SERVICE <https://dbpedia.org/sparql>`, memakai URI `owl:sameAs` dari langkah 1, ambil `dbo:abstract` bahasa Inggris:
   ```sparql
   PREFIX dbo: <http://dbpedia.org/ontology/>
   SELECT ?abstract WHERE {
     SERVICE <https://dbpedia.org/sparql> {
       <URI_DBPEDIA_DARI_OWL_SAMEAS> dbo:abstract ?abstract .
       FILTER(LANG(?abstract) = "en")
     }
   }
   LIMIT 1
   ```
   Kalau film tidak punya `owl:sameAs`, langkah ini di-skip (lihat §9).
3. **Runtime API calls paralel** (`Http::pool()`, fungsi `fetchExternalServices`) — **saat ini 4 request**, BUKAN termasuk trailer:
   - `soundtrack` → iTunes Search API (`itunes.apple.com/search?term={judul}&media=music`) — gratis, tanpa API key.
   - `reviews` → TMDB `/movie/{tmdbId}/reviews` (kalau `tmdbId` & TMDB key ada).
   - `streaming` → TMDB `/movie/{tmdbId}/watch/providers` (data JustWatch lewat TMDB).
   - `exchange` → ExchangeRate API (`open.er-api.com/v6/latest/USD`) → hitung `boxOfficeUSD × rate`.
   - **Trailer (TMDB `/movie/{id}/videos`) BELUM ditambahkan ke pool ini** — lihat Open Question #9.
4. **Film Sejenis** (`fetchSimilarFilms`) — query SPARQL terpisah, `UNION` antara "sutradara resource sama" dan "genre resource sama", `FILTER(?filmLain != <film ini>)`, `LIMIT 6`.
5. Render satu halaman, dengan RDFa + OGP tertanam di HTML yang sama (lihat FR-7 — **Blade view-nya belum dibuat**).
- **Failure handling:** setiap sub-service (Fuseki, DBpedia, iTunes, TMDB, ExchangeRate) gagal independen — kode sudah pakai try/catch + `Log::warning`, tidak menjatuhkan seluruh halaman (lihat §9).

### FR-3 · Chatbot Tanya-Jawab Film ⏳ (belum diimplementasi)
- **UI:** widget chat (floating button/sidebar), tersedia di semua halaman.
- **Backend:** kirim pertanyaan ke **OpenAI API (`gpt-4o-mini`)** dengan system prompt: asisten CineGraph, boleh menjawab pengetahuan umum soal film di luar dataset.
- **Tidak wajib** memanggil SPARQL endpoint sendiri — ini keputusan tim yang disengaja. Catatan: karena itu, komponen ini **tidak menyumbang poin ke rubrik "maksimalkan SPARQL"** (lihat Open Question #6) — tapi fitur Film Sejenis & Statistik (FR baru di atas) sudah menutupi sebagian gap ini.
- **Kontrol biaya:** batasi jumlah pesan per sesi (mis. maks 15–20).
- **Riwayat chat:** cukup in-memory/session, tidak perlu persist ke database.
- **Failure state:** timeout/quota habis → tampilkan pesan graceful, jangan biarkan UI hang.

### FR-4 · Trailer YouTube (⏳ belum ada) & OST iTunes (✅ sudah ada)
- **Trailer — BELUM DIIMPLEMENTASI:** perlu tambah 1 request ke pool `fetchExternalServices()`: TMDB `/movie/{id}/videos`, filter `site=YouTube` + `type=Trailer`, prioritas `official=true`, fallback trailer pertama. Embed via `<iframe>` YouTube standar di Blade view.
- **OST — sudah diimplementasi via iTunes Search API, BUKAN Spotify.** Alasan pivot ini **tepat dan terverifikasi**: sejak 6 Februari 2026, Spotify mewajibkan pemilik app Development Mode punya akun **Premium**, membatasi maksimal **5 authorized test user per app**, dan mempersempit endpoint yang tersedia — sama sekali tidak praktis untuk demo ke satu kelas + dosen. iTunes Search API gratis, tanpa API key/OAuth, tanpa batas user, jadi pilihan yang jauh lebih aman untuk konteks tugas ini.
- Kalau iTunes tidak menemukan match soundtrack yang masuk akal → sembunyikan section OST, jangan tampilkan pemutar kosong/error (perlu dipastikan diimplementasikan di Blade view).

### FR-5 · Info Ketersediaan Streaming ✅
- **Sumber:** TMDB `/movie/{id}/watch/providers` (sudah di pool, key `streaming`).
- **Tampilkan:** logo provider + kategori (flatrate/rent/buy) — **implementasi tampilan di Blade view masih perlu dibuat**, controller baru mengembalikan raw JSON `results.ID` (fallback `results.US`).
- **Wajib** cantumkan atribusi: data disediakan oleh JustWatch (syarat penggunaan TMDB).
- Region ID kosong → tampilkan pesan eksplisit "Belum tersedia data streaming untuk wilayah Indonesia", jangan diam-diam fallback ke region lain **secara silent di UI** (kode saat ini fallback ke `results.US` di level data — kalau dipakai, UI wajib beri keterangan "data US sebagai fallback").

### FR-6 · Box Office Converter ke IDR ✅
- `boxOfficeUSD` diambil dari **dataset RDF sendiri** (properti `film:boxOfficeUSD`) — sudah benar, controller tidak mengisi nilai kalau properti ini tidak ada di dataset (bukan tampilkan 0).
- Konversi sudah dihitung di `fetchExternalServices()`: `boxOfficeIDR = round(boxOfficeUSD * rate)`. **Perlu ditambahkan** di Blade view: tampilkan rate & waktu konversi ("Kurs: 1 USD = Rp X, per [timestamp]") — saat ini controller belum mengembalikan timestamp konversi secara eksplisit.
- Kalau film tidak punya data box office → section otomatis `null`, tinggal di-hide di view.

### FR-7 · RDFa & Open Graph Protocol ⏳ (belum diimplementasi — Blade view belum dibuat)
- Setiap halaman detail film wajib memuat markup RDFa memakai vocab schema.org **versi https** (samakan dengan namespace controller): `vocab="https://schema.org/"`, `typeof="Movie"`, `property="name"`, `property="genre"`, `property="datePublished"`, dst.
- Open Graph meta tags di `<head>`: `og:title`, `og:type="video.movie"`, `og:image` (dari `$film['poster']`), `og:description` (dari `$film['sinopsis']`).

### FR-8 · SPARQL Endpoint Sendiri (Fuseki)
- Fuseki wajib running (minimal localhost, port 3030) dengan dataset dari `film.ttl` + `cinegraph-ontology.ttl` ter-load, nama dataset `film` (lihat `config('services.fuseki.endpoint')` default `http://localhost:3030/film/sparql`).
- Endpoint harus bisa diquery manual lewat UI bawaan Fuseki, untuk verifikasi dosen saat demo.
- Query federated di FR-2 poin 2 harus bisa dijalankan langsung di Fuseki tanpa lewat aplikasi Laravel sama sekali — ini bukti paling kuat untuk rubrik penilaian.

### FR-9 · Film Sejenis ✅ (naik dari V2 — sudah diimplementasi)
- **Logic:** SPARQL `UNION` — cari film lain yang berbagi resource `schema:director` ATAU resource `schema:genre` yang SAMA PERSIS dengan film ini, `FILTER` exclude film itu sendiri, `LIMIT 6`.
- Ini showcase nyata manfaat pemodelan graph (resource, bukan literal) — nilai tambah kuat untuk rubrik "maksimalkan SPARQL".
- **Belum ada** di Blade view — tinggal render daftar `$film['filmSejenis']`.

### FR-10 · Statistik Genre ✅ (naik dari tidak terdaftar — sudah diimplementasi)
- **Route:** `GET /statistik`.
- **Logic:** SPARQL `GROUP BY ?genreLabel` + `COUNT(DISTINCT ?film)`, `ORDER BY DESC(?jumlah)`.
- **Belum ada** Blade view (`film.statistik`) — bisa jadi bar chart sederhana.

---

## 7. Arsitektur & Stack Teknologi

```
Browser User
   │
   ▼
Laravel App (Blade, server-rendered)
   │
   ├──(1)──► Apache Jena Fuseki [localhost:3030/film/sparql]
   │           dataset: film.ttl + cinegraph-ontology.ttl
   │           (schema.org https + film: <http://example.org/ontology/>)
   │           federated: SERVICE <https://dbpedia.org/sparql>
   │           → metadata dasar + sinopsis lengkap + Film Sejenis + Statistik
   │
   ├──(2)──► TMDB API
   │           /movie/{id}/videos          → trailer YouTube key   [BELUM diimplementasi]
   │           /movie/{id}/watch/providers → streaming availability (region=ID)  ✅
   │           /movie/{id}/reviews         → live reviews  ✅
   │
   ├──(3)──► iTunes Search API (gratis, tanpa key/OAuth)
   │           search?term={judul}&media=music → preview audio OST  ✅
   │
   ├──(4)──► ExchangeRate API (open.er-api.com)
   │           USD → IDR rate untuk box office  ✅
   │
   └──(5)──► OpenAI API (gpt-4o-mini)
               chatbot general-knowledge   [BELUM diimplementasi]

MySQL (Laravel default)
   → HANYA untuk: session Laravel, cache config non-film
   → TIDAK ADA tabel `films`/metadata film apa pun (syarat tugas — perlu audit migration yang ada)
```

**Komponen kunci:**
- **Backend:** Laravel (PHP) + EasyRDF (`EasyRdf\Sparql\Client`) sebagai SPARQL client ke Fuseki — endpoint & base URI film diambil dari `config('services.fuseki.*)`, bukan hardcode (praktik bagus, sudah diikuti kode yang ada).
- **Triple store:** Apache Jena Fuseki, dataset dibangun offline/batch dari `fetch_tmdb_to_rdf.py` (TMDB API → RDF triples → load ke Fuseki). Proses ini **bukan** runtime.
- **Federated query:** dilakukan di level SPARQL (klausa `SERVICE` di dalam query yang dikirim ke Fuseki) — sudah diimplementasikan benar di `fetchAbstractFromDbpedia()`.
- **Frontend:** Blade + CSS framework bebas — **belum ada satupun file `.blade.php` di repo saat ini**, ini pekerjaan berikutnya yang paling mendesak (tanpa view, semua FR di atas tidak bisa didemokan).
- **Runtime enrichment layer:** iTunes, TMDB (reviews+streaming), ExchangeRate dipanggil paralel via `Http::pool()` di `fetchExternalServices()` — trailer belum masuk pool ini.
- **Database relasional:** **Aksi wajib:** audit migration Laravel yang ada — pastikan tidak ada tabel/migration yang menyimpan data film ke MySQL.
- **Deployment:** localhost untuk submission ini. Tidak perlu domain publik/hosting.

---

## 8. Ontologi Dataset

**Namespace (SUDAH FINAL — diambil dari `FilmController.php` yang ada, bukan placeholder lagi):**
- `film:` = `http://example.org/ontology/`
- `schema:` = `https://schema.org/` (**https**, perhatikan — beda dari draf v1.0 PRD ini)
- Base URI film = `http://example.org/film/{tmdbId}`
- Base URI person = `http://example.org/person/{slug-nama}` (baru — untuk `schema:director`/`schema:actor`)
- Base URI country = `http://example.org/country/{slug-nama}` (baru — untuk `schema:countryOfOrigin`)
- Base URI genre = `http://example.org/genre/{slug-nama}`

**Reuse class/property schema.org (genre/sutradara/aktor/negara SEBAGAI RESOURCE, bukan literal):**
| Elemen | Tipe | Keterangan |
|---|---|---|
| `schema:Movie` | Class | Entitas utama film |
| `schema:Person` | Class | Resource sutradara & aktor, ber-`schema:name` |
| `schema:Country` | Class | Resource negara produksi, ber-`schema:name` |
| `schema:name` | Property | Judul film (di Movie) / nama (di Person, Country) |
| `schema:datePublished` | Property | Tanggal rilis (`xsd:date`) |
| `schema:duration` | Property | Durasi ISO 8601 (mis. `PT148M`) |
| `schema:image` | Property | URL poster |
| `schema:genre` | Property | Movie → **resource** `film:Genre` (bukan literal) |
| `schema:director` | Property | Movie → **resource** `schema:Person` |
| `schema:actor` | Property | Movie → **resource** `schema:Person` (bisa banyak) |
| `schema:countryOfOrigin` | Property | Movie → **resource** `schema:Country` |

**Class/property custom (`film:`):**
| Elemen | Tipe | Domain/Range | Keterangan |
|---|---|---|---|
| `film:Genre` | Class | — | Tipe untuk resource genre; dihubungkan via `schema:genre` (BUKAN properti custom `hasGenre` — sudah dikoreksi dari draf v1.0) |
| `film:tmdbId` | Datatype Property | domain `schema:Movie`, range `xsd:integer` | ID eksternal TMDB |
| `film:wikidataId` | Datatype Property | domain `schema:Movie`, range `xsd:string` | QID Wikidata, jembatan mapping DBpedia |
| `film:boxOfficeUSD` | Datatype Property | domain `schema:Movie`, range `xsd:decimal` | Sumber untuk FR-6 |
| `owl:sameAs` | Standard OWL | range: URI resource DBpedia | Jembatan federated query, via Wikidata QID |

**Kenapa genre/sutradara/aktor/negara dibuat resource (bukan literal seperti draf v1.0 PRD ini)?** Supaya `FilmController::fetchSimilarFilms()` bisa mencocokkan "film lain dengan sutradara ATAU genre yang SAMA PERSIS" lewat perbandingan URI resource — jauh lebih akurat & jadi bukti nyata manfaat pemodelan graph RDF, bukan sekadar string matching.

---

## 9. Edge Case dan Failure State

| Situasi | Penanganan |
|---|---|
| DBpedia endpoint down/timeout saat federated query | `fetchAbstractFromDbpedia` sudah try/catch `EasyRdfException` → `Log::warning`, return `null`. Metadata dataset sendiri tetap tampil; section sinopsis perlu fallback teks di Blade view. |
| Film belum punya `owl:sameAs` ke DBpedia | Kode sudah cek `if ($film['sameAs'])` sebelum federated query — otomatis skip. |
| TMDB API rate limit/down | `Http::pool()` dibungkus try/catch `\Throwable`; kalau gagal, `$responses = []`, semua field terkait jadi `null` — perlu di-hide di Blade view. |
| iTunes tidak menemukan soundtrack match | `soundtrack` jadi `null` kalau request gagal/`successful()` false — sembunyikan section OST di view, jangan tampilkan pemutar kosong. |
| ExchangeRate API down/limit habis | `boxOfficeIDR` otomatis `null` (exchangeRate null) — tampilkan `boxOfficeUSD` asli + catatan "Konversi kurs sementara tidak tersedia". |
| LLM API error/timeout/quota habis | Belum diimplementasi — pastikan pesan graceful, jangan infinite loading. |
| Query search kosong/hanya spasi | **Belum divalidasi eksplisit** di `search()` — kalau `$keyword=''`, semua `$filters` kosong dan query jalan tanpa filter (return semua film). Pertimbangkan tambah validasi biar tidak query "SELECT semua" tanpa sengaja. |
| Fuseki down / SPARQL query gagal parse | `search()` & `detail()` sudah tangkap `EasyRdfException`; `search()` redirect `back()->withErrors()`, `detail()` `abort(500, ...)`. |
| Film dengan `boxOfficeUSD` null (film indie) | Sudah benar — script generator tidak menulis triple ini kalau `revenue` TMDB 0/kosong, jadi `$film['boxOfficeUSD']` otomatis `null`. |
| Judul film dengan karakter khusus (apostrof, non-ASCII) | `escape()` di controller replace `"` dan `\` — **cukup untuk kasus dasar, tapi bukan proteksi SPARQL injection penuh**. Untuk keamanan lebih baik, pertimbangkan parameterized query builder EasyRDF. |
| Beberapa orang buka halaman film yang sama bersamaan saat demo | Bukan target produksi. iTunes Search API tidak butuh OAuth/token sehingga tidak ada risiko spam re-auth (beda dari rencana awal pakai Spotify) — cukup pastikan tidak melebihi rate limit iTunes/TMDB kalau demo beruntun. |

---

## 10. Open Questions

Hal-hal ini **belum bisa saya putuskan sendiri** — butuh keputusan tim sebelum implementasi FR terkait dimulai:

1. ~~**Provider LLM untuk chatbot** belum dipilih~~ — **RESOLVED:** OpenAI (`gpt-4o-mini`), general knowledge.
2. ~~**Metode mapping antara resource dataset sendiri ↔ DBpedia** belum ditentukan~~ — **RESOLVED:** via Wikidata QID. Diimplementasikan di `fetch_tmdb_to_rdf.py`.
3. **Jumlah & cakupan film dalam dataset MVP** belum ditentukan — berapa judul, campuran global atau termasuk film Indonesia, rentang tahun.
4. **Bahasa sinopsis DBpedia** — defaultnya `dbo:abstract` versi Inggris (`@en`). Perlu fallback `@id`?
5. **Batasan kuota/biaya API** (OpenAI berbayar, kemungkinan rate limit TMDB/iTunes menjelang H-1 presentasi) belum dikonfirmasi tim.
6. **Trade-off chatbot general-knowledge:** tidak menyumbang poin "maksimalkan SPARQL" — tapi fitur Film Sejenis (FR-9) & Statistik (FR-10) yang sudah ada sebagian sudah menutupi gap ini. Apakah tim masih ingin tambah kemampuan chatbot yang query ke Fuseki?
7. **Audit migration MySQL** — perlu dipastikan tidak ada tabel yang menyimpan data film sebagai sumber tampil.
8. Dokumen ini **bukan pengganti** paper jurnal akademik yang wajib dikumpulkan terpisah.
9. **[BARU] Trailer YouTube (FR-4 bagian 1) belum diimplementasi** — siapa yang mengerjakan penambahan request TMDB `/movie/{id}/videos` ke `fetchExternalServices()`, dan kapan?
10. **[BARU] Semua Blade view belum dibuat** (`film.index`, `film.detail`, `film.statistik`) — tanpa ini, tidak ada satupun FR yang bisa didemokan secara visual meski logic SPARQL-nya sudah jalan. Ini blocker paling mendesak saat ini.
11. **[BARU] Landing page (`/`) masih di-comment di `routes/web.php`** — perlu diaktifkan & diarahkan ke view apa (search page langsung, atau landing terpisah)?
