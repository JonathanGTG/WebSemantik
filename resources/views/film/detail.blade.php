{{--
    Halaman detail film -- extend layouts.app (tema gelap terpusat).
    RDFa (vocab/typeof/property) dan Open Graph DIPERTAHANKAN -- syarat
    wajib dokumen tugas Web Semantik.
--}}
@extends('layouts.app')

@section('title', $film['nama'] . ' — pilem.')

@push('meta')
    <meta property="og:title" content="{{ $film['nama'] }}">
    <meta property="og:type" content="video.movie">
    @if (pilemPoster($film['id'], $film['poster']))
        <meta property="og:image" content="{{ pilemPoster($film['id'], $film['poster']) }}">
    @endif
    <meta property="og:description" content="{{ Str::limit($film['sinopsis'] ?? ('Detail film ' . $film['nama']), 200) }}">
    <meta property="og:url" content="{{ url()->current() }}">
@endpush

@push('styles')
<style>
    .detail-hero { padding: 2rem 0 1.5rem; }
    .genre-tag {
        display: inline-block; font-size: .72rem; font-weight: 700;
        padding: .25rem .65rem; border-radius: 6px; background: rgba(39,196,232,.14); border: 1px solid rgba(39,196,232,.45);
        color: var(--text); text-decoration: none; transition: background .15s;
    }
    .genre-tag:hover { background: var(--gold); border-color: var(--gold); color: #1a1200; }
    .meta-row { display: flex; flex-wrap: wrap; gap: .3rem .8rem; font-size: .9rem; color: rgba(255,255,255,0.6); margin-bottom: 1rem; }
    .meta-row strong { color: #fff; }
    .meta-row .sep { color: rgba(255,255,255,0.2); }
    .section-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sand); margin-bottom: .75rem; }
    .cast-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); gap: 1.1rem .75rem; }
    .cast-card {
        text-align: center; text-decoration: none; color: inherit;
        display: flex; flex-direction: column; align-items: center; gap: .4rem;
    }
    .cast-card img, .cast-card .cast-placeholder {
        width: 76px; height: 76px; border-radius: 50%; object-fit: cover;
        border: 2px solid transparent; transition: border-color .15s, transform .15s;
    }
    .cast-card .cast-placeholder {
        background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; font-weight: 700; color: var(--text-dim);
    }
    .cast-card:hover img, .cast-card:hover .cast-placeholder { border-color: var(--cyan); transform: scale(1.05); }
    .cast-name { font-size: .8rem; font-weight: 700; line-height: 1.25; word-break: break-word; }
    .cast-role-badge {
        font-size: .68rem; padding: .22rem .6rem; border-radius: 10px; line-height: 1.35;
        background: rgba(255,255,255,0.12); font-weight: 600; max-width: 100%;
        white-space: normal; word-break: break-word; text-align: center;
    }
    .cast-role-badge.sutradara { background: rgba(39,196,232,.18); color: var(--cyan); border: 1px solid rgba(39,196,232,.35); }
    .cast-role-badge.pemeran { background: rgba(247,185,40,.15); color: var(--gold-soft); border: 1px solid rgba(247,185,40,.35); }

    /* ---------- Clean 2-Column Soundtrack Grid ---------- */
    .ost-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    @media (max-width: 767.98px) {
        .ost-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .spotify-track-wrap {
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 3px;
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        overflow: hidden;
    }
    .spotify-track-wrap:hover {
        border-color: #1DB954;
        box-shadow: 0 4px 18px rgba(29, 185, 84, 0.22);
    }

    .ost-track-card {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.09);
        border-radius: 14px;
        padding: 1rem 1.15rem;
        transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: .75rem;
        min-width: 0;
    }
    .ost-track-card:hover {
        border-color: rgba(29, 185, 84, 0.5);
        background: rgba(255, 255, 255, 0.06);
        box-shadow: 0 6px 20px rgba(29, 185, 84, 0.12);
    }

    .ost-track-title {
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.35;
        color: #fff;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ost-track-artist {
        font-size: .8rem;
        color: var(--gold-soft);
        line-height: 1.3;
        margin-top: .15rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ost-spotify-frame {
        border-radius: 10px;
        border: 0;
        width: 100%;
        height: 80px;
        display: block;
    }

    .btn-spotify-connect {
        background: #1DB954;
        color: #000;
        font-weight: 700;
        font-size: .78rem;
        border: none;
        border-radius: 999px;
        padding: .4rem .95rem;
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        text-decoration: none;
        transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
        box-shadow: 0 4px 14px rgba(29, 185, 84, 0.35);
    }
    .btn-spotify-connect:hover {
        background: #1ed760;
        color: #000;
        transform: scale(1.04);
        box-shadow: 0 6px 18px rgba(29, 185, 84, 0.45);
    }

    .sp-play-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #1DB954;
        border: none;
        color: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(29, 185, 84, 0.35);
        transition: transform .15s, background .15s;
    }
    .sp-play-btn:hover {
        transform: scale(1.08);
        background: #1ed760;
        color: #000;
    }
    .sp-seek-lane {
        width: 100%;
        height: 6px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.12);
        cursor: pointer;
        overflow: hidden;
        position: relative;
    }
    .sp-seek-fill {
        height: 100%;
        width: 0%;
        background: #1DB954;
        border-radius: 6px;
        transition: width .15s linear;
    }

    .info-tiles { display: flex; flex-wrap: wrap; gap: .9rem; align-items: stretch; margin-top: 1.5rem; }
    .info-tile {
        background: rgba(255,255,255,.04); border: 1px solid var(--line); border-radius: 14px;
        padding: .8rem 1.1rem; display: flex; flex-direction: column; gap: .5rem; min-width: 0;
    }
    .tile-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sand); }
    .tile-rating-val { font-size: 2rem; font-weight: 800; line-height: 1; color: var(--gold-soft); }
    .tile-rating-val small { font-size: .9rem; font-weight: 600; color: rgba(255,255,255,.5); }
    .tile-stream { flex: 1 1 300px; }
    .stream-groups { display: flex; flex-wrap: wrap; gap: .9rem 1.6rem; }
    .stream-cat { font-size: .72rem; font-weight: 700; color: rgba(255,255,255,.55); margin-bottom: .4rem; }
    .stream-list { display: flex; flex-wrap: wrap; gap: .6rem; }
    .stream-item { display: flex; flex-direction: column; align-items: center; gap: .3rem; text-decoration: none; width: 64px; }
    .stream-item .provider-logo { width: 46px; height: 46px; border-radius: 10px; }
    .stream-item span { font-size: .62rem; color: rgba(255,255,255,.6); text-align: center; line-height: 1.2; word-break: break-word; }
    .review-card {
        background: rgba(255,255,255,0.04); border-left: 3px solid var(--sky);
        border-radius: 0 10px 10px 0; padding: .8rem 1rem;
    }
    .sejenis-card .sejenis-title { font-size: .78rem; font-weight: 600; }
    .sejenis-card .sejenis-year { font-size: .68rem; color: rgba(255,255,255,0.5); }
    .data-source-note { font-size: .7rem; color: rgba(255,255,255,0.35); }
