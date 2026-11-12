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
    .ost-grid { display: grid; gap: .8rem; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .ost-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1200px) { .ost-grid.cols-3 { grid-template-columns: repeat(3, 1fr); } }
    .ost-grid.cols-1 { grid-template-columns: 1fr; max-width: 560px; }
    .ost-track {
        display: flex; flex-direction: column; gap: .15rem;
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px; padding: .85rem 1rem; transition: border-color .15s;
    }
    .ost-track:hover { border-color: var(--sky); }
    .ost-title { font-size: .9rem; font-weight: 700; line-height: 1.35; }
    .ost-artist { font-size: .8rem; color: rgba(255,255,255,.55); line-height: 1.3; }
    .ost-player { margin-top: auto; padding-top: .7rem; }
    audio { display: block; width: 100%; height: 36px; border-radius: 8px; color-scheme: dark; }
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
        <div class="section-label">Soundtrack (iTunes)</div>
        @php
            $ostList = array_values(array_filter($film['externalData']['soundtrack'] ?? [], fn ($t) => !empty($t['trackName'])));
            $ostN = min(6, count($ostList));
            if ($ostN === 5) { $ostN = 4; }
            $ostList = array_slice($ostList, 0, $ostN);
            $ostCols = in_array($ostN, [3, 6]) ? 'cols-3' : ($ostN === 1 ? 'cols-1' : 'cols-2');
        @endphp
        @if ($ostN > 0)
            <div class="ost-grid {{ $ostCols }}">
                @foreach ($ostList as $track)
                    <div class="ost-track">
                        <div class="ost-title">{{ $track['trackName'] }}</div>
                        <div class="ost-artist">{{ $track['artistName'] ?? '' }}</div>
                        <div class="ost-player">
                            @if (!empty($track['previewUrl']))
                                <audio controls preload="none">
                                    <source src="{{ $track['previewUrl'] }}" type="audio/mp4">
                                </audio>
                                <div class="data-source-note mt-2">
                                    Preview 30 detik ·
                                    <a href="https://open.spotify.com/search/{{ urlencode(trim(($track['trackName'] ?? '') . ' ' . ($track['artistName'] ?? ''))) }}"
                                       target="_blank" rel="noopener" class="text-sand">Cari di Spotify →</a>
                                </div>
                            @else
                                <div class="data-source-note">Preview tidak tersedia.</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card-flat p-3 text-center">
                <div class="text-white-50 small">Data soundtrack tidak tersedia.</div>
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