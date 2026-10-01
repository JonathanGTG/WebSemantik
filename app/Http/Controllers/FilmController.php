<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use EasyRdf\Sparql\Client;
use EasyRdf\Exception as EasyRdfException;
use Illuminate\Support\Facades\Log;

class FilmController extends Controller
{
    /**
     * SPARQL endpoint milik sendiri (Apache Jena Fuseki).
     * Diambil dari config/services.php -> .env, bukan hardcode.
     */
    protected Client $client;
    protected string $externalEndpoint = 'https://dbpedia.org/sparql';

    public function __construct()
    {
        $this->client = new Client(
            config('services.fuseki.endpoint', 'http://localhost:3030/film/sparql')
        );
    }

    /**
     * Halaman pencarian film.
     * GET /search?q=keyword&genre=...&tahun=...
     */
    public function search(Request $request)
    {
        $keyword = trim($request->input('q', ''));
        $genre   = trim($request->input('genre', ''));
        $tahun   = trim($request->input('tahun', ''));
        $negara  = trim($request->input('negara', ''));

        // Validasi: kalau tidak ada satupun kriteria diisi, JANGAN kirim ke
        // SPARQL sama sekali (query tanpa FILTER = "SELECT semua film" yang
        // tidak sengaja) -- lihat PRD §9 edge case. Browsing tanpa keyword
        // sudah difasilitasi halaman utama (/), jadi /search murni untuk
        // pencarian aktif.
        if ($keyword === '' && $genre === '' && $tahun === '' && $negara === '') {
            return view('film.index', [
                'films'        => [],
                'keyword'      => $keyword,
                'genre'        => $genre,
                'tahun'        => $tahun,
                'negara'       => $negara,
                'belumMencari' => true,
            ]);
        }

        $filters = [];

        if ($keyword !== '') {
            $filters[] = 'FILTER(CONTAINS(LCASE(?nama), LCASE("' . $this->escape($keyword) . '")))';
        }
        if ($genre !== '') {
            $filters[] = 'FILTER(CONTAINS(LCASE(?genreLabel), LCASE("' . $this->escape($genre) . '")))';
        }
        if ($tahun !== '') {
            $filters[] = 'FILTER(YEAR(?tahun) = ' . intval($tahun) . ')';
        }
        if ($negara !== '') {
            $filters[] = 'FILTER(CONTAINS(LCASE(?negaraLabel), LCASE("' . $this->escape($negara) . '")))';
        }

        $filterClause = implode("\n                ", $filters);

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            SELECT ?film ?nama ?tahun ?poster
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                ?film a schema:Movie ;
                      schema:name ?nama ;
                      schema:datePublished ?tahun .
                OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { ?film schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
                OPTIONAL { ?film schema:image ?poster . }
                $filterClause
            }
            GROUP BY ?film ?nama ?tahun ?poster
            ORDER BY ?nama
            LIMIT 50
        SPARQL;

        $films = [];

        try {
            $result = $this->client->query($query);
            foreach ($result as $row) {
                $films[] = [
                    'uri'    => (string) $row->film,
                    'id'     => $this->extractId((string) $row->film),
                    'nama'   => (string) $row->nama,
                    'tahun'  => isset($row->tahun) ? (string) $row->tahun : null,
                    'genre'  => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                    'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
                    'poster' => isset($row->poster) ? (string) $row->poster : null,
                ];
            }
        } catch (EasyRdfException $e) {
            return back()
                ->withErrors(['sparql' => 'Gagal mengambil data dari SPARQL endpoint: ' . $e->getMessage()])
                ->withInput();
        }

        return view('film.index', [
            'films'   => $films,
            'keyword' => $keyword,
            'genre'   => $genre,
            'tahun'   => $tahun,
            'negara'  => $negara,
        ]);
    }

    /**
     * Halaman detail satu film.
     * GET /film/{id}
     */
    public function detail(string $id)
    {
        try {
            $film = $this->getFilmDetail($id);
        } catch (EasyRdfException $e) {
            abort(500, 'Gagal mengambil data film: ' . $e->getMessage());
        }

        if (!$film) {
            abort(404, 'Film tidak ditemukan');
        }

        return view('film.detail', ['film' => $film]);
    }

