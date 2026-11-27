<?php

namespace App\Http\Controllers;

use App\Models\MoviePoster;
use EasyRdf\Exception as EasyRdfException;
use EasyRdf\Sparql\Client;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $huruf = strtoupper(trim($request->input('huruf', '')));
        $genre = trim($request->input('genre', ''));
        $tahun = trim($request->input('tahun', ''));
        $rated = trim($request->input('rated', ''));
        $sort = trim($request->input('sort', ''));
        $rating = trim($request->input('rating', ''));
        $urutanTahun = trim($request->input('urutan_tahun', ''));
        $negara = trim($request->input('negara', ''));

        // Kompatibilitas mundur jika sort lama masih dikirim
        if ($sort === 'year_desc' && $urutanTahun === '') {
            $urutanTahun = 'year_desc';
            $sort = '';
        } elseif ($sort === 'year_asc' && $urutanTahun === '') {
            $urutanTahun = 'year_asc';
            $sort = '';
        } elseif ($sort === 'rating_desc' && $rating === '') {
            $rating = 'rating_desc';
            $sort = '';
        }

        // Validasi: kalau tidak ada satupun kriteria diisi, JANGAN kirim ke
        // SPARQL sama sekali (query tanpa FILTER = "SELECT semua film" yang
        // tidak sengaja) -- lihat PRD §9 edge case. Browsing tanpa keyword
        // sudah difasilitasi halaman utama (/), jadi /search murni untuk
        // pencarian aktif.
        if ($keyword === '' && $huruf === '' && $genre === '' && $tahun === '' && $rated === '' && $sort === '' && $rating === '' && $urutanTahun === '' && $negara === '') {
            return view('film.index', [
                'films' => [],
                'keyword' => $keyword,
                'huruf' => $huruf,
                'genre' => $genre,
                'tahun' => $tahun,
                'rated' => $rated,
                'sort' => $sort,
                'rating' => $rating,
                'urutanTahun' => $urutanTahun,
                'negara' => $negara,
                'belumMencari' => true,
            ]);
        }

        $filters = [];

        if ($keyword !== '') {
            $filters = array_merge($filters, $this->buildKeywordFilter($keyword));
        }
        if ($huruf !== '') {
            if ($huruf === '#') {
                $filters[] = 'FILTER(!REGEX(?nama, "^[A-Za-z]"))';
            } else {
                $filters[] = 'FILTER(STRSTARTS(UCASE(?nama), "'.$this->escape($huruf).'"))';
            }
        }
        if ($genre !== '') {
            $filters[] = 'FILTER EXISTS { ?film schema:genre ?fg . ?fg rdfs:label ?fgLabel . FILTER(CONTAINS(LCASE(?fgLabel), LCASE("'.$this->escape($genre).'"))) }';
        }
        if ($tahun !== '') {
            $filters[] = 'FILTER(YEAR(?tahun) = '.intval($tahun).')';
        }
        if ($rated !== '') {
            $filters[] = 'FILTER(?ratedVal = "'.$this->escape($rated).'")';
        }
        if ($rating !== '') {
            if (in_array($rating, ['8', '7', '6', '5'])) {
                $filters[] = 'FILTER(BOUND(?ratingVal) && ?ratingVal >= '.floatval($rating).')';
            }
        }
        if ($negara !== '') {
            $filters[] = 'FILTER EXISTS { ?film schema:countryOfOrigin ?fnRes . ?fnRes schema:name ?fnLabel . FILTER(CONTAINS(LCASE(?fnLabel), LCASE("'.$this->escape($negara).'"))) }';
        }

        $filterClause = implode("\n                ", $filters);

        $orderClause = $this->buildOrderClause($keyword, $urutanTahun, $rating, $sort);

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?film ?nama ?tahun ?tmdbId (SAMPLE(?posterRaw) AS ?posterRdf) (SAMPLE(?ratingVal) AS ?rating) (SAMPLE(?ratedVal) AS ?rated)
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                ?film a schema:Movie ;
                      schema:name ?nama ;
                      schema:datePublished ?tahun .
                OPTIONAL { ?film schema:image ?posterRaw . }
                OPTIONAL { ?film film:tmdbId ?tmdbId . }
                OPTIONAL { ?film film:tmdbRating ?ratingVal . }
                OPTIONAL { ?film film:rated ?ratedVal . }
                OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { ?film schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
                OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradaraNama . }
                OPTIONAL { ?film schema:actor ?aktorRes . ?aktorRes schema:name ?aktorNama . }
                OPTIONAL { ?film schema:description ?deskripsi . }
                $filterClause
            }
            GROUP BY ?film ?nama ?tahun ?tmdbId
            $orderClause
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
                    'rating' => isset($row->rating) ? (float) (string) $row->rating : null,
                    'rated' => isset($row->rated) ? (string) $row->rated : null,
                    'genre' => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                    'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
                    'poster' => null,
                    'posterRdf' => isset($row->posterRdf) ? (string) $row->posterRdf : null,
                ];
            }

            // Ambil poster dari database (cepat & hemat resource), fallback ke RDF poster
            $posters = MoviePoster::getPosters($filmIds);
            foreach ($films as &$f) {
                $f['poster'] = $posters[$f['id']] ?? $f['posterRdf'] ?? null;
                unset($f['posterRdf']);
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
            'huruf' => $huruf,
            'genre' => $genre,
            'tahun' => $tahun,
            'rated' => $rated,
            'sort' => $sort,
            'rating' => $rating,
            'urutanTahun' => $urutanTahun,
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

        // ── Mode Pencarian & Filter ───────────────────────────────────────
        // Kalau ada salah satu parameter filter (?q, ?huruf, ?genre, ?tahun, ?rated, ?sort, ?rating, ?urutan_tahun, ?negara),
        // home berfungsi sebagai halaman hasil pencarian (tidak tampil hero/featured).
        $keyword = trim($request->input('q', ''));
        $huruf = strtoupper(trim($request->input('huruf', '')));
        $genre = trim($request->input('genre', ''));
        $tahun = trim($request->input('tahun', ''));
        $rated = trim($request->input('rated', ''));
        $sort = trim($request->input('sort', ''));
        $rating = trim($request->input('rating', ''));
        $urutanTahun = trim($request->input('urutan_tahun', ''));
        $negara = trim($request->input('negara', ''));

        // Kompatibilitas mundur jika sort lama masih dikirim
        if ($sort === 'year_desc' && $urutanTahun === '') {
            $urutanTahun = 'year_desc';
            $sort = '';
        } elseif ($sort === 'year_asc' && $urutanTahun === '') {
            $urutanTahun = 'year_asc';
            $sort = '';
        } elseif ($sort === 'rating_desc' && $rating === '') {
            $rating = 'rating_desc';
            $sort = '';
        }

        $isSearchMode = ($keyword !== '' || $huruf !== '' || $genre !== '' || $tahun !== '' || $rated !== '' || $sort !== '' || $rating !== '' || $urutanTahun !== '' || $negara !== '');

        if ($isSearchMode) {
            $filters = [];
            if ($keyword !== '') {
                $filters = array_merge($filters, $this->buildKeywordFilter($keyword));
            }
            if ($huruf !== '') {
                if ($huruf === '#') {
                    $filters[] = 'FILTER(!REGEX(?nama, "^[A-Za-z]"))';
                } else {
                    $filters[] = 'FILTER(STRSTARTS(UCASE(?nama), "'.$this->escape($huruf).'"))';
                }
            }
            if ($genre !== '') {
                $filters[] = 'FILTER EXISTS { ?film schema:genre ?fg . ?fg rdfs:label ?fgLabel . FILTER(CONTAINS(LCASE(?fgLabel), LCASE("'.$this->escape($genre).'"))) }';
            }
            if ($tahun !== '') {
                $filters[] = 'FILTER(YEAR(?tahun) = '.intval($tahun).')';
            }
            if ($rated !== '') {
                $filters[] = 'FILTER(?ratedVal = "'.$this->escape($rated).'")';
            }
            if ($rating !== '') {
                if (in_array($rating, ['8', '7', '6', '5'])) {
                    $filters[] = 'FILTER(BOUND(?ratingVal) && ?ratingVal >= '.floatval($rating).')';
                }
            }
            if ($negara !== '') {
                $filters[] = 'FILTER EXISTS { ?film schema:countryOfOrigin ?fnRes . ?fnRes schema:name ?fnLabel . FILTER(CONTAINS(LCASE(?fnLabel), LCASE("'.$this->escape($negara).'"))) }';
            }

            $filterClause = implode("\n                ", $filters);

            $orderClause = $this->buildOrderClause($keyword, $urutanTahun, $rating, $sort);

            $sparql = <<<SPARQL
                PREFIX schema: <https://schema.org/>
                PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
                PREFIX film: <http://pilem.org/cinegraph/ontology#>
                SELECT ?film ?nama ?tahun ?tmdbId (SAMPLE(?posterRaw) AS ?posterRdf) (SAMPLE(?ratingVal) AS ?rating) (SAMPLE(?ratedVal) AS ?rated)
                       (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                       (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                    ?film a schema:Movie ;
                          schema:name ?nama ;
                          schema:datePublished ?tahun .
                    OPTIONAL { ?film schema:image ?posterRaw . }
                    OPTIONAL { ?film film:tmdbId ?tmdbId . }
                    OPTIONAL { ?film film:tmdbRating ?ratingVal . }
                    OPTIONAL { ?film film:rated ?ratedVal . }
                    OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                    OPTIONAL { ?film schema:countryOfOrigin ?negaraRes . ?negaraRes schema:name ?negaraLabel . }
                    OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradaraNama . }
                    OPTIONAL { ?film schema:actor ?aktorRes . ?aktorRes schema:name ?aktorNama . }
                    OPTIONAL { ?film schema:description ?deskripsi . }
                    $filterClause
                }
                GROUP BY ?film ?nama ?tahun ?tmdbId
                $orderClause
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
                        'rating' => isset($row->rating) ? (float) (string) $row->rating : null,
                        'rated' => isset($row->rated) ? (string) $row->rated : null,
                        'genre' => isset($row->genres) && (string) $row->genres !== '' ? (string) $row->genres : null,
                        'negara' => isset($row->negaraAsal) && (string) $row->negaraAsal !== '' ? (string) $row->negaraAsal : null,
                        'poster' => null,
                        'posterRdf' => isset($row->posterRdf) ? (string) $row->posterRdf : null,
                    ];
                }

                $posters = MoviePoster::getPosters($filmIds);
                foreach ($films as &$f) {
                    $f['poster'] = $posters[$f['id']] ?? $f['posterRdf'] ?? null;
                    unset($f['posterRdf']);
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
                'huruf' => $huruf,
                'genre' => $genre,
                'tahun' => $tahun,
                'rated' => $rated,
                'sort' => $sort,
                'rating' => $rating,
                'urutanTahun' => $urutanTahun,
                'negara' => $negara,
                'category' => 'now_playing',
                // Tetap kirim nilai default agar view tidak error
                'nowPlaying' => [],
                'featured' => null,
                'studioAktif' => '',
                'streamingPreview' => [],
                'studioTabs' => [],
            ]);
        }

        // ── Mode Now Playing (default) ────────────────────────────────────
        $category = trim($request->input('category', 'now_playing'));
        $nowPlaying = $this->fetchNowPlaying($studio !== '' ? $studio : null, 16, $category);

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
            'category' => $category,
            'studioAktif' => $studio,
            'streamingPreview' => $this->fetchStreamingPreview($nowPlaying),
            'studioTabs' => [
                '' => 'All',
                'Marvel Studios' => 'Marvel Studios',
                'Pixar' => 'Pixar',
                'Walt Disney Pictures' => 'Walt Disney',
            ],
            'keyword' => '',
            'huruf' => '',
            'genre' => '',
            'tahun' => '',
            'rated' => '',
            'sort' => '',
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
            SELECT ?nama ?tahun ?sutradara ?durasi ?sameAs ?tmdbId ?imdbId ?boxOffice ?rating ?deskripsi ?rated ?runtimeMinutes ?posterRdf
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres)
                   (GROUP_CONCAT(DISTINCT ?aktorNama; separator=", ") AS ?aktor)
                   (GROUP_CONCAT(DISTINCT ?negaraLabel; separator=", ") AS ?negaraAsal) WHERE {
                <$filmUri> schema:name ?nama ;
                           schema:datePublished ?tahun .
                OPTIONAL { <$filmUri> schema:image ?posterRdf . }
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
            GROUP BY ?nama ?tahun ?sutradara ?durasi ?sameAs ?tmdbId ?imdbId ?boxOffice ?rating ?deskripsi ?rated ?runtimeMinutes ?posterRdf
        SPARQL;

        $result = $this->client->query($query);

        if (count($result) === 0) {
            return null;
        }

        $row = $result[0];

        // Poster dan studio diambil dari database lokal agar reload cepat, fallback ke RDF
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
            'poster' => $posterRecord?->display_poster ?? (isset($row->posterRdf) ? (string) $row->posterRdf : null),
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
     * Secara default menyaring rilis terbaru (2024–2026) agar tidak menampilkan
     * tahun-tahun lama yang sudah lewat, atau dapat menampilkan film terbaik sepanjang
     * masa bila $category bernilai 'top_rated'.
     */
    protected function fetchNowPlaying(?string $studio, int $limit = 16, string $category = 'now_playing'): array
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

        $yearFilter = ($category === 'top_rated')
            ? ''
            : 'FILTER(YEAR(?tahun) >= 2024 && YEAR(?tahun) <= 2026)';

        $orderClause = ($category === 'top_rated')
            ? 'ORDER BY DESC(?rating)'
            : 'ORDER BY DESC(?rating) DESC(?tahun)';

        $query = <<<SPARQL
            PREFIX schema: <https://schema.org/>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?film ?nama ?tahun ?rating ?durasi ?tmdbId
                   (SAMPLE(?posterRdf) AS ?poster)
                   (SAMPLE(?sutradaraNama) AS ?sutradara)
                   (GROUP_CONCAT(DISTINCT ?genreLabel; separator=", ") AS ?genres) WHERE {
                ?film a schema:Movie ;
                      schema:name ?nama ;
                      schema:datePublished ?tahun .
                OPTIONAL { ?film film:tmdbRating ?rating . }
                OPTIONAL { ?film film:tmdbId ?tmdbId . }
                OPTIONAL { ?film schema:duration ?durasi . }
                OPTIONAL { ?film schema:image ?posterRdf . }
                OPTIONAL { ?film schema:genre ?genreRes . ?genreRes rdfs:label ?genreLabel . }
                OPTIONAL { ?film schema:director ?sutradaraRes . ?sutradaraRes schema:name ?sutradaraNama . }
                $studioFilter
                $yearFilter
            }
            GROUP BY ?film ?nama ?tahun ?rating ?durasi ?tmdbId
            $orderClause
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
                    'posterRdf' => isset($row->posterRdf) ? (string) $row->posterRdf : null,
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

            // Ambil poster dan studio dari database, fallback ke poster RDF bila ada
            $posters = MoviePoster::getPosters($filmIds);
            $posterModels = MoviePoster::whereIn('film_id', $filmIds)->get()->keyBy('film_id');
            foreach ($films as &$f) {
                $f['poster'] = $posters[$f['id']] ?? $f['posterRdf'] ?? null;
                $f['studio'] = $posterModels[$f['id']]->studio ?? null;
                unset($f['posterRdf']);
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
            $data = ($resp instanceof Response && $resp->successful())
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
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?person ?nama ?peran (SAMPLE(?fotoRaw) AS ?foto) (GROUP_CONCAT(DISTINCT ?charName; separator=", ") AS ?karakter) WHERE {
                {
                    <$filmUri> schema:director ?person .
                    BIND("Sutradara" AS ?peran)
                }
                UNION
                {
                    <$filmUri> schema:actor ?person .
                    BIND("Aktor" AS ?peran)
                    OPTIONAL {
                        <$filmUri> film:performanceRole ?role .
                        ?role schema:actor ?person ;
                              schema:characterName ?charName .
                    }
                }
                ?person schema:name ?nama .
                OPTIONAL { ?person schema:image ?fotoRaw . }
            }
            GROUP BY ?person ?nama ?peran
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
                    'karakter' => isset($row->karakter) && (string) $row->karakter !== '' ? (string) $row->karakter : null,
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
            PREFIX film: <http://pilem.org/cinegraph/ontology#>
            SELECT ?film ?namaFilm ?tahun ?peran (SAMPLE(?posterRaw) AS ?posterRdf) (GROUP_CONCAT(DISTINCT ?charName; separator=", ") AS ?karakter) WHERE {
                {
                    ?film schema:director <$personUri> .
                    BIND("Sutradara" AS ?peran)
                }
                UNION
                {
                    ?film schema:actor <$personUri> .
                    BIND("Aktor" AS ?peran)
                    OPTIONAL {
                        ?film film:performanceRole ?role .
                        ?role schema:actor <$personUri> ;
                              schema:characterName ?charName .
                    }
                }
                ?film schema:name ?namaFilm .
                OPTIONAL { ?film schema:datePublished ?tahun . }
                OPTIONAL { ?film schema:image ?posterRaw . }
            }
            GROUP BY ?film ?namaFilm ?tahun ?peran
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
                    'posterRdf' => isset($row->posterRdf) ? (string) $row->posterRdf : null,
                    'peran' => (string) $row->peran,
                    'karakter' => isset($row->karakter) && (string) $row->karakter !== '' ? (string) $row->karakter : null,
                ];
            }

            $posters = MoviePoster::getPosters($filmIds);
            foreach ($filmografi as &$item) {
                $item['poster'] = $posters[$item['id']] ?? $item['posterRdf'] ?? null;
                unset($item['posterRdf']);
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
     * Ambil atau gunakan cache token akses Spotify Web API (Client Credentials Flow).
     */
    protected function getSpotifyAccessToken(): ?string
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');
        if (! $clientId || ! $clientSecret) {
            return null;
        }

        return Cache::remember('spotify_access_token_v2', 3500, function () use ($clientId, $clientSecret) {
            try {
                $res = Http::withOptions(['force_ip_resolve' => 'v4'])
                    ->timeout(4)
                    ->asForm()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->post('https://accounts.spotify.com/api/token', [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($res->successful()) {
                    return $res->json('access_token');
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil Spotify token: '.$e->getMessage());
            }

            return null;
        });
    }

    /**
     * Ambil data dari 4 layanan eksternal secara paralel. Kalau salah satu
     * gagal/timeout, bagian itu jadi null -- tidak menggagalkan halaman.
     */
    protected function fetchExternalServices(array $film): array
    {
        $tmdbKey = config('services.tmdb.key');
        $youtubeKey = config('services.youtube.key');
        $spotifyToken = $this->getSpotifyAccessToken();

        try {
            $responses = Http::pool(function (Pool $pool) use ($film, $tmdbKey, $spotifyToken) {
                $requests = [];

                // Soundtrack resmi via Spotify Web API: cari lagu berdasarkan judul album resmi film
                if ($spotifyToken) {
                    $cleanFilmName = trim(str_replace('"', '', $film['nama']));
                    $requests[] = $pool->as('soundtrack_spotify')
                        ->withOptions(['force_ip_resolve' => 'v4'])
                        ->withToken($spotifyToken)
                        ->get('https://api.spotify.com/v1/search', [
                            'q' => 'album:"'.$cleanFilmName.'" soundtrack',
                            'type' => 'track',
                            'limit' => 10,
                        ]);
                }

                // OST fallback lewat iTunes Search API jika Spotify belum tersedia
                $requests[] = $pool->as('soundtrack_itunes')->get('https://itunes.apple.com/search', [
                    'term' => $film['nama'],
                    'media' => 'music',
                    'limit' => 6,
                ]);

                if ($film['tmdbId'] && $tmdbKey) {
                    // Trailer YouTube resmi dari TMDB (hanya untuk cuplikan film)
                    $requests[] = $pool->as('trailer')->get(
                        "https://api.themoviedb.org/3/movie/{$film['tmdbId']}/videos",
                        ['api_key' => $tmdbKey]
                    );

                    $requests[] = $pool->as('reviews')->get(
                        "https://api.themoviedb.org/3/movie/{$film['tmdbId']}/reviews",
                        ['api_key' => $tmdbKey]
                    );

                    // Data streaming JustWatch lewat TMDB
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

        $exchangeRate = (isset($responses['exchange']) && $responses['exchange'] instanceof Response && $responses['exchange']->successful())
            ? $responses['exchange']->json('rates.IDR')
            : null;

        $isOk = static fn ($r): bool => $r instanceof Response && $r->successful();

        // Olah data soundtrack: Murni menggunakan Spotify Web API
        $soundtrack = [];
        $soundtrackSource = 'spotify';
        $itunesResults = $isOk($responses['soundtrack_itunes'] ?? null)
            ? ($responses['soundtrack_itunes']->json('results') ?? [])
            : [];

        // 1. Ambil lagu dari Spotify Web API
        if ($spotifyToken && $isOk($responses['soundtrack_spotify'] ?? null)) {
            $spItems = $responses['soundtrack_spotify']->json('tracks.items') ?? [];

            // Fallback 1: Jika kurang dari 2 lagu dengan query album soundtrack, cari berdasarkan judul album film
            if (count($spItems) < 2) {
                try {
                    $cleanFilmName = trim(str_replace('"', '', $film['nama']));
                    $fallbackRes = Http::withOptions(['force_ip_resolve' => 'v4'])
                        ->withToken($spotifyToken)
                        ->timeout(3)
                        ->get('https://api.spotify.com/v1/search', [
                            'q' => 'album:"'.$cleanFilmName.'"',
                            'type' => 'track',
                            'limit' => 10,
                        ]);
                    if ($fallbackRes->successful() && ! empty($fallbackRes->json('tracks.items'))) {
                        $spItems = $fallbackRes->json('tracks.items');
                    }
                } catch (\Throwable $e) {
                    // Abaikan fallback error
                }
            }

            // Fallback 2: Jika masih kosong, cari kata kunci soundtrack film umum di Spotify
            if (empty($spItems)) {
                try {
                    $cleanFilmName = trim(str_replace('"', '', $film['nama']));
                    $fallbackRes2 = Http::withOptions(['force_ip_resolve' => 'v4'])
                        ->withToken($spotifyToken)
                        ->timeout(3)
                        ->get('https://api.spotify.com/v1/search', [
                            'q' => '"'.$cleanFilmName.'" soundtrack',
                            'type' => 'track',
                            'limit' => 10,
                        ]);
                    if ($fallbackRes2->successful() && ! empty($fallbackRes2->json('tracks.items'))) {
                        $spItems = $fallbackRes2->json('tracks.items');
                    }
                } catch (\Throwable $e) {
                    // Abaikan fallback error
                }
            }

            if (! empty($spItems)) {
                $seenTitles = [];
                foreach ($spItems as $item) {
                    $trackId = $item['id'] ?? null;
                    if (! $trackId) {
                        continue;
                    }

                    $rawTitle = $item['name'] ?? '';
                    $cleanTitle = preg_replace('/\s*-\s*From\s+.*$/i', '', $rawTitle);
                    $cleanTitle = preg_replace('/\s*-\s*Soundtrack\s+.*$/i', '', $cleanTitle);
                    $cleanTitle = preg_replace('/\s*-\s*Remastered\s*.*$/i', '', $cleanTitle);
                    $cleanTitle = preg_replace('/\s*[\(\[][^\)\]]*(from|soundtrack|ost|score|version|remastered|official|audio|video|original motion picture)[^\)\]]*[\)\]]/i', '', $cleanTitle);
                    $cleanTitle = trim($cleanTitle, " \t\n\r\0\x0B\"'-/");

                    $finalTitle = $cleanTitle ?: $rawTitle;
                    $canonical = $this->getCanonicalTrackTitle($finalTitle);

                    if (isset($seenTitles[$canonical]) || strlen($canonical) < 2) {
                        continue;
                    }

                    $artists = implode(', ', array_column($item['artists'] ?? [], 'name'));

                    // Abaikan channel/album anak-anak yang tidak relevan (misal CoComelon untuk pencarian "Coco")
                    $albumName = $item['album']['name'] ?? '';
                    if (str_contains(strtolower($artists), 'cocomelon') || str_contains(strtolower($albumName), 'cocomelon')) {
                        continue;
                    }

                    // Filter relevansi: pastikan lagu terkait dengan film atau album soundtrack
                    $words = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $film['nama']))), fn ($w) => strlen($w) >= 4);
                    $haystack = strtolower($rawTitle.' '.$albumName.' '.$artists);
                    $isRelevant = empty($words);
                    foreach ($words as $w) {
                        if (str_contains($haystack, $w)) {
                            $isRelevant = true;
                            break;
                        }
                    }
                    if (! $isRelevant && ! str_contains(strtolower($albumName), 'soundtrack') && ! str_contains(strtolower($albumName), 'disney')) {
                        continue;
                    }

                    $seenTitles[$canonical] = true;

                    $art = $item['album']['images'][0]['url'] ?? null;
                    $finalArtist = $artists ?: 'Official Artist';

                    $soundtrack[] = [
                        'source' => 'spotify',
                        'trackId' => $trackId,
                        'trackName' => $finalTitle,
                        'artistName' => $finalArtist,
                        'artworkUrl100' => $art,
                        'previewUrl' => $item['preview_url'] ?? null,
                        'spotifyUrl' => $item['external_urls']['spotify'] ?? ('https://open.spotify.com/track/'.$trackId),
                        'embedUrl' => 'https://open.spotify.com/embed/track/'.$trackId.'?utm_source=generator&theme=0',
                    ];

                    if (count($soundtrack) >= 6) {
                        break;
                    }
                }
            }
        }

        // 2. Fallback darurat jika Spotify kosong
        if (empty($soundtrack) && ! empty($itunesResults)) {
            $soundtrackSource = 'itunes';
            $seenItunes = [];
            foreach ($itunesResults as $it) {
                $tName = $it['trackName'] ?? '';
                $aName = $it['artistName'] ?? '';
                if ($tName === '') {
                    continue;
                }

                $canonical = $this->getCanonicalTrackTitle($tName);
                if (isset($seenItunes[$canonical]) || strlen($canonical) < 2) {
                    continue;
                }
                $seenItunes[$canonical] = true;

                $soundtrack[] = [
                    'source' => 'itunes',
                    'trackName' => $tName,
                    'artistName' => $aName,
                    'artworkUrl100' => $it['artworkUrl100'] ?? null,
                    'previewUrl' => $it['previewUrl'] ?? null,
                    'spotifyUrl' => 'https://open.spotify.com/search/'.urlencode($tName.' '.$aName),
                    'embedUrl' => null,
                ];

                if (count($soundtrack) >= 6) {
                    break;
                }
            }
        }

        return [
            'soundtrack' => $soundtrack,
            'soundtrackSource' => $soundtrackSource,
            'trailerKey' => $isOk($responses['trailer'] ?? null)
                ? $this->extractYoutubeTrailerKey($responses['trailer']->json('results') ?? [])
                : null,
            'streaming' => $isOk($responses['streaming'] ?? null)
                ? ($responses['streaming']->json('results.ID') ?? $responses['streaming']->json('results.US'))
                : null,
            'reviews' => $isOk($responses['reviews'] ?? null)
                ? $responses['reviews']->json('results')
                : null,
            'boxOfficeIDR' => ($film['boxOfficeUSD'] && $exchangeRate)
                ? round($film['boxOfficeUSD'] * $exchangeRate)
                : null,
            // Rate mentah + waktu update dari ExchangeRate API sendiri
            // (BUKAN waktu request kita), supaya keterangan "per [timestamp]"
            // di FR-6 akurat -- bukan cuma tampilkan waktu server kita.
            'exchangeRate' => $exchangeRate,
            'exchangeRateAt' => $isOk($responses['exchange'] ?? null)
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
     * Dapatkan judul kanonikal lagu untuk mencegah judul ganda atau variasi duplikat
     * (misal: "Remember Me (Lullaby)" & "Remember Me (Dúo)", atau "A Whole New World (End Title)").
     */
    protected function getCanonicalTrackTitle(string $rawTitle): string
    {
        // 1. Bersihkan semua kurung / siku di dalam atau akhir judul
        $clean = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $rawTitle);

        // 2. Bersihkan embel-embel strip
        $clean = preg_replace('/\s*-\s*(from|soundtrack|ost|score|reprise|version|live|remastered|single|end title|finale|instrumental|part).*$/i', '', $clean);

        // 3. Trim simbol dan spasi
        $clean = trim($clean, " \t\n\r\0\x0B\"'-/");

        // 4. Huruf kecil dan alfanumerik saja
        $canonical = preg_replace('/[^a-z0-9]/', '', strtolower($clean));

        return strlen($canonical) >= 2 ? $canonical : preg_replace('/[^a-z0-9]/', '', strtolower($rawTitle));
    }

    /**
     * Bersihkan judul lagu dan nama artis dari metadata YouTube.
     * Mengeliminasi tag tambahan seperti (From "...", Official Audio, OST, - Topic, VEVO, dll).
     */
    protected function cleanTrackTitleAndArtist(string $rawTitle, string $rawChannel): array
    {
        $rawTitle = html_entity_decode($rawTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawChannel = html_entity_decode($rawChannel, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Hilangkan tag kurung seperti (From "...", Official Audio, Soundtrack Version, Lirik, dll)
        $clean = preg_replace('/\s*[\(\[][^\)\]]*(from|soundtrack|ost|score|lyric|lyrics|official|audio|video|remastered|theme|version|mashup|edition|sub|español|lirik|terjemahan|movie|film)[^\)\]]*[\)\]]/i', '', $rawTitle);
        $clean = preg_replace('/\s*[\(\[]\s*HD\s*[\)\]]/i', '', $clean);
        $clean = preg_replace('/\s*-\s*(lirik|terjemahan|lyrics?).*$/i', '', $clean);
        $clean = preg_replace('/\b(lyrics?|lirik(\s+dan\s+terjemahan)?|terjemahan|official\s+video|official\s+audio)\b/i', '', $clean);
        $clean = preg_replace('/\s*\b(ost|soundtrack)\s+.*$/i', '', $clean);

        if (str_contains($clean, ' | ')) {
            $parts = explode(' | ', $clean, 2);
            $clean = $parts[0];
        }

        $clean = trim($clean);

        $cleanChannel = preg_replace('/\s*-\s*Topic$/i', '', $rawChannel);
        $cleanChannel = preg_replace('/VEVO$/i', '', $cleanChannel);
        $cleanChannel = trim($cleanChannel);

        if (str_contains($clean, ' - ')) {
            $parts = explode(' - ', $clean, 2);
            $artist = trim($parts[0]);
            $title = trim($parts[1]);
        } elseif (str_contains($clean, ': ')) {
            $parts = explode(': ', $clean, 2);
            $artist = trim($parts[0]);
            $title = trim($parts[1]);
        } else {
            $title = $clean;
            $artist = $cleanChannel;
        }

        $title = trim($title, " \t\n\r\0\x0B\"'-");
        $artist = trim($artist, " \t\n\r\0\x0B\"'-");

        return [
            'title' => $title ?: $rawTitle,
            'artist' => $artist ?: 'Official Artist',
        ];
    }

    /**
     * Cari YouTube video ID untuk sebuah query pencarian OST/soundtrack (cache 7 hari).
     * Mendukung YouTube Data API v3 dan fallback search scraping.
     */
    protected function resolveYoutubeVideoId(string $query): ?string
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $cacheKey = 'yt_vid_v4_'.md5(strtolower($query));

        return Cache::remember($cacheKey, 86400 * 7, function () use ($query) {
            $youtubeKey = config('services.youtube.key');
            if ($youtubeKey) {
                try {
                    $res = Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/search', [
                        'part' => 'snippet',
                        'q' => $query,
                        'type' => 'video',
                        'videoCategoryId' => '10',
                        'videoEmbeddable' => 'true',
                        'videoSyndicated' => 'true',
                        'maxResults' => 1,
                        'key' => $youtubeKey,
                    ]);
                    if ($res->successful()) {
                        $vid = $res->json('items.0.id.videoId');
                        if ($vid) {
                            return $vid;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Gagal resolve videoId via YouTube API: '.$e->getMessage());
                }
            }

            // Fallback scraping jika tanpa API key atau kuota habis
            try {
                $res = Http::timeout(3)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])->get('https://www.youtube.com/results', ['search_query' => $query]);

                if ($res->successful() && preg_match('/"videoId":"([a-zA-Z0-9_-]{11})"/', $res->body(), $m)) {
                    return $m[1];
                }
            } catch (\Throwable $e) {
                // Abaikan error scraping
            }

            return null;
        });
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
     * Susun klausa ORDER BY dengan pembobotan relevansi semantik saat ada keyword.
     */
    protected function buildOrderClause(string $keyword, string $urutanTahun, string $rating, string $sort): string
    {
        if ($urutanTahun === 'year_desc') {
            return 'ORDER BY DESC(?tahun) '.($sort === 'title_desc' ? 'DESC(?nama)' : '?nama');
        }
        if ($urutanTahun === 'year_asc') {
            return 'ORDER BY ASC(?tahun) '.($sort === 'title_desc' ? 'DESC(?nama)' : '?nama');
        }
        if ($rating === 'rating_desc' || in_array($rating, ['8', '7', '6', '5'])) {
            return 'ORDER BY DESC(?rating) '.($sort === 'title_desc' ? 'DESC(?nama)' : '?nama');
        }
        if ($rating === 'rating_asc') {
            return 'ORDER BY ASC(?rating) '.($sort === 'title_desc' ? 'DESC(?nama)' : '?nama');
        }
        if ($sort === 'title_desc') {
            return 'ORDER BY DESC(?nama)';
        }

        if ($keyword !== '') {
            $escKeyword = $this->escape(mb_strtolower(trim($keyword)));
            $cleanTokens = array_values(array_filter(explode(' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $escKeyword))));
            $firstToken = $cleanTokens[0] ?? $escKeyword;

            $relevanceOrders = [
                'DESC(STRSTARTS(LCASE(?nama), "'.$escKeyword.'"))',
                'DESC(CONTAINS(LCASE(?nama), "'.$escKeyword.'"))',
            ];

            if ($firstToken !== $escKeyword && $firstToken !== '') {
                $relevanceOrders[] = 'DESC(STRSTARTS(LCASE(?nama), "'.$firstToken.'"))';
                $relevanceOrders[] = 'DESC(CONTAINS(LCASE(?nama), "'.$firstToken.'"))';
            }

            $relevanceOrders[] = '?nama';

            return 'ORDER BY '.implode(' ', $relevanceOrders);
        }

        return 'ORDER BY ?nama';
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