</style>
@endpush

@section('content')
<div vocab="https://schema.org/" typeof="Movie">

    {{-- ======================= BREADCRUMB ======================= --}}
    <nav class="mb-3" style="font-size:.82rem; color: rgba(255,255,255,0.45);">
        <a href="{{ route('home') }}" class="text-decoration-none text-white-50">Now Playing</a>
        <span class="mx-1">›</span>
        <a href="{{ route('film.search') }}" class="text-decoration-none text-white-50">Cari Film</a>
        <span class="mx-1">›</span>
        <span class="text-white">{{ Str::limit($film['nama'], 40) }}</span>
    </nav>

    {{-- ======================= HEADER FILM ======================= --}}
    <div class="row g-4 detail-hero">

        {{-- Poster --}}
        <div class="col-md-3 col-lg-2">
            @if (pilemPoster($film['id'], $film['poster']))
                <div class="position-relative">
                    <img src="{{ pilemPoster($film['id'], $film['poster']) }}" property="image"
                         class="img-fluid rounded-3 shadow w-100" alt="{{ $film['nama'] }}"
                         style="aspect-ratio:2/3; object-fit:cover;">
                    @if (!empty($film['rating']))
                        <span class="rating-badge position-absolute top-0 end-0 m-2">
                             &#9733; {{ number_format($film['rating'], 1) }}/10
                        </span>
                    @endif
                </div>
            @else
                <div class="position-relative d-flex align-items-center justify-content-center rounded-3 shadow w-100 bg-dark text-white-50"
                     style="aspect-ratio:2/3; font-size:3rem; border:1px solid rgba(255,255,255,0.1);">
                    
                </div>
            @endif
        </div>

        {{-- Info Utama --}}
        <div class="col-md-9 col-lg-10">
            @if ($film['genre'] || $film['studio'])
                <div class="text-uppercase small fw-bold mb-2 text-sand" style="letter-spacing:.05em;">
                    {{ $film['studio'] ? strtoupper(explode(',', $film['studio'])[0]) : 'Film' }}
                </div>
            @endif

            <h1 property="name" class="fw-bold display-6 mb-2">{{ $film['nama'] }}</h1>

            @if (!empty($film['tagline']))
                <p class="text-white-50 fst-italic mb-3">&ldquo;{{ $film['tagline'] }}&rdquo;</p>
            @endif

            {{-- Meta row --}}
            <div class="meta-row">
                @if ($film['tahun'])
                    <span><strong property="datePublished">{{ substr($film['tahun'], 0, 4) }}</strong></span>
                    <span class="sep">·</span>
                @endif
                @if (!empty($film['rated']))
                    <span class="badge border border-secondary text-light px-2 py-1" style="font-size:.72rem;">{{ $film['rated'] }}</span>
                    <span class="sep">·</span>
                @endif
                @if ($film['durasi'])
                    <span property="duration">{{ pilemFormatDurasi($film['durasi']) }}</span>
                    <span class="sep">·</span>
                @elseif (!empty($film['runtimeMinutes']))
                    <span>{{ $film['runtimeMinutes'] }} mnt</span>
                    <span class="sep">·</span>
                @endif
                @if ($film['negara'])
                    <span property="countryOfOrigin">{{ $film['negara'] }}</span>
                @endif
                @if ($film['komposer'])
                    <span class="sep">·</span>
                    <span> <span property="musicBy">{{ $film['komposer'] }}</span></span>
                @endif
            </div>

            {{-- Sutradara --}}
            @php
                $sutradaraList = collect($film['castCrew'] ?? [])->where('peran', 'Sutradara');
            @endphp
            @if ($sutradaraList->isNotEmpty())
                <div class="mb-2" style="font-size:.9rem;">
                    <span class="text-white-50">Sutradara: </span>
                    @foreach ($sutradaraList as $s)
                        <a href="{{ route('person.detail', $s['id']) }}"
                           class="text-sand text-decoration-none fw-semibold" property="director">
                            {{ $s['nama'] }}
                        </a>{{ !$loop->last ? ', ' : '' }}
                    @endforeach
                </div>
            @endif

            {{-- Genre tags --}}
            @if ($film['genre'])
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @foreach (explode(',', $film['genre']) as $g)
                        <a href="{{ route('film.search', ['genre' => trim($g)]) }}"
                           class="genre-tag" property="genre">{{ trim($g) }}</a>
                    @endforeach
                </div>
            @endif

            {{-- CTA buttons --}}
            <div class="d-flex gap-2 flex-wrap">
                @if (!empty($film['externalData']['trailerKey']))
                    <a href="#trailer" class="btn btn-sun px-4">Tonton Trailer</a>
                @endif
                <a href="#soundtrack" class="btn btn-outline-pilem px-4"> Dengar OST</a>
                @if (!empty($film['externalData']['streaming']))
                    <a href="#streaming" class="btn btn-outline-pilem px-4"> Streaming</a>
                @endif
            </div>

            {{-- Info tiles: Rating, Streaming, Box Office --}}
            @php
                $streaming     = $film['externalData']['streaming'] ?? null;
                $streamingLink = $streaming['link'] ?? null;
                $kategoriLabel = ['flatrate' => 'Langganan', 'rent' => 'Sewa', 'buy' => 'Beli'];
                $adaProvider   = $streaming && (!empty($streaming['flatrate']) || !empty($streaming['rent']) || !empty($streaming['buy']));
            @endphp
            <div class="info-tiles">
                @if (!empty($film['rating']))
                    <div class="info-tile">
                        <div class="tile-label">Rating TMDB</div>
                        <div class="tile-rating-val">{{ number_format($film['rating'], 1) }} <small>/ 10</small></div>
                    </div>
                @endif

                <div class="info-tile tile-stream" id="streaming">
                    <div class="tile-label">Streaming (Indonesia)</div>
                    @if ($adaProvider)
                        <div class="stream-groups">
                            @foreach ($kategoriLabel as $kategori => $label)
                                @if (!empty($streaming[$kategori]))
                                    <div>
                                        <div class="stream-cat">{{ $label }}</div>
                                        <div class="stream-list">
                                            @foreach ($streaming[$kategori] as $provider)
                                                <a href="{{ $streamingLink ?? '#' }}" target="_blank" rel="noopener"
                                                   title="{{ $provider['provider_name'] }}" class="stream-item">
                                                    <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}"
                                                         alt="{{ $provider['provider_name'] }}" class="provider-logo">
                                                    <span>{{ $provider['provider_name'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="text-white-50 small">Belum tersedia data streaming untuk wilayah Indonesia.</div>
                    @endif
                </div>

                @if ($film['boxOfficeUSD'])
                    <div class="info-tile">
                        <div class="tile-label">Box Office</div>
                        <div class="fw-bold">${{ number_format($film['boxOfficeUSD'], 0, ',', '.') }} USD</div>
                        @if (!empty($film['externalData']['boxOfficeIDR']))
                            <div class="text-sand small fw-semibold">Rp{{ number_format($film['externalData']['boxOfficeIDR'], 0, ',', '.') }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ======================= SINOPSIS ======================= --}}
    @if ($film['sinopsis'])
        <hr class="pilem-hr my-4">
        <div class="section-label">Sinopsis</div>
        <p property="description" class="text-white-50" style="max-width:800px; line-height:1.75;">
            {{ $film['sinopsis'] }}
        </p>
    @endif

    {{-- ======================= SUTRADARA & PEMERAN ======================= --}}
    @php
        $sutradaraList = collect($film['castCrew'] ?? [])->where('peran', 'Sutradara');
        $aktorList = collect($film['castCrew'] ?? [])->where('peran', 'Aktor');
    @endphp

    @if ($sutradaraList->isNotEmpty() || $aktorList->isNotEmpty())
        <hr class="pilem-hr my-4">

        {{-- Baris 1: Sutradara --}}
        @if ($sutradaraList->isNotEmpty())
            <div class="mb-4">
                <div class="section-label"> Sutradara</div>
                <div class="cast-grid">
                    @foreach ($sutradaraList as $s)
                        <a href="{{ route('person.detail', $s['id']) }}"
                           class="cast-card"
                           property="director">
                            @if ($s['foto'])
                                <img src="{{ $s['foto'] }}" alt="{{ $s['nama'] }}">
                            @else
                                <div class="cast-placeholder">{{ mb_strtoupper(mb_substr($s['nama'], 0, 1)) }}</div>
                            @endif
                            <div class="cast-name">{{ $s['nama'] }}</div>
                            <span class="cast-role-badge sutradara">Sutradara</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Baris 2: Pemeran & Karakter --}}
        @if ($aktorList->isNotEmpty())
            <div>
                <div class="section-label"> Pemeran &amp; Karakter</div>
                <div class="cast-grid">
                    @foreach ($aktorList as $a)
                        <a href="{{ route('person.detail', $a['id']) }}"
                           class="cast-card"
                           property="actor">
                            @if ($a['foto'])
                                <img src="{{ $a['foto'] }}" alt="{{ $a['nama'] }}">
                            @else
                                <div class="cast-placeholder">{{ mb_strtoupper(mb_substr($a['nama'], 0, 1)) }}</div>
                            @endif
                            <div class="cast-name">{{ $a['nama'] }}</div>
                            @if (!empty($a['karakter']))
                                <span class="cast-role-badge pemeran" title="{{ $a['nama'] }} sebagai {{ $a['karakter'] }}">
                                    {{ $a['karakter'] }}
                                </span>
                            @else
                                <span class="cast-role-badge pemeran">Pemeran</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- ======================= TRAILER ======================= --}}
    @if (!empty($film['externalData']['trailerKey']))
        <hr class="pilem-hr my-4">
        <div class="section-label" id="trailer"> Trailer Resmi</div>
        <div class="ratio ratio-16x9 rounded-4 overflow-hidden" style="max-width: 860px;">
            <iframe src="https://www.youtube.com/embed/{{ $film['externalData']['trailerKey'] }}"
                    title="Trailer {{ $film['nama'] }}" allowfullscreen style="border:0;"></iframe>
        </div>
    @endif

    {{-- ======================= SOUNDTRACK ======================= --}}
    <hr class="pilem-hr my-4">
    <div id="soundtrack">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="section-label mb-0">
                    Soundtrack &amp; Musik Tema
                </div>
                <div class="text-dim small mt-1">Daftar soundtrack resmi film via Spotify.</div>
            </div>

            @if (session('spotify_user_access_token'))
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 px-2.5 py-1.5 d-inline-flex align-items-center gap-1.5" style="font-size:0.75rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                        <span>Tersambung: <strong>{{ session('spotify_user_name', 'Spotify') }}</strong> ({{ ucfirst(session('spotify_user_product', 'Premium')) }})</span>
                    </span>
                    <a href="{{ route('spotify.logout') }}" class="btn btn-outline-danger btn-sm px-2 py-0 fw-semibold text-decoration-none" style="font-size:0.7rem;" title="Putuskan koneksi Spotify">
                        Putus
                    </a>
                </div>
            @else
                <a href="{{ route('spotify.login', ['return_url' => url()->current()]) }}" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1.5 px-2.5 py-1 text-decoration-none" style="font-size:0.78rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                    <span>Hubungkan Spotify</span>
                </a>
            @endif
        </div>