    /**
     * Halaman utama ("Now Playing" ala desain Pilem).
     * GET /?studio=Marvel+Studios
     */
    public function home(Request $request)
    {
        $studio     = trim($request->input('studio', ''));
        $featuredId = trim($request->input('featured', ''));

        // ── Mode Pencarian ────────────────────────────────────────────────
        // Kalau ada salah satu parameter search (?q, ?genre, ?tahun, ?negara),
        // home berfungsi sebagai halaman hasil pencarian (tidak tampil hero/featured).
        $keyword = trim($request->input('q', ''));
        $genre   = trim($request->input('genre', ''));
        $tahun   = trim($request->input('tahun', ''));
        $negara  = trim($request->input('negara', ''));

        $isSearchMode = ($keyword !== '' || $genre !== '' || $tahun !== '' || $negara !== '');

        if ($isSearchMode) {
            $filters = [];
            if ($keyword !== '') {
                $filters[] = 'FILTER(CONTAINS(LCASE(?nama), LCASE("' . $this->escape($keyword) . '")))';
            }
            if ($genre !== '') {
                $filters[] = 'FILTER(CONTAINS(LCASE(?genreLabel), LCASE("' . $this->escape($genre) . '")))';
            }
            if ($tahun !== '') {
                $filters[] = 'FILTER(YEAR(?tahun) = ' . intval($tahun) . ')';
            }
            if ($negara !== '') {
                $filters[] = 'FILTER(CONTAINS(LCASE(?negaraLabel), LCASE("' . $this->escape($negara) . '")))';
            }

            $filterClause = implode("\n                ", $filters);

            $sparql = <<<SPARQL
                PREFIX schema: <https://schema.org/>
                PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
                SELECT ?film ?nama ?tahun ?poster
                       (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                       (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                    ?film a schema:Movie ;
                          schema:name ?nama ;
                          schema:datePublished ?tahun .
                    OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                    OPTIONAL { ?film schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
                    OPTIONAL { ?film schema:image ?poster . }
                    $filterClause
                }
                GROUP BY ?film ?nama ?tahun ?poster
                ORDER BY ?nama
                LIMIT 50
            SPARQL;

            $films = [];
            try {
                $result = $this->client->query($sparql);
                foreach ($result as $row) {
                    $films[] = [
                        'uri'    => (string) $row->film,
                        'id'     => $this->extractId((string) $row->film),
                        'nama'   => (string) $row->nama,
                        'tahun'  => isset($row->tahun) ? (string) $row->tahun : null,
                        'genre'  => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                        'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
                        'poster' => isset($row->poster) ? (string) $row->poster : null,
                    ];
                }
            } catch (EasyRdfException $e) {
                return back()
                    ->withErrors(['sparql' => 'Gagal mengambil data dari SPARQL endpoint: ' . $e->getMessage()])
                    ->withInput();
            }

            return view('film.home', [
                'isSearchMode'  => true,
                'films'         => $films,
                'keyword'       => $keyword,
                'genre'         => $genre,
                'tahun'         => $tahun,
                'negara'        => $negara,
                // Tetap kirim nilai default agar view tidak error
                'nowPlaying'    => [],
                'featured'      => null,
                'studioAktif'   => '',
                'streamingPreview' => [],
                'studioTabs'    => [],
            ]);
        }

        // ── Mode Now Playing (default) ────────────────────────────────────
        $nowPlaying = $this->fetchNowPlaying($studio !== '' ? $studio : null, 8);

        $featuredCandidateId = $featuredId !== '' ? $featuredId : ($nowPlaying[0]['id'] ?? null);

        $featured = null;
        if ($featuredCandidateId) {
            try {
                $featured = $this->getFilmDetail($featuredCandidateId);
            } catch (EasyRdfException $e) {
                Log::warning('Gagal ambil detail film unggulan: ' . $e->getMessage());
            }
        }

        return view('film.home', [
            'isSearchMode'     => false,
            'nowPlaying'       => $nowPlaying,
            'featured'         => $featured,
            'studioAktif'      => $studio,
            'streamingPreview' => $this->fetchStreamingPreview($nowPlaying),
            'studioTabs'       => [
                ''                     => 'All',
                'Marvel Studios'       => 'Marvel Studios',
                'Pixar'                => 'Pixar',
                'Walt Disney Pictures' => 'Walt Disney',
            ],
            'keyword' => '',
            'genre'   => '',
            'tahun'   => '',
            'negara'  => '',
        ]);
    }

    /**
     * Ambil data lengkap satu film (metadata dataset sendiri + sinopsis
     * federated DBpedia + integrasi runtime + film sejenis + cast/crew).
     * Dipakai bersama oleh detail() dan home() (untuk film unggulan),
     * supaya tidak duplikasi logic. Return null kalau film tidak ada di
     * dataset. EasyRdfException dari query UTAMA sengaja tidak ditangkap
     * di sini -- biar pemanggil yang putuskan (detail() -> abort 500,
     * home() -> skip featured section saja, lihat method home()).
     */
    protected function getFilmDetail(string $id): ?array
    {
        // Konsistenkan dengan validasi $id di person() -- id resource film
        // di dataset kita selalu tmdbId (angka murni), jadi karakter lain
        // tidak mungkin valid. Mencegah karakter aneh ter-interpolasi ke
        // URI/SPARQL (lihat PRD §9, catatan yang sebelumnya cuma ada di FR-11).
        if (!preg_match('/^[0-9]+$/', $id)) {
            return null;
        }

        $filmUri = $this->buildFilmUri($id);

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX owl: <http://www.w3.org/2002/07/owl#>
            PREFIX film: <http://example.org/ontology/>
            SELECT ?nama ?tahun ?sutradara ?komposer ?poster ?durasi ?sameAs ?tmdbId ?boxOffice ?rating ?tagline
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?aktorNama; separator=", ") AS ?aktor)
                   (GROUP_CONCAT(DISTINCT ?studioLabel; separator=", ") AS ?studios)
                   (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                <$filmUri> schema:name ?nama ;
                           schema:datePublished ?tahun .
                OPTIONAL { <$filmUri> schema:duration ?durasi . }
                OPTIONAL { <$filmUri> schema:image ?poster . }
                OPTIONAL { <$filmUri> owl:sameAs ?sameAs . }
                OPTIONAL { <$filmUri> film:tmdbId ?tmdbId . }
                OPTIONAL { <$filmUri> film:boxOfficeUSD ?boxOffice . }
                OPTIONAL { <$filmUri> film:tmdbRating ?rating . }
                OPTIONAL { <$filmUri> film:studio ?studioLabel . }
                OPTIONAL { <$filmUri> schema:alternativeHeadline ?tagline . }
                OPTIONAL { <$filmUri> schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradara . }
                OPTIONAL { <$filmUri> schema:musicBy ?komposerRes . ?komposerRes schema:name ?komposer . }
                OPTIONAL { <$filmUri> schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { <$filmUri> schema:actor ?aktorRes . ?aktorRes schema:name ?aktorNama . }
                OPTIONAL { <$filmUri> schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
            }
            GROUP BY ?nama ?tahun ?sutradara ?komposer ?poster ?durasi ?sameAs ?tmdbId ?boxOffice ?rating ?tagline
        SPARQL;

        $result = $this->client->query($query);

        if (count($result) === 0) {
            return null;
        }

        $row = $result[0];

        $film = [
            'uri'          => $filmUri,
            'id'           => $id,
            'nama'         => (string) $row->nama,
            'tahun'        => isset($row->tahun) ? (string) $row->tahun : null,
            'sutradara'    => isset($row->sutradara) ? (string) $row->sutradara : null,
            'komposer'     => isset($row->komposer) ? (string) $row->komposer : null,
            'genre'        => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
            'studio'       => isset($row->studios) && (string) $row->studios !== '' ? (string) $row->studios : null,
            'aktor'        => isset($row->aktor) && (string) $row->aktor !== '' ? (string) $row->aktor : null,
            'negara'       => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
            'poster'       => isset($row->poster) ? (string) $row->poster : null,
            'durasi'       => isset($row->durasi) ? (string) $row->durasi : null,
            'sameAs'       => isset($row->sameAs) ? (string) $row->sameAs : null,
            'tmdbId'       => isset($row->tmdbId) ? (string) $row->tmdbId : null,
            'boxOfficeUSD' => isset($row->boxOffice) ? (float) (string) $row->boxOffice : null,
            'rating'       => isset($row->rating) ? (float) (string) $row->rating : null,
            'tagline'      => isset($row->tagline) ? (string) $row->tagline : null,
            'sinopsis'     => null,
        ];

        // Federated query ke DBpedia kalau film ini punya owl:sameAs
        if ($film['sameAs']) {
            $film['sinopsis'] = $this->fetchAbstractFromDbpedia($film['sameAs']);
        }

        // 4 integrasi runtime (bukan bagian dataset RDF utama): OST iTunes,
        // trailer YouTube, TMDB watch/providers (menggantikan JustWatch --
        // datanya bersumber dari JustWatch tapi disajikan lewat API TMDB
        // yang sudah kita punya), ExchangeRate Box Office IDR, dan TMDB
        // Live Reviews. Dikirim paralel pakai Http::pool() supaya halaman
        // detail tidak menunggu request berurutan.
        $film['externalData'] = $this->fetchExternalServices($film);

        // Rekomendasi "film sejenis" -- bukti nyata manfaat data berbentuk
        // graph: film lain yang sutradara ATAU genre-nya sama persis
        // (menunjuk ke resource URI yang sama), bukan sekadar teks cocok.
        $film['filmSejenis'] = $this->fetchSimilarFilms($filmUri);

        // Daftar sutradara & aktor SATU PER SATU beserta resource URI-nya
        // (bukan string gabungan seperti 'sutradara'/'aktor' di atas),
        // supaya masing-masing nama bisa jadi link ke halaman /person/{id}.
        $film['castCrew'] = $this->fetchCastAndCrew($filmUri);

        return $film;
    }

    /**
     * Daftar film untuk grid "Now Playing" di homepage, dengan filter
     * opsional per studio (Marvel Studios/Pixar/Walt Disney Pictures).
     * Diurutkan dari rating TMDB tertinggi -- film pertama otomatis jadi
     * "featured" di hero section (lihat home()).
     */
    protected function fetchNowPlaying(?string $studio, int $limit = 8): array
    {
        // FILTER EXISTS dipakai (bukan taruh FILTER langsung di variabel
        // OPTIONAL ?studioLabel) supaya tidak mengacaukan kombinasi baris
        // dari OPTIONAL genre/studio lain yang sama-sama multi-value.
        $studioFilter = $studio
            ? 'FILTER EXISTS { ?film film:studio ?studioMatch . FILTER(CONTAINS(LCASE(?studioMatch), LCASE("' . $this->escape($studio) . '"))) }'
            : '';

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX film: <http://example.org/ontology/>
            SELECT ?film ?nama ?tahun ?poster ?rating ?tagline ?durasi ?sutradara ?komposer ?tmdbId
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?studioLabel; separator=", ") AS ?studios) WHERE {
                ?film a schema:Movie ;
                      schema:name ?nama ;
                      schema:datePublished ?tahun .
                OPTIONAL { ?film schema:image ?poster . }
                OPTIONAL { ?film film:tmdbRating ?rating . }
                OPTIONAL { ?film film:tmdbId ?tmdbId . }
                OPTIONAL { ?film schema:alternativeHeadline ?tagline . }
                OPTIONAL { ?film schema:duration ?durasi . }
                OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { ?film film:studio ?studioLabel . }
                OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradara . }
                OPTIONAL { ?film schema:musicBy ?komposerRes . ?komposerRes schema:name ?komposer . }
                $studioFilter
            }
            GROUP BY ?film ?nama ?tahun ?poster ?rating ?tagline ?durasi ?sutradara ?komposer ?tmdbId
            ORDER BY DESC(?rating)
            LIMIT $limit
        SPARQL;

