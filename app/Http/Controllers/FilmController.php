<?php

namespace App\Http\Controllers;

use App\Models\MoviePoster;
use EasyRdf\Exception as EasyRdfException;
use EasyRdf\Sparql\Client;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $genre = trim($request->input('genre', ''));
        $tahun = trim($request->input('tahun', ''));
        $negara = trim($request->input('negara', ''));

        // Validasi: kalau tidak ada satupun kriteria diisi, JANGAN kirim ke
        // SPARQL sama sekali (query tanpa FILTER = "SELECT semua film" yang
        // tidak sengaja) -- lihat PRD §9 edge case. Browsing tanpa keyword
        // sudah difasilitasi halaman utama (/), jadi /search murni untuk
        // pencarian aktif.
        if ($keyword === '' && $genre === '' && $tahun === '' && $negara === '') {
            return view('film.index', [
                'films' => [],
                'keyword' => $keyword,
                'genre' => $genre,
                'tahun' => $tahun,
                'negara' => $negara,
                'belumMencari' => true,
            ]);
        }

        $filters = [];

        if ($keyword !== '') {
            $filters = array_merge($filters, $this->buildKeywordFilter($keyword));
        }
        if ($genre !== '') {
            $filters[] = 'FILTER(CONTAINS(LCASE(?genreLabel), LCASE("'.$this->escape($genre).'")))';
        }
        if ($tahun !== '') {
            $filters[] = 'FILTER(YEAR(?tahun) = '.intval($tahun).')';
        }
        if ($negara !== '') {
            $filters[] = 'FILTER(CONTAINS(LCASE(?negaraLabel), LCASE("'.$this->escape($negara).'")))';
        }

        $filterClause = implode("\n                ", $filters);

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?film ?nama ?tahun ?tmdbId
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                ?film a schema:Movie ;
                      schema:name ?nama ;
                      schema:datePublished ?tahun .
                OPTIONAL { ?film film:tmdbId ?tmdbId . }
                OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { ?film schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
                OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradaraNama . }
                OPTIONAL { ?film schema:actor ?aktorRes . ?aktorRes schema:name ?aktorNama . }
                OPTIONAL { ?film schema:description ?deskripsi . }
                $filterClause
            }
            GROUP BY ?film ?nama ?tahun ?tmdbId
            ORDER BY ?nama
            LIMIT 50
        SPARQL;

        $films = [];

        try {
            $result = $this->client->query($query);
            $filmIds = [];
            foreach ($result as $row) {
                $filmId = $this->extractId((string) $row->film);
                $filmIds[] = $filmId;
                $films[] = [
                    'uri' => (string) $row->film,
                    'id' => $filmId,
                    'nama' => (string) $row->nama,
                    'tahun' => isset($row->tahun) ? (string) $row->tahun : null,
                    'genre' => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                    'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
                    'poster' => null,
                ];
            }

            // Ambil poster dari database (cepat & hemat resource)
            $posters = MoviePoster::getPosters($filmIds);
            foreach ($films as &$f) {
                $f['poster'] = $posters[$f['id']] ?? null;
            }
            unset($f);
        } catch (EasyRdfException $e) {
            return back()
                ->withErrors(['sparql' => 'Gagal mengambil data dari SPARQL endpoint: '.$e->getMessage()])
                ->withInput();
        }

        return view('film.index', [
            'films' => $films,
            'keyword' => $keyword,
            'genre' => $genre,
            'tahun' => $tahun,
            'negara' => $negara,
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
            abort(500, 'Gagal mengambil data film: '.$e->getMessage());
        }

        if (! $film) {
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
        $studio = trim($request->input('studio', ''));
        $featuredId = trim($request->input('featured', ''));

        // ── Mode Pencarian ────────────────────────────────────────────────
        // Kalau ada salah satu parameter search (?q, ?genre, ?tahun, ?negara),
        // home berfungsi sebagai halaman hasil pencarian (tidak tampil hero/featured).
        $keyword = trim($request->input('q', ''));
        $genre = trim($request->input('genre', ''));
        $tahun = trim($request->input('tahun', ''));
        $negara = trim($request->input('negara', ''));

        $isSearchMode = ($keyword !== '' || $genre !== '' || $tahun !== '' || $negara !== '');

        if ($isSearchMode) {
            $filters = [];
            if ($keyword !== '') {
                $filters = array_merge($filters, $this->buildKeywordFilter($keyword));
            }
            if ($genre !== '') {
                $filters[] = 'FILTER(CONTAINS(LCASE(?genreLabel), LCASE("'.$this->escape($genre).'")))';
            }
            if ($tahun !== '') {
                $filters[] = 'FILTER(YEAR(?tahun) = '.intval($tahun).')';
            }
            if ($negara !== '') {
                $filters[] = 'FILTER(CONTAINS(LCASE(?negaraLabel), LCASE("'.$this->escape($negara).'")))';
            }

            $filterClause = implode("\n                ", $filters);

            $sparql = <<<SPARQL
                PREFIX schema: <https://schema.org/>
                PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
                PREFIX film: <http://pilem.org/cinegraph/ontology#>
                SELECT ?film ?nama ?tahun ?tmdbId
                       (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                       (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                    ?film a schema:Movie ;
                          schema:name ?nama ;
                          schema:datePublished ?tahun .
                    OPTIONAL { ?film film:tmdbId ?tmdbId . }
                    OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                    OPTIONAL { ?film schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
                    OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradaraNama . }
                    OPTIONAL { ?film schema:actor ?aktorRes . ?aktorRes schema:name ?aktorNama . }
                    OPTIONAL { ?film schema:description ?deskripsi . }
                    $filterClause
                }
                GROUP BY ?film ?nama ?tahun ?tmdbId
                ORDER BY ?nama
                LIMIT 50
            SPARQL;

            $films = [];
            try {
                $result = $this->client->query($sparql);
                $filmIds = [];
                foreach ($result as $row) {
                    $filmId = $this->extractId((string) $row->film);
                    $filmIds[] = $filmId;
                    $films[] = [
                        'uri' => (string) $row->film,
                        'id' => $filmId,
                        'nama' => (string) $row->nama,
                        'tahun' => isset($row->tahun) ? (string) $row->tahun : null,
                        'genre' => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                        'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
                        'poster' => null,
                    ];
                }

                $posters = MoviePoster::getPosters($filmIds);
                foreach ($films as &$f) {
                    $f['poster'] = $posters[$f['id']] ?? null;
                }
                unset($f);
            } catch (EasyRdfException $e) {
                return back()
                    ->withErrors(['sparql' => 'Gagal mengambil data dari SPARQL endpoint: '.$e->getMessage()])
                    ->withInput();
            }

            return view('film.home', [
                'isSearchMode' => true,
                'films' => $films,
                'keyword' => $keyword,
                'genre' => $genre,
                'tahun' => $tahun,
                'negara' => $negara,
                // Tetap kirim nilai default agar view tidak error
                'nowPlaying' => [],
                'featured' => null,
                'studioAktif' => '',
                'streamingPreview' => [],
                'studioTabs' => [],
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
                Log::warning('Gagal ambil detail film unggulan: '.$e->getMessage());
            }
        }

        return view('film.home', [
            'isSearchMode' => false,
            'nowPlaying' => $nowPlaying,
            'featured' => $featured,
            'studioAktif' => $studio,
            'streamingPreview' => $this->fetchStreamingPreview($nowPlaying),
            'studioTabs' => [
                '' => 'All',
                'Marvel Studios' => 'Marvel Studios',
                'Pixar' => 'Pixar',
                'Walt Disney Pictures' => 'Walt Disney',
            ],
            'keyword' => '',
            'genre' => '',
            'tahun' => '',
            'negara' => '',
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
        // Validasi ID film: boleh alfanumerik dan strip (mis. tt0085936 atau tmdb-14813)
        if (! preg_match('/^[a-zA-Z0-9_\-]+$/', $id)) {
            return null;
        }

        $filmUri = $this->buildFilmUri($id);

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX owl: <http://www.w3.org/2002/07/owl#>
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?nama ?tahun ?sutradara ?durasi ?sameAs ?tmdbId ?imdbId ?boxOffice ?rating ?deskripsi ?rated ?runtimeMinutes
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?aktorNama; separator=", ") AS ?aktor)
                   (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                <$filmUri> schema:name ?nama ;
                           schema:datePublished ?tahun .
                OPTIONAL { <$filmUri> schema:duration ?durasi . }
                OPTIONAL { <$filmUri> owl:sameAs ?sameAs . }
                OPTIONAL { <$filmUri> film:tmdbId ?tmdbId . }
                OPTIONAL { <$filmUri> film:imdbId ?imdbId . }
                OPTIONAL { <$filmUri> film:boxOfficeUSD ?boxOffice . }
                OPTIONAL { <$filmUri> film:tmdbRating ?rating . }
                OPTIONAL { <$filmUri> film:rated ?rated . }
                OPTIONAL { <$filmUri> film:runtimeMinutes ?runtimeMinutes . }
                OPTIONAL { <$filmUri> schema:description ?deskripsi . }
                OPTIONAL { <$filmUri> schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradara . }
                OPTIONAL { <$filmUri> schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { <$filmUri> schema:actor ?aktorRes . ?aktorRes schema:name ?aktorNama . }
                OPTIONAL { <$filmUri> schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
            }
            GROUP BY ?nama ?tahun ?sutradara ?durasi ?sameAs ?tmdbId ?imdbId ?boxOffice ?rating ?deskripsi ?rated ?runtimeMinutes
        SPARQL;

        $result = $this->client->query($query);

        if (count($result) === 0) {
            return null;
        }

        $row = $result[0];

        // Poster dan studio diambil dari database lokal agar reload cepat
        $posterRecord = MoviePoster::where('film_id', $id)
            ->when(isset($row->tmdbId), fn ($q) => $q->orWhere('tmdb_id', (int) (string) $row->tmdbId))
            ->first();

        $film = [
            'uri' => $filmUri,
            'id' => $id,
            'nama' => (string) $row->nama,
            'tahun' => isset($row->tahun) ? (string) $row->tahun : null,
            'sutradara' => isset($row->sutradara) ? (string) $row->sutradara : null,
            'komposer' => null,
            'genre' => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
            'studio' => $posterRecord?->studio,
            'aktor' => isset($row->aktor) && (string) $row->aktor !== '' ? (string) $row->aktor : null,
            'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
            'poster' => $posterRecord?->display_poster,
            'durasi' => isset($row->durasi) ? (string) $row->durasi : null,
            'sameAs' => isset($row->sameAs) ? (string) $row->sameAs : null,
            'tmdbId' => isset($row->tmdbId) ? (string) $row->tmdbId : ($posterRecord?->tmdb_id ? (string) $posterRecord->tmdb_id : null),
            'imdbId' => isset($row->imdbId) ? (string) $row->imdbId : $id,
            'boxOfficeUSD' => isset($row->boxOffice) ? (float) (string) $row->boxOffice : null,
            'rating' => isset($row->rating) ? (float) (string) $row->rating : null,
            'rated' => isset($row->rated) ? (string) $row->rated : null,
            'runtimeMinutes' => isset($row->runtimeMinutes) ? (int) (string) $row->runtimeMinutes : null,
            'tagline' => null,
            'sinopsis' => isset($row->deskripsi) ? (string) $row->deskripsi : null,
        ];

        // Federated query ke DBpedia kalau film ini punya owl:sameAs
        if ($film['sameAs']) {
            $dbpediaSinopsis = $this->fetchAbstractFromDbpedia($film['sameAs']);
            if ($dbpediaSinopsis) {
                $film['sinopsis'] = $dbpediaSinopsis;
            }
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
        // Filter studio menggunakan pemetaan dataset di database
        $studioFilter = '';
        if ($studio) {
            $studioFilmIds = MoviePoster::where('studio', $studio)->pluck('film_id')->all();
            if (! empty($studioFilmIds)) {
                $uriList = implode(' ', array_map(fn ($fId) => '<'.$this->buildFilmUri($fId).'>', $studioFilmIds));
                $studioFilter = "VALUES ?film { {$uriList} }";
            }
        }

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?film ?nama ?tahun ?rating ?durasi ?sutradara ?tmdbId
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres) WHERE {
                ?film a schema:Movie ;
                      schema:name ?nama ;
                      schema:datePublished ?tahun .
                OPTIONAL { ?film film:tmdbRating ?rating . }
                OPTIONAL { ?film film:tmdbId ?tmdbId . }
                OPTIONAL { ?film schema:duration ?durasi . }
                OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradara . }
                $studioFilter
            }
            GROUP BY ?film ?nama ?tahun ?rating ?durasi ?sutradara ?tmdbId
            ORDER BY DESC(?rating)
            LIMIT $limit
        SPARQL;

        $films = [];

        try {
            $result = $this->client->query($query);
            $filmIds = [];
            foreach ($result as $row) {
                $fId = $this->extractId((string) $row->film);
                $filmIds[] = $fId;
                $films[] = [
                    'uri' => (string) $row->film,
                    'id' => $fId,
                    'nama' => (string) $row->nama,
                    'tahun' => isset($row->tahun) ? (string) $row->tahun : null,
                    'poster' => null,
                    'rating' => isset($row->rating) ? (float) (string) $row->rating : null,
                    'tagline' => null,
                    'durasi' => isset($row->durasi) ? (string) $row->durasi : null,
                    'sutradara' => isset($row->sutradara) ? (string) $row->sutradara : null,
                    'komposer' => null,
                    'tmdbId' => isset($row->tmdbId) ? (string) $row->tmdbId : null,
                    'genre' => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                    'studio' => null,
                ];
            }

            // Ambil poster dan studio dari database
            $posters = MoviePoster::getPosters($filmIds);
            $posterModels = MoviePoster::whereIn('film_id', $filmIds)->get()->keyBy('film_id');
            foreach ($films as &$f) {
                $f['poster'] = $posters[$f['id']] ?? null;
                $f['studio'] = $posterModels[$f['id']]->studio ?? null;
            }
            unset($f);
        } catch (EasyRdfException $e) {
            Log::warning('Query Now Playing gagal: '.$e->getMessage());
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

        if (empty($sample) || ! $tmdbKey) {
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
            Log::warning('Streaming preview Http::pool gagal: '.$e->getMessage());

            return [];
        }

        $preview = [];

        foreach ($sample as $f) {
            $resp = $responses[$f['id']] ?? null;
            $data = ($resp && $resp->successful())
                ? ($resp->json('results.ID') ?? $resp->json('results.US'))
                : null;

            $preview[] = [
                'id' => $f['id'],
                'nama' => $f['nama'],
                'tahun' => $f['tahun'],
                'rating' => $f['rating'],
                'studio' => $f['studio'],
                'providers' => $data['flatrate'] ?? $data['rent'] ?? $data['buy'] ?? [],
                'link' => $data['link'] ?? null,
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
                    'id' => $this->extractId((string) $row->person),
                    'nama' => (string) $row->nama,
                    'peran' => (string) $row->peran,
                    'foto' => isset($row->foto) ? (string) $row->foto : null,
                ];
            }
        } catch (EasyRdfException $e) {
            Log::warning('Query cast & crew gagal: '.$e->getMessage());
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
        if (! preg_match('/^[a-z0-9\-]+$/', $id)) {
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
            abort(500, 'Gagal mengambil data orang: '.$e->getMessage());
        }

        if (count($result) === 0) {
            abort(404, 'Orang tidak ditemukan');
        }

        $person = [
            'id' => $id,
            'uri' => $personUri,
            'nama' => (string) $result[0]->nama,
            'foto' => isset($result[0]->foto) ? (string) $result[0]->foto : null,
        ];

        return view('film.person', [
            'person' => $person,
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
            SELECT DISTINCT ?film ?namaFilm ?tahun ?peran WHERE {
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
            }
            ORDER BY (?peran = "Aktor") ?namaFilm
        SPARQL;

        $filmografi = [];

        try {
            $result = $this->client->query($query);
            $filmIds = [];
            foreach ($result as $row) {
                $fId = $this->extractId((string) $row->film);
                $filmIds[] = $fId;
                $filmografi[] = [
                    'id' => $fId,
                    'nama' => (string) $row->namaFilm,
                    'tahun' => isset($row->tahun) ? (string) $row->tahun : null,
                    'poster' => null,
                    'peran' => (string) $row->peran,
                ];
            }

            $posters = MoviePoster::getPosters($filmIds);
            foreach ($filmografi as &$item) {
                $item['poster'] = $posters[$item['id']] ?? null;
            }
            unset($item);
        } catch (EasyRdfException $e) {
            Log::warning('Query filmografi gagal: '.$e->getMessage());
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
            SELECT DISTINCT ?filmLain ?namaLain WHERE {
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
                FILTER(?filmLain != <$filmUri>)
            }
            LIMIT 6
        SPARQL;

        $similar = [];

        try {
            $result = $this->client->query($query);
            $filmIds = [];
            foreach ($result as $row) {
                $fId = $this->extractId((string) $row->filmLain);
                $filmIds[] = $fId;
                $similar[] = [
                    'uri' => (string) $row->filmLain,
                    'id' => $fId,
                    'nama' => (string) $row->namaLain,
                    'poster' => null,
                ];
            }

            $posters = MoviePoster::getPosters($filmIds);
            foreach ($similar as &$item) {
                $item['poster'] = $posters[$item['id']] ?? null;
            }
            unset($item);
        } catch (EasyRdfException $e) {
            Log::warning('Query film sejenis gagal: '.$e->getMessage());
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
        $query = <<<'SPARQL'
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
                    'genre' => (string) $row->genreLabel,
                    'jumlah' => (int) (string) $row->jumlah,
                ];
            }
        } catch (EasyRdfException $e) {
            return back()->withErrors(['sparql' => 'Gagal mengambil statistik: '.$e->getMessage()]);
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
                    'term' => $film['nama'],
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
            Log::warning('Http::pool integrasi layanan lain gagal: '.$e->getMessage());
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
            'exchangeRate' => $exchangeRate,
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
            Log::warning('DBpedia federated query gagal: '.$e->getMessage());
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
        return config('services.fuseki.base_uri', 'http://pilem.org/cinegraph/resource/movie/').$id;
    }

    /**
     * Ubah id pendek dari URL -> URI penuh resource person di dataset.
     * Sesuai namespace person yang dipakai fetch_tmdb_to_rdf.py.
     */
    protected function buildPersonUri(string $id): string
    {
        return config('services.fuseki.person_base_uri', 'http://pilem.org/cinegraph/resource/person/').$id;
    }

    /**
     * Escape sederhana untuk mencegah SPARQL injection dari input pencarian.
     */
    protected function escape(string $value): string
    {
        return str_replace(['"', '\\'], ['\\"', '\\\\'], $value);
    }

    /**
     * Parse keyword menjadi array filter SPARQL.
     * Membersihkan stopwords (seperti "film", "yang", "tentang") dan
     * mencocokkan setiap sisa kata secara terpisah (AND di antar kata, OR di antar field).
     */
    private function buildKeywordFilter(string $keyword): array
    {
        $filters = [];
        $keyword = mb_strtolower($keyword);

        // Buang tanda baca
        $keyword = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword);

        // Stopwords yang diabaikan dari pencarian (Bahasa Indonesia & Inggris)
        $stopwords = [
            'film', 'film-film', 'movies', 'movie', 'films',
            'tentang', 'yang', 'ada', 'dengan', 'aktor', 'sutradara', 'berjudul', 'cerita', 'kisah',
            'dan', 'di', 'ke', 'dari', 'pada', 'dalam', 'untuk', 'itu', 'ini', 'menampilkan', 'bercerita',
            'the', 'a', 'an', 'is', 'of', 'and', 'in', 'on', 'for', 'with', 'about', 'starring', 'featuring',
            'hanya', 'punya', 'tanya', // mencegah stemming '-nya' salah potong
        ];

        $tokens = explode(' ', $keyword);
        $cleanTokens = [];
        foreach ($tokens as $token) {
            $token = trim($token);

            // Stemming sangat sederhana untuk Bahasa Indonesia: buang akhiran 'nya'
            // Contoh: "thornya" -> "thor", "spidermannya" -> "spiderman"
            if (strlen($token) > 4 && substr($token, -3) === 'nya' && ! in_array($token, $stopwords)) {
                $token = substr($token, 0, -3);
            }

            if ($token !== '' && ! in_array($token, $stopwords)) {
                $cleanTokens[] = $token;
            }
        }

        // Jika setelah di-filter stopwords ternyata kosong, gunakan token asli
        if (empty($cleanTokens)) {
            foreach ($tokens as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $cleanTokens[] = $token;
                }
            }
        }

        foreach ($cleanTokens as $token) {
            $escKeyword = $this->escape($token);
            $filters[] = 'FILTER('.
                'CONTAINS(LCASE(?nama), LCASE("'.$escKeyword.'")) || '.
                'CONTAINS(LCASE(?sutradaraNama), LCASE("'.$escKeyword.'")) || '.
                'CONTAINS(LCASE(?aktorNama), LCASE("'.$escKeyword.'")) || '.
                'CONTAINS(LCASE(?deskripsi), LCASE("'.$escKeyword.'"))'.
            ')';
        }

        return $filters;
    }
}