8
        @php
            $rawOstList = array_values(array_filter($film['externalData']['soundtrack'] ?? [], fn ($t) => !empty($t['trackName'])));
            $ostList = [];
            $seenBladeTitles = [];
            foreach ($rawOstList as $trk) {
                $cKey = preg_replace('/[^a-z0-9]/', '', strtolower(trim(preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $trk['trackName']))));
                if ($cKey === '') {
                    $cKey = strtolower(preg_replace('/[^a-z0-9]/', '', $trk['trackName']));
                }
                if (!isset($seenBladeTitles[$cKey])) {
                    $seenBladeTitles[$cKey] = true;
                    $ostList[] = $trk;
                }
                if (count($ostList) >= 6) {
                    break;
                }
            }
            $ostN = count($ostList);
            $hasSpotifyUser = (bool) session('spotify_user_access_token');
        @endphp
        @if ($ostN > 0)
            <div class="ost-grid">
                @foreach ($ostList as $idx => $track)
                    <div class="ost-track-card" id="ost-card-{{ $idx }}">
                        {{-- Baris Atas: Judul, Penyanyi & Tombol Play / Tautan Spotify --}}
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="min-w-0 flex-grow-1">
                                <div class="ost-track-title" title="{{ $track['trackName'] }}">{{ $track['trackName'] }}</div>
                                <div class="ost-track-artist" title="{{ $track['artistName'] ?? '' }}">{{ $track['artistName'] ?? 'Official Artist' }}</div>
                            </div>

                            @if ($hasSpotifyUser && !empty($track['trackId']))
                                <button type="button" class="sp-play-btn" id="sp-btn-play-{{ $idx }}" onclick="togglePlaySpotifyTrack({{ $idx }})" aria-label="Putar lagu {{ $track['trackName'] }}">
                                    <svg id="sp-ico-play-{{ $idx }}" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                    <svg id="sp-ico-pause-{{ $idx }}" class="d-none" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                                </button>
                            @elseif (!empty($track['spotifyUrl']))
                                <a href="{{ $track['spotifyUrl'] }}"
                                   target="_blank" rel="noopener" class="text-success d-inline-flex align-items-center gap-1 small text-decoration-none fw-semibold flex-shrink-0" style="font-size:.74rem;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                                    Buka &rarr;
                                </a>
                            @endif
                        </div>

                        {{-- Mode Terhubung: Live Seekbar & Player Controller --}}
                        @if ($hasSpotifyUser)
                            <div class="mt-2">
                                <div class="sp-seek-lane" id="sp-seek-lane-{{ $idx }}" onclick="seekSpotifyTrack({{ $idx }}, event)">
                                    <div class="sp-seek-fill" id="sp-seek-fill-{{ $idx }}"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-1 text-dim" style="font-size: .68rem; font-variant-numeric: tabular-nums;">
                                    <span id="sp-cur-time-{{ $idx }}">0:00</span>
                                    <span id="sp-dur-time-{{ $idx }}">Full Durasi (Spotify)</span>
                                </div>
                            </div>
                        {{-- Mode Belum Terhubung: Spotify Embed Widget 80px --}}
                        @elseif (!empty($track['embedUrl']))
                            <iframe class="ost-spotify-frame"
                                    src="{{ $track['embedUrl'] }}"
                                    frameBorder="0"
                                    allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                                    loading="lazy"></iframe>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="card-flat p-3 text-center">
                <div class="text-white-50 small">Data soundtrack Spotify tidak tersedia untuk film ini.</div>
            </div>
        @endif
    </div>

    {{-- ======================= FILM SEJENIS ======================= --}}
    @if (!empty($film['filmSejenis']))
        <hr class="pilem-hr my-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="{{ route('film.search', ['genre' => explode(',', $film['genre'])[0] ?? '']) }}"
               class="btn btn-outline-pilem btn-sm" style="font-size:.78rem;">
                Lihat semua genre ini →
            </a>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
            @foreach ($film['filmSejenis'] as $mirip)
                <div class="col sejenis-card">
                    <a href="{{ route('film.detail', $mirip['id']) }}" class="text-decoration-none text-white">
                        <div class="card-pilem h-100">
                            @if (pilemPoster($mirip['id'], $mirip['poster']))
                                <img src="{{ pilemPoster($mirip['id'], $mirip['poster']) }}" class="poster" alt="{{ $mirip['nama'] }}">
                            @else
                                <div class="d-flex align-items-center justify-content-center"
                                     style="aspect-ratio:2/3; background: rgba(255,255,255,0.05);">
                                    <span style="font-size:1.8rem; opacity:.3;"></span>
                                </div>
                            @endif
                            <div class="p-2">
                                <div class="sejenis-title">{{ Str::limit($mirip['nama'], 20) }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection

@if (session('spotify_user_access_token'))
@push('scripts')
<script src="https://sdk.scdn.co/spotify-player.js"></script>
<script>
(function () {
    const ostTracks = @json($ostList ?? []);
    let currentDeviceId = null;
    let activeIndex = null;
    let spotifyPlayer = null;
    let trackProgressTimer = null;

    const fmt = ms => {
        const s = Math.floor(ms / 1000);
        return (isFinite(s) && s > 0) ? Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0') : '0:00';
    };

    window.onSpotifyWebPlaybackSDKReady = function () {
        spotifyPlayer = new Spotify.Player({
            name: 'Pilem Web Player',
            getOAuthToken: function (cb) {
                fetch('{{ route('spotify.token') }}')
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.token) cb(d.token);
                    })
                    .catch(err => console.error('Spotify token fetch error:', err));
            },
            volume: 0.8
        });

        spotifyPlayer.addListener('ready', ({ device_id }) => {
            console.log('Spotify Web Player Ready! Device ID:', device_id);
            currentDeviceId = device_id;
        });

        spotifyPlayer.addListener('not_ready', ({ device_id }) => {
            console.warn('Spotify Device ID offline:', device_id);
        });

        spotifyPlayer.addListener('player_state_changed', state => {
            if (!state) return;
            const isPaused = state.paused;
            const pos = state.position || 0;
            const dur = state.duration || 0;

            if (activeIndex !== null) {
                const curEl = document.getElementById('sp-cur-time-' + activeIndex);
                const durEl = document.getElementById('sp-dur-time-' + activeIndex);
                const fillEl = document.getElementById('sp-seek-fill-' + activeIndex);
                const playIco = document.getElementById('sp-ico-play-' + activeIndex);
                const pauseIco = document.getElementById('sp-ico-pause-' + activeIndex);
                const card = document.getElementById('ost-card-' + activeIndex);

                if (curEl) curEl.textContent = fmt(pos);
                if (durEl && dur > 0) durEl.textContent = fmt(dur);
                if (fillEl && dur > 0) fillEl.style.width = ((pos / dur) * 100) + '%';
                if (playIco) playIco.classList.toggle('d-none', !isPaused);
                if (pauseIco) pauseIco.classList.toggle('d-none', isPaused);
                if (card) card.classList.toggle('is-playing', !isPaused);
            }
        });

        spotifyPlayer.connect();
    };

    function startLiveTimer(idx) {
        stopLiveTimer();
        trackProgressTimer = setInterval(() => {
            if (!spotifyPlayer) return;
            spotifyPlayer.getCurrentState().then(state => {
                if (state && !state.paused && state.duration > 0) {
                    const curEl = document.getElementById('sp-cur-time-' + idx);
                    const durEl = document.getElementById('sp-dur-time-' + idx);
                    const fillEl = document.getElementById('sp-seek-fill-' + idx);
                    if (curEl) curEl.textContent = fmt(state.position);
                    if (durEl) durEl.textContent = fmt(state.duration);
                    if (fillEl) fillEl.style.width = ((state.position / state.duration) * 100) + '%';
                }
            });
        }, 300);
    }

    function stopLiveTimer() {
        if (trackProgressTimer) {
            clearInterval(trackProgressTimer);
            trackProgressTimer = null;
        }
    }

    window.togglePlaySpotifyTrack = function (idx) {
        const track = ostTracks[idx];
        if (!track || !track.trackId) return;

        if (activeIndex === idx) {
            if (spotifyPlayer) {
                spotifyPlayer.togglePlay();
            }
        } else {
            if (activeIndex !== null) {
                const prevCard = document.getElementById('ost-card-' + activeIndex);
                const prevPlayIco = document.getElementById('sp-ico-play-' + activeIndex);
                const prevPauseIco = document.getElementById('sp-ico-pause-' + activeIndex);
                if (prevCard) prevCard.classList.remove('is-playing');
                if (prevPlayIco) prevPlayIco.classList.remove('d-none');
                if (prevPauseIco) prevPauseIco.classList.add('d-none');
            }

            activeIndex = idx;
            const durEl = document.getElementById('sp-dur-time-' + idx);
            if (durEl) durEl.textContent = 'Memulai streaming...';

            fetch('{{ route('spotify.play') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    device_id: currentDeviceId,
                    track_uri: 'spotify:track:' + track.trackId
                })
            }).then(r => r.json()).then(res => {
                startLiveTimer(idx);
            }).catch(e => {
                console.error('Play error:', e);
                if (durEl) durEl.textContent = 'Gagal memutar';
            });
        }
    };

    window.seekSpotifyTrack = function (idx, event) {
        if (!spotifyPlayer || activeIndex !== idx) return;
        const lane = document.getElementById('sp-seek-lane-' + idx);
        if (!lane) return;
        const rect = lane.getBoundingClientRect();
        const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));

        spotifyPlayer.getCurrentState().then(state => {
            if (state && state.duration > 0) {
                const targetMs = Math.floor(ratio * state.duration);
                spotifyPlayer.seek(targetMs);
                const curEl = document.getElementById('sp-cur-time-' + idx);
                const fillEl = document.getElementById('sp-seek-fill-' + idx);
                if (curEl) curEl.textContent = fmt(targetMs);
                if (fillEl) fillEl.style.width = (ratio * 100) + '%';
            }
        });
    };
})();
</script>
@endpush
@endif