        $films = [];

        try {
            $result = $this->client->query($query);
            foreach ($result as $row) {
                $films[] = [
                    'uri'      => (string) $row->film,
                    'id'       => $this->extractId((string) $row->film),
                    'nama'     => (string) $row->nama,
                    'tahun'    => isset($row->tahun) ? (string) $row->tahun : null,
                    'poster'   => isset($row->poster) ? (string) $row->poster : null,
                    'rating'   => isset($row->rating) ? (float) (string) $row->rating : null,
                    'tagline'  => isset($row->tagline) ? (string) $row->tagline : null,
                    'durasi'   => isset($row->durasi) ? (string) $row->durasi : null,
                    'sutradara'=> isset($row->sutradara) ? (string) $row->sutradara : null,
                    'komposer' => isset($row->komposer) ? (string) $row->komposer : null,
                    'tmdbId'   => isset($row->tmdbId) ? (string) $row->tmdbId : null,
                    'genre'    => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                    'studio'   => isset($row->studios) && (string) $row->studios !== '' ? (string) $row->studios : null,
                ];
            }
        } catch (EasyRdfException $e) {
            Log::warning('Query Now Playing gagal: ' . $e->getMessage());
        }

        return $films;
    }

    /**
     * Preview ketersediaan streaming untuk beberapa film di "Now Playing"
     * (bukan semua -- tiap film butuh 1 API call TMDB, dibatasi $limit
     * biar homepage tidak lambat). Dikirim paralel via Http::pool().
     */
    protected function fetchStreamingPreview(array $films, int $limit = 3): array
    {
        $tmdbKey = config('services.tmdb.key');
        $sample = array_slice(array_values(array_filter($films, fn ($f) => $f['tmdbId'])), 0, $limit);

        if (empty($sample) || !$tmdbKey) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($sample, $tmdbKey) {
                $requests = [];
                foreach ($sample as $f) {
                    $requests[$f['id']] = $pool->as($f['id'])->get(
                        "https://api.themoviedb.org/3/movie/{$f['tmdbId']}/watch/providers",
                        ['api_key' => $tmdbKey]
                    );
                }

                return $requests;
            });
        } catch (\Throwable $e) {
            Log::warning('Streaming preview Http::pool gagal: ' . $e->getMessage());

            return [];
        }

        $preview = [];

        foreach ($sample as $f) {
            $resp = $responses[$f['id']] ?? null;
            $data = ($resp && $resp->successful())
                ? ($resp->json('results.ID') ?? $resp->json('results.US'))
                : null;

            $preview[] = [
                'id'        => $f['id'],
                'nama'      => $f['nama'],
                'tahun'     => $f['tahun'],
                'rating'    => $f['rating'],
                'studio'    => $f['studio'],
                'providers' => $data['flatrate'] ?? $data['rent'] ?? $data['buy'] ?? [],
                'link'      => $data['link'] ?? null,
            ];
        }

        return $preview;
    }

    /**
     * Ambil daftar sutradara & aktor sebuah film beserta resource URI-nya
     * masing-masing (bukan digabung jadi satu string seperti kolom
     * 'sutradara'/'aktor' di query detail() utama), supaya tiap nama bisa
     * dijadikan link individual ke halaman detail orang.
     */
    protected function fetchCastAndCrew(string $filmUri): array
    {
        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            SELECT DISTINCT ?person ?nama ?peran ?foto WHERE {
                {
                    <$filmUri> schema:director ?person .
                    BIND("Sutradara" AS ?peran)
                }
                UNION
                {
                    <$filmUri> schema:actor ?person .
                    BIND("Aktor" AS ?peran)
                }
                ?person schema:name ?nama .
                OPTIONAL { ?person schema:image ?foto . }
            }
            ORDER BY (?peran = "Aktor") ?nama
        SPARQL;

        $castCrew = [];

        try {
            $result = $this->client->query($query);
            foreach ($result as $row) {
                $castCrew[] = [
                    'id'    => $this->extractId((string) $row->person),
                    'nama'  => (string) $row->nama,
                    'peran' => (string) $row->peran,
                    'foto'  => isset($row->foto) ? (string) $row->foto : null,
                ];
            }
        } catch (EasyRdfException $e) {
            Log::warning('Query cast & crew gagal: ' . $e->getMessage());
        }

        return $castCrew;
    }

    /**
     * Halaman detail satu orang (sutradara/aktor) + daftar film yang dia
     * ikut andil di dalamnya (sebagai sutradara dan/atau aktor).
     * GET /person/{id}
     */
    public function person(string $id)
    {
        // Validasi ringan: id resource person cuma boleh huruf kecil,
        // angka, dan strip (hasil slugify() di script generator dataset).
        // Mencegah karakter aneh ikut ter-interpolasi ke query SPARQL.
        if (!preg_match('/^[a-z0-9\-]+$/', $id)) {
            abort(404, 'Orang tidak ditemukan');
        }

        $personUri = $this->buildPersonUri($id);

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            SELECT ?nama ?foto WHERE {
                <$personUri> schema:name ?nama .
                OPTIONAL { <$personUri> schema:image ?foto . }
            }
            LIMIT 1
        SPARQL;

        try {
            $result = $this->client->query($query);
        } catch (EasyRdfException $e) {
            abort(500, 'Gagal mengambil data orang: ' . $e->getMessage());
        }

        if (count($result) === 0) {
            abort(404, 'Orang tidak ditemukan');
        }

        $person = [
            'id'   => $id,
            'uri'  => $personUri,
            'nama' => (string) $result[0]->nama,
            'foto' => isset($result[0]->foto) ? (string) $result[0]->foto : null,
        ];

        return view('film.person', [
            'person'     => $person,
            'filmografi' => $this->fetchFilmografi($personUri),
        ]);
    }

    /**
     * Cari semua film di dataset yang orang ini terlibat (sebagai sutradara
     * ATAU aktor), lewat resource URI yang sama persis -- pola query yang
     * sama dengan fetchSimilarFilms(), cuma arah relasinya dibalik.
     */
    protected function fetchFilmografi(string $personUri): array
    {
        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            SELECT DISTINCT ?film ?namaFilm ?tahun ?poster ?peran WHERE {
                {
                    ?film schema:director <$personUri> .
                    BIND("Sutradara" AS ?peran)
                }
                UNION
                {
                    ?film schema:actor <$personUri> .
                    BIND("Aktor" AS ?peran)
                }
                ?film schema:name ?namaFilm .
                OPTIONAL { ?film schema:datePublished ?tahun . }
                OPTIONAL { ?film schema:image ?poster . }
            }
            ORDER BY (?peran = "Aktor") ?namaFilm
        SPARQL;

        $filmografi = [];

        try {
            $result = $this->client->query($query);
            foreach ($result as $row) {
                $filmografi[] = [
                    'id'     => $this->extractId((string) $row->film),
                    'nama'   => (string) $row->namaFilm,
                    'tahun'  => isset($row->tahun) ? (string) $row->tahun : null,
                    'poster' => isset($row->poster) ? (string) $row->poster : null,
                    'peran'  => (string) $row->peran,
                ];
            }
        } catch (EasyRdfException $e) {
            Log::warning('Query filmografi gagal: ' . $e->getMessage());
        }

        return $filmografi;
    }

    /**
     * Cari film lain dengan sutradara atau genre yang sama (resource URI
     * yang sama persis), untuk fitur "Film Sejenis" di halaman detail.
     */
    protected function fetchSimilarFilms(string $filmUri): array
    {
        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            SELECT DISTINCT ?filmLain ?namaLain ?posterLain WHERE {
                {
                    <$filmUri> schema:director ?sutradaraRes .
                    ?filmLain schema:director ?sutradaraRes .
                }
                UNION
                {
                    <$filmUri> schema:genre ?genreRes .
                    ?filmLain schema:genre ?genreRes .
                }
                ?filmLain schema:name ?namaLain .
                OPTIONAL { ?filmLain schema:image ?posterLain . }
                FILTER(?filmLain != <$filmUri>)
            }
            LIMIT 6
        SPARQL;

        $similar = [];

        try {
            $result = $this->client->query($query);
            foreach ($result as $row) {
                $similar[] = [
                    'uri'    => (string) $row->filmLain,
                    'id'     => $this->extractId((string) $row->filmLain),
                    'nama'   => (string) $row->namaLain,
                    'poster' => isset($row->posterLain) ? (string) $row->posterLain : null,
                ];
            }
        } catch (EasyRdfException $e) {
            Log::warning('Query film sejenis gagal: ' . $e->getMessage());
        }

        return $similar;
    }

    /**
     * Halaman statistik: jumlah film per genre.
     * GET /statistik
     * Bukti "memaksimalkan penggunaan SPARQL" lewat query agregasi
     * (GROUP BY + COUNT), bukan sekadar SELECT biasa.
     */
    public function statistik()
    {
        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            SELECT ?genreLabel (COUNT(DISTINCT ?film) AS ?jumlah) WHERE {
                ?film a schema:Movie ;
                      schema:genre ?genreRes .
                ?genreRes rdfs:label ?genreLabel .
            }
            GROUP BY ?genreLabel
            ORDER BY DESC(?jumlah)
        SPARQL;

        $stats = [];

        try {
            $result = $this->client->query($query);
            foreach ($result as $row) {
                $stats[] = [
                    'genre'  => (string) $row->genreLabel,
                    'jumlah' => (int) (string) $row->jumlah,
                ];
            }
        } catch (EasyRdfException $e) {
            return back()->withErrors(['sparql' => 'Gagal mengambil statistik: ' . $e->getMessage()]);
        }

        return view('film.statistik', ['stats' => $stats]);
    }

    /**
     * Ambil data dari 4 layanan eksternal secara paralel. Kalau salah satu
     * gagal/timeout, bagian itu jadi null -- tidak menggagalkan halaman.
     */
    protected function fetchExternalServices(array $film): array
    {
        $tmdbKey = config('services.tmdb.key');

        try {
            $responses = Http::pool(function (Pool $pool) use ($film, $tmdbKey) {
                $requests = [];

                // OST lewat iTunes Search API -- gratis, tanpa API key/OAuth
                // sama sekali, jadi tidak tergantung status akun developer
                // (beda dengan Spotify yang sejak Feb 2026 mewajibkan akun
                // Premium untuk App di Development Mode).
                $requests[] = $pool->as('soundtrack')->get('https://itunes.apple.com/search', [
                    'term'  => $film['nama'],
                    'media' => 'music',
                    'limit' => 5,
                ]);

                if ($film['tmdbId'] && $tmdbKey) {
                    // Trailer YouTube -- TMDB videos endpoint langsung kasih
                    // video key YouTube, tidak perlu panggil YouTube Data API
                    // terpisah (lebih hemat kuota).
                    $requests[] = $pool->as('trailer')->get(
                        "https://api.themoviedb.org/3/movie/{$film['tmdbId']}/videos",
                        ['api_key' => $tmdbKey]
                    );

                    $requests[] = $pool->as('reviews')->get(
                        "https://api.themoviedb.org/3/movie/{$film['tmdbId']}/reviews",
                        ['api_key' => $tmdbKey]
                    );

                    // Data streaming ini bersumber dari JustWatch, disajikan
                    // lewat TMDB -- tidak perlu API key/partnership terpisah.
                    $requests[] = $pool->as('streaming')->get(
                        "https://api.themoviedb.org/3/movie/{$film['tmdbId']}/watch/providers",
                        ['api_key' => $tmdbKey]
                    );
                }

                $requests[] = $pool->as('exchange')->get(
                    config('services.exchange_rate.url', 'https://open.er-api.com/v6/latest/USD')
                );

                return $requests;
            });
        } catch (\Throwable $e) {
            Log::warning('Http::pool integrasi layanan lain gagal: ' . $e->getMessage());
            $responses = [];
        }

        $exchangeRate = (isset($responses['exchange']) && $responses['exchange']->successful())
            ? $responses['exchange']->json('rates.IDR')
            : null;

        return [
            'soundtrack' => (isset($responses['soundtrack']) && $responses['soundtrack']->successful())
                ? $responses['soundtrack']->json('results')
                : null,
            'trailerKey' => (isset($responses['trailer']) && $responses['trailer']->successful())
                ? $this->extractYoutubeTrailerKey($responses['trailer']->json('results') ?? [])
                : null,
            'streaming' => (isset($responses['streaming']) && $responses['streaming']->successful())
                ? ($responses['streaming']->json('results.ID') ?? $responses['streaming']->json('results.US'))
                : null,
            'reviews' => (isset($responses['reviews']) && $responses['reviews']->successful())
                ? $responses['reviews']->json('results')
                : null,
            'boxOfficeIDR' => ($film['boxOfficeUSD'] && $exchangeRate)
                ? round($film['boxOfficeUSD'] * $exchangeRate)
                : null,
            // Rate mentah + waktu update dari ExchangeRate API sendiri
            // (BUKAN waktu request kita), supaya keterangan "per [timestamp]"
            // di FR-6 akurat -- bukan cuma tampilkan waktu server kita.
            'exchangeRate'   => $exchangeRate,
            'exchangeRateAt' => (isset($responses['exchange']) && $responses['exchange']->successful())
                ? $responses['exchange']->json('time_last_update_utc')
                : null,
        ];
    }

    /**
     * Pilih video key YouTube terbaik dari hasil TMDB /movie/{id}/videos.
     * Prioritas: type=Trailer + official=true -> Trailer manapun -> video
     * YouTube pertama yang ada. Return null kalau tidak ada video YouTube
     * sama sekali (lihat PRD FR-4 & edge case sinopsis/trailer kosong).
     */
    protected function extractYoutubeTrailerKey(array $videos): ?string
    {
        $youtubeVideos = array_values(array_filter(
            $videos,
            fn ($v) => ($v['site'] ?? null) === 'YouTube'
        ));

        if (empty($youtubeVideos)) {
            return null;
        }

        foreach ($youtubeVideos as $video) {
            if (($video['type'] ?? null) === 'Trailer' && ($video['official'] ?? false) === true) {
                return $video['key'];
            }
        }

        foreach ($youtubeVideos as $video) {
            if (($video['type'] ?? null) === 'Trailer') {
                return $video['key'];
            }
        }

        return $youtubeVideos[0]['key'] ?? null;
    }

    /**
     * Ambil sinopsis (dbo:abstract) dari DBpedia lewat federated SPARQL query
     * yang dikirim melalui endpoint Fuseki sendiri (SERVICE clause).
     */
    protected function fetchAbstractFromDbpedia(string $dbpediaUri): ?string
    {
        $query = <<<SPARQL
            PREFIX dbo: <http://dbpedia.org/ontology/>
            SELECT ?abstract WHERE {
                SERVICE <{$this->externalEndpoint}> {
                    <$dbpediaUri> dbo:abstract ?abstract .
                    FILTER(LANG(?abstract) = "en")
                }
            }
            LIMIT 1
        SPARQL;

        try {
            $result = $this->client->query($query);
            if (count($result) > 0) {
                return (string) $result[0]->abstract;
            }
        } catch (EasyRdfException $e) {
            // Endpoint eksternal down/timeout tidak boleh menggagalkan halaman detail
            Log::warning('DBpedia federated query gagal: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Ubah URI film penuh -> id pendek untuk dipakai di URL Laravel.
     */
    protected function extractId(string $uri): string
    {
        return basename(parse_url($uri, PHP_URL_PATH) ?? $uri);
    }

    /**
     * Ubah id pendek dari URL -> URI penuh film di dataset.
     * Sesuaikan base URI ini dengan namespace dataset kalian sendiri.
     */
    protected function buildFilmUri(string $id): string
    {
        return config('services.fuseki.base_uri', 'http://example.org/film/') . $id;
    }

    /**
     * Ubah id pendek dari URL -> URI penuh resource person di dataset.
     * Sesuai namespace person yang dipakai fetch_tmdb_to_rdf.py.
     */
    protected function buildPersonUri(string $id): string
    {
        return config('services.fuseki.person_base_uri', 'http://example.org/person/') . $id;
    }

    /**
     * Escape sederhana untuk mencegah SPARQL injection dari input pencarian.
     */
    protected function escape(string $value): string
    {
        return str_replace(['"', '\\'], ['\\"', '\\\\'], $value);
    }
}