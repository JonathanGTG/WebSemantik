{{--
    Halaman utama pilem. -- desain mengikuti referensi tim (hero + spotlight,
    Now Playing, trailer, Ask Pilmy, ledger streaming, mini player OST).
    Kontrak data dari FilmController::home() TIDAK diubah. Poster diambil
    otomatis dari film.ttl lewat pilemPoster() bila controller belum menyediakan.
--}}
@extends('layouts.app')
@php $hideChatWidget = true; @endphp

@section('title', $isSearchMode ? 'Hasil Pencarian — pilem.' : 'pilem. — Now Playing')

@push('styles')
<style>
    /* ---------- Hero ---------- */
    .hero { position: relative; margin: -3rem calc(50% - 50vw) 0; padding: 3.5rem calc(50vw - 50%) 3rem; overflow: hidden; }
    .hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center 20%; filter: blur(46px) saturate(1.3); transform: scale(1.25); opacity: .32; }
    .hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(6,11,24,.35) 0%, var(--bg) 100%); }
    .hero > .row { position: relative; z-index: 1; }
    .hero-kicker { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; font-size: .76rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--cyan); }
    .hero-kicker .chip { border: 1px solid rgba(247,185,40,.5); color: var(--gold-soft); border-radius: 6px; padding: .15rem .5rem; font-size: .66rem; }
    .hero h1 { font-size: clamp(2.4rem, 5.4vw, 4.4rem); font-weight: 800; line-height: 1.02; letter-spacing: -.035em; margin: 1rem 0 1.1rem; text-wrap: balance; }
    .hero-lead { color: var(--text-dim); max-width: 580px; line-height: 1.7; font-size: 1rem; }
    .hero-nav { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: 2.4rem; }
    .arrow-btn { width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--line-strong); background: rgba(255,255,255,.04); color: var(--text); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: .15s; }
    .arrow-btn:hover { border-color: var(--cyan); color: var(--cyan); }
    .arrow-btn.disabled { opacity: .35; pointer-events: none; }

    .spotlight { border: 1px solid var(--line-strong); border-radius: 26px; padding: 1.25rem; background: linear-gradient(160deg, rgba(19,31,59,.85), rgba(9,16,33,.9)); box-shadow: 0 30px 70px rgba(0,0,0,.45); }
    .spot-media { position: relative; border-radius: 18px; overflow: hidden; aspect-ratio: 16/10; background: var(--surface); display: block; }
    .spot-media img { width: 100%; height: 100%; object-fit: cover; }
    .spot-media.is-poster img { object-fit: cover; object-position: center 22%; }
    .spot-media::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(6,11,24,.15), rgba(6,11,24,.62)); }
    .play-btn { position: absolute; z-index: 2; left: 50%; top: 50%; transform: translate(-50%,-50%); width: 64px; height: 64px; border-radius: 50%; background: rgba(39,196,232,.9); border: 3px solid rgba(255,255,255,.35); display: flex; align-items: center; justify-content: center; color: #04121a; transition: transform .15s; }
    .spot-media:hover .play-btn { transform: translate(-50%,-50%) scale(1.08); }
    .spot-tag { position: absolute; z-index: 2; top: .8rem; left: .8rem; background: var(--red); color: #fff; font-size: .64rem; font-weight: 800; letter-spacing: .05em; padding: .3rem .6rem; border-radius: 7px; }
    .spot-rating { position: absolute; z-index: 2; top: .8rem; right: .8rem; }
    .spot-meta { display: flex; justify-content: space-between; align-items: center; gap: .75rem; color: var(--text-mute); font-size: .76rem; font-weight: 600; }
    .chip-gold { border: 1px solid rgba(247,185,40,.45); background: rgba(247,185,40,.1); color: var(--gold-soft); border-radius: 8px; padding: .25rem .6rem; font-size: .7rem; font-weight: 700; }

    /* ---------- Now Playing ---------- */
    .seg { display: inline-flex; gap: .2rem; padding: .3rem; border: 1px solid var(--line); border-radius: 14px; background: rgba(255,255,255,.03); }
    .seg button { background: transparent; border: 1px solid transparent; color: var(--text-dim); font-weight: 600; font-size: .8rem; padding: .45rem 1rem; border-radius: 10px; cursor: pointer; transition: .15s; font-family: inherit; }
    .seg button:hover { color: var(--text); }
    .seg button.active { background: linear-gradient(180deg, #ffc93d, #f2a900); color: #1a1200; font-weight: 700; }
    .np-card { padding: .85rem; display: flex; flex-direction: column; }
    .np-card .poster-wrap { position: relative; border-radius: 14px; overflow: hidden; }
    .np-card .poster-wrap img { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; background: var(--surface); }
    .np-card .poster-wrap .badge-studio { position: absolute; top: .6rem; left: .6rem; }
    .np-card .poster-wrap .rating-badge { position: absolute; top: .6rem; right: .6rem; font-size: .7rem; padding: .2rem .5rem; }
    .np-title { font-weight: 800; font-size: 1rem; line-height: 1.25; margin-top: .9rem; text-align: center; }
    .np-sub { color: var(--text-mute); font-size: .74rem; text-align: center; margin-top: .15rem; min-height: 1.1em; }
    .np-meta { display: flex; justify-content: space-between; font-size: .72rem; color: var(--text-dim); font-weight: 600; margin-top: .9rem; padding-top: .8rem; border-top: 1px solid var(--line); }
    .np-meta b { color: var(--gold-soft); font-weight: 700; }
    .np-credit { margin-top: .5rem; font-size: .74rem; color: var(--text-dim); }
    .np-credit span { color: var(--text-mute); font-size: .62rem; letter-spacing: .06em; text-transform: uppercase; display: block; }
    .np-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-top: auto; padding-top: 1rem; }
    .np-actions .btn { font-size: .76rem; padding: .5rem; }

    /* ---------- Trailer ---------- */
    .player-panel { padding: 1.4rem; }
    .player-label { font-size: .72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-dim); padding-bottom: 1rem; border-bottom: 1px solid var(--line); margin-bottom: 1.1rem; }
    .cast-chip { display: flex; align-items: center; gap: .7rem; padding: .6rem .8rem; border: 1px solid var(--line); border-radius: 12px; background: rgba(255,255,255,.025); text-decoration: none; transition: .15s; min-width: 0; }
    .cast-chip:hover { border-color: rgba(39,196,232,.5); background: rgba(39,196,232,.06); }
    .cast-chip .avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; object-fit: cover; background: var(--surface-2); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .8rem; color: var(--text-dim); }
    .cast-chip .nm { font-size: .8rem; font-weight: 700; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cast-chip .rl { font-size: .68rem; color: var(--text-mute); }
    .playlist-item { display: flex; gap: .85rem; align-items: center; padding: .8rem; border-radius: 14px; border: 1px solid transparent; text-decoration: none; transition: .15s; }
    .playlist-item:hover { background: rgba(255,255,255,.04); }
    .playlist-item.active { background: rgba(39,196,232,.1); border-color: var(--cyan); box-shadow: 0 0 0 1px rgba(39,196,232,.4); }
    .playlist-item img { width: 40px; height: 58px; object-fit: cover; border-radius: 8px; flex-shrink: 0; background: var(--surface); }
    .playlist-item .pt { font-weight: 700; font-size: .85rem; line-height: 1.3; }
    .playlist-item .py { font-size: .7rem; color: var(--text-mute); }
    .playlist-item.active .py { color: var(--cyan); }

    /* ---------- Ask Pilmy ---------- */
    .chat-panel { padding: 1.25rem; display: flex; flex-direction: column; height: 520px; }
    #pilmy-messages { scrollbar-width: thin; scrollbar-color: var(--line-strong) transparent; }
    .chat-who { font-size: .64rem; font-weight: 700; letter-spacing: .05em; color: var(--text-mute); text-transform: uppercase; }
    .chat-input-row { display: flex; gap: .6rem; padding-top: 1rem; border-top: 1px solid var(--line); }

    /* ---------- Ledger ---------- */
    .ledger { border: 1px solid var(--line); border-radius: 22px; background: var(--bg-raised); overflow: hidden; }
    .ledger .num { color: var(--cyan); font-weight: 700; font-variant-numeric: tabular-nums; }
    .pill { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--line-strong); border-radius: 999px; padding: .32rem .7rem; font-size: .68rem; font-weight: 700; letter-spacing: .03em; background: rgba(255,255,255,.03); text-decoration: none; color: var(--text); }
    .pill img { width: 16px; height: 16px; border-radius: 4px; }
    .pill:hover { border-color: var(--cyan); color: var(--text); }

    /* ---------- Mini player ---------- */
    .mini-player { position: fixed; left: 50%; bottom: 14px; transform: translateX(-50%); width: min(1180px, calc(100% - 28px)); z-index: 1040; display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1.3fr) auto; align-items: center; gap: 1rem; padding: .7rem 1rem; border-radius: 20px; background: rgba(11,19,38,.92); backdrop-filter: blur(16px); border: 1px solid var(--line-strong); box-shadow: 0 20px 60px rgba(0,0,0,.55); }
    .mp-art { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold); flex-shrink: 0; background: var(--surface); }
    .mp-title { font-weight: 700; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mp-sub { font-size: .7rem; color: var(--text-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mp-ctrl { display: flex; align-items: center; justify-content: center; gap: .9rem; }
    .mp-ctrl button { background: none; border: none; color: var(--text-dim); display: flex; padding: .25rem; cursor: pointer; }
    .mp-ctrl button:hover { color: var(--text); }
    .mp-ctrl .mp-play { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(180deg, #ffc93d, #f2a900); color: #1a1200; align-items: center; justify-content: center; }
    .mp-ctrl .mp-play:hover { color: #1a1200; filter: brightness(1.08); }
    .mp-bar { display: flex; align-items: center; gap: .6rem; font-size: .66rem; color: var(--text-mute); font-variant-numeric: tabular-nums; margin-top: .15rem; }
    .mp-track { flex: 1; height: 4px; border-radius: 4px; background: rgba(255,255,255,.12); overflow: hidden; cursor: pointer; }
    .mp-fill { height: 100%; width: 0; background: var(--gold); }
    .btn-spotify { background: #1db954; color: #04130a; font-weight: 700; border: none; border-radius: 999px; font-size: .78rem; padding: .5rem 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: .4rem; }
    .btn-spotify:hover { filter: brightness(1.08); color: #04130a; }
    @media (max-width: 767.98px) {
        .mini-player { grid-template-columns: 1fr auto; }
        .mini-player .mp-center { grid-column: 1 / -1; order: 3; }
        .mini-player .mp-right { display: none; }
        body { padding-bottom: 8rem; }
    }

    /* ---------- Pencarian ---------- */
    .film-card-list { display: flex; gap: 1rem; align-items: center; background: rgba(255,255,255,.035); border: 1px solid var(--line); border-radius: 16px; padding: .9rem 1rem; transition: .15s; text-decoration: none; color: inherit; }
    .film-card-list:hover { border-color: rgba(39,196,232,.5); transform: translateX(4px); color: inherit; }
    .film-card-list img { width: 52px; height: 78px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
    .film-card-list .no-poster { width: 52px; height: 78px; background: var(--surface-2); border-radius: 8px; flex-shrink: 0; }
    .result-count { font-size: .85rem; color: var(--text-dim); }
</style>
@endpush

@section('content')

{{-- ======================= SEARCH MODE ======================= --}}
@if ($isSearchMode)
    <div class="mb-4">
        <div class="eyebrow mb-1">Pencarian</div>
        <h1 class="fw-bold mb-3" style="font-size:2rem;">Cari film</h1>
        <form method="GET" action="{{ route('home') }}" id="home-search-form" class="row g-2 mb-3">
            <div class="col-12 col-md-5">
                <input type="text" name="q" class="form-control search-input form-control-lg" style="font-size:.95rem;" placeholder="Judul film, sutradara, aktor..." value="{{ $keyword }}" autocomplete="off">
            </div>
            <div class="col-4 col-md-2"><input type="text" name="genre" class="form-control search-input form-control-lg" style="font-size:.95rem;" placeholder="Genre" value="{{ $genre }}"></div>
            <div class="col-4 col-md-2"><input type="number" name="tahun" class="form-control search-input form-control-lg" style="font-size:.95rem;" placeholder="Tahun" value="{{ $tahun }}" min="1900" max="{{ date('Y') }}"></div>
            <div class="col-4 col-md-2"><input type="text" name="negara" class="form-control search-input form-control-lg" style="font-size:.95rem;" placeholder="Negara" value="{{ $negara }}"></div>
            <div class="col-12 col-md-1"><button type="submit" class="btn btn-sun w-100 h-100">Cari</button></div>
        </form>
        <div class="d-flex flex-wrap gap-2">
            @foreach (['Action', 'Adventure', 'Animation', 'Comedy', 'Drama', 'Science Fiction', 'Family'] as $g)
                <button type="button" class="filter-badge {{ strtolower($genre) === strtolower($g) ? 'active' : '' }}" onclick="setHomeGenre('{{ $g }}')">{{ $g }}</button>
            @endforeach
            <a href="{{ route('home') }}" class="filter-badge" style="color:#ff9a9a; border-color:rgba(255,120,120,.35);">Hapus filter</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first('sparql') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <span class="result-count">
            <strong class="text-white">{{ count($films) }}</strong> film ditemukan
            @if ($keyword) untuk &ldquo;{{ $keyword }}&rdquo; @endif
            @if (!empty($huruf)) &middot; awalan huruf &ldquo;{{ $huruf }}&rdquo; @endif
            @if ($genre) &middot; genre {{ $genre }} @endif
            @if ($tahun) &middot; tahun {{ $tahun }} @endif
            @if (!empty($rated)) &middot; usia {{ $rated }} @endif
            @if ($negara) &middot; negara {{ $negara }} @endif
        </span>
        <div class="seg">
            <button type="button" class="active" id="view-grid" onclick="setView('grid')">Grid</button>
            <button type="button" id="view-list" onclick="setView('list')">List</button>
        </div>
    </div>

    @if (empty($films))
        <div class="text-center py-5">
            <p class="fw-bold mb-1">Film tidak ditemukan</p>
            <p class="text-dim small mb-3">Coba kata kunci lain atau kurangi filter.</p>
            <a href="{{ route('home') }}" class="btn btn-outline-pilem">Kembali ke Now Playing</a>
        </div>
    @else
        <div id="results-grid" class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
            @foreach ($films as $film)
                @php $poster = pilemPoster($film['id'], $film['poster']); @endphp
                <div class="col">
                    <a href="{{ route('film.detail', $film['id']) }}" class="text-decoration-none text-white">
                        <div class="card-pilem">
                            @if ($poster)
                                <img src="{{ $poster }}" class="poster" alt="{{ $film['nama'] }}" loading="lazy">
                            @else
                                <div class="poster-fallback">{{ $film['nama'] }}</div>
                            @endif
                            <div class="p-3">
                                <div class="fw-bold small mb-1">{{ Str::limit($film['nama'], 26) }}</div>
                                <div class="text-dim" style="font-size:.72rem;">
                                    {{ $film['tahun'] ? substr($film['tahun'], 0, 4) : '-' }}
                                    @if (!empty($film['rated'])) &middot; <span class="badge border border-secondary text-light px-1" style="font-size:.65rem;">{{ $film['rated'] }}</span> @endif
                                    @if ($film['genre']) &middot; {{ Str::limit(explode(',', $film['genre'])[0], 16) }} @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div id="results-list" class="d-none flex-column gap-2">
            @foreach ($films as $film)
                @php $poster = pilemPoster($film['id'], $film['poster']); @endphp
                <a href="{{ route('film.detail', $film['id']) }}" class="film-card-list">
                    @if ($poster)
                        <img src="{{ $poster }}" alt="{{ $film['nama'] }}" loading="lazy">
                    @else
                        <div class="no-poster"></div>
                    @endif
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1">{{ $film['nama'] }}</div>
                        <div class="text-dim small">
                            {{ $film['tahun'] ? substr($film['tahun'], 0, 4) : '-' }}
                            @if ($film['genre']) &middot; {{ $film['genre'] }} @endif
                            @if ($film['negara']) &middot; {{ $film['negara'] }} @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

@else
{{-- ======================= NOW PLAYING MODE ======================= --}}
@php
    $studioKey  = fn ($name, $studio) => pilemFilmStudio($name, $studio);
    $badgeClass = fn ($studio) => match (pilemStudioBadge($studio)) { 'MCU' => 'mcu', 'PIXAR' => 'pixar', default => 'disney' };

    $feat       = $featured;
    $featPoster = $feat ? pilemPoster($feat['id'], $feat['poster']) : null;
    $featTrailer = $feat['externalData']['trailerKey'] ?? null;
    $featStudio = $feat ? $studioKey($feat['nama'], $feat['studio']) : null;
    $featIndex  = $feat ? collect($nowPlaying)->search(fn ($f) => $f['id'] === $feat['id']) : false;
    $prevFilm   = ($featIndex !== false && $featIndex > 0) ? $nowPlaying[$featIndex - 1] : null;
    $nextFilm   = ($featIndex !== false && $featIndex < count($nowPlaying) - 1) ? $nowPlaying[$featIndex + 1] : null;
    $featureUrl = fn ($id, $hash = '') => route('home', ['featured' => $id]).$hash;
    $tracks     = collect($feat['externalData']['soundtrack'] ?? [])->filter(fn ($t) => !empty($t['previewUrl']))->take(8)->values();
    $trackData  = $tracks->map(fn ($t) => ['title' => $t['trackName'] ?? '', 'artist' => $t['artistName'] ?? '', 'art' => $t['artworkUrl100'] ?? '', 'src' => $t['previewUrl']])->values()->all();
@endphp

{{-- ---------- HERO ---------- --}}
@if ($feat)
<section class="hero">
    @if ($featPoster)<div class="hero-bg" style="background-image:url('{{ $featPoster }}')"></div>@endif
    <div class="row g-5 align-items-center">
        <div class="col-lg-7">
            <div class="hero-kicker">
                <span>{{ $feat['genre'] ? str_replace(', ', ' / ', $feat['genre']) : 'Film' }}</span>
                @if ($featStudio)<span class="chip">{{ $featStudio }}</span>@endif
            </div>
            <h1>{{ $feat['nama'] }}</h1>
            <p class="hero-lead">{{ Str::limit($feat['sinopsis'] ?? 'Sinopsis belum tersedia untuk film ini.', 240) }}</p>
            <div class="d-flex gap-2 flex-wrap mt-4">
                <a href="{{ route('film.detail', $feat['id']) }}" class="btn btn-sun px-4 py-2">Lihat detail</a>
                <a href="#trailer-section" class="btn btn-outline-pilem px-4 py-2">Tonton trailer</a>
                <a href="{{ route('film.detail', $feat['id']) }}#soundtrack" class="btn btn-cyan px-4 py-2">Dengar OST</a>
            </div>

            @if (count($nowPlaying) > 1)
                <div class="hero-nav">
                    <a class="arrow-btn {{ $prevFilm ? '' : 'disabled' }}" href="{{ $prevFilm ? $featureUrl($prevFilm['id']) : '#' }}" aria-label="Film sebelumnya">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </a>
                    <a class="arrow-btn {{ $nextFilm ? '' : 'disabled' }}" href="{{ $nextFilm ? $featureUrl($nextFilm['id']) : '#' }}" aria-label="Film berikutnya">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                    <div class="d-flex flex-wrap ms-2">
                        @foreach (array_slice($nowPlaying, 0, 5) as $i => $f)
                            <a class="hero-tab {{ $f['id'] === $feat['id'] ? 'active' : '' }}" href="{{ $featureUrl($f['id']) }}">
                                {{ sprintf('%02d', $i + 1) }} {{ Str::limit(explode(':', $f['nama'])[0], 14, '') }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="spotlight">
                <a href="#trailer-section" class="spot-media {{ $featTrailer ? '' : 'is-poster' }}" aria-label="Tonton trailer {{ $feat['nama'] }}">
                    @if ($featTrailer)
                        <img src="{{ pilemYouTubeThumb($featTrailer) }}" alt="" loading="eager">
                    @elseif ($featPoster)
                        <img src="{{ $featPoster }}" alt="">
                    @endif
                    <span class="spot-tag">Film unggulan</span>
                    @if ($feat['rating'])<span class="rating-badge spot-rating">&#9733; {{ number_format($feat['rating'], 1) }} / 10</span>@endif
                    <span class="play-btn"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
                </a>
                <div class="mt-3">
                    <div class="eyebrow" style="font-size:.66rem;">{{ $featStudio }}</div>
                    <div class="fw-bold mt-1" style="font-size:1.25rem; letter-spacing:-.01em;">{{ $feat['nama'] }}</div>
                </div>
                <div class="spot-meta mt-3 pt-3" style="border-top:1px solid var(--line);">
                    <span>Rilis {{ $feat['tahun'] ? substr($feat['tahun'], 0, 4) : '-' }} &nbsp;&bull;&nbsp; {{ pilemFormatDurasi($feat['durasi']) ?? '-' }}</span>
                    @if ($feat['sutradara'])<span class="chip-gold">{{ Str::limit($feat['sutradara'], 22) }}</span>@endif
                </div>
            </div>
        </div>
    </div>
</section>
@else
<section class="text-center py-5">
    <h1 class="fw-bold">pilem<span style="color:var(--gold)">.</span></h1>
    <p class="text-dim">Belum ada film untuk ditampilkan. Pastikan Fuseki berjalan dan dataset film.ttl sudah ter-load.</p>
</section>
@endif

{{-- ---------- NOW PLAYING ---------- --}}
<section class="pt-5 mt-4" id="now-playing">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="section-title mb-0">Now Playing</h2>
            </div>
            <p class="text-dim small mb-0">
                @if (($category ?? 'now_playing') !== 'top_rated')
                    Film rilis terbaru &amp; sedang tayang (2024–2026) dari Disney, Marvel Studios, dan Pixar.
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            {{-- Pilihan Kategori: Now Playing Terbaru vs All-Time Top Rated --}}
            <div class="seg" role="tablist" aria-label="Pilih Kategori Tayang">
                <a href="{{ route('home', array_merge(request()->query(), ['category' => 'now_playing'])) }}#now-playing"
                   class="{{ ($category ?? 'now_playing') !== 'top_rated' ? 'active' : '' }}"
                   style="text-decoration:none; padding: .45rem .85rem; border-radius: 10px; font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; gap: .3rem;">
                    Sedang Tayang (2024–2026)
                </a>
                <a href="{{ route('home', array_merge(request()->query(), ['category' => 'top_rated'])) }}#now-playing"
                   class="{{ ($category ?? 'now_playing') === 'top_rated' ? 'active' : '' }}"
                   style="text-decoration:none; padding: .45rem .85rem; border-radius: 10px; font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; gap: .3rem;">
                    Terbaik Sepanjang Masa
                </a>
            </div>

            {{-- Filter Studio --}}
            <div class="seg" id="studio-tabs" role="tablist" aria-label="Filter studio">
                <button type="button" class="active" data-studio="all">Semua Studio</button>
                <button type="button" data-studio="Marvel Studios">Marvel</button>
                <button type="button" data-studio="Pixar">Pixar</button>
                <button type="button" data-studio="Walt Disney Pictures">Disney</button>
            </div>
        </div>
    </div>

    @if (empty($nowPlaying))
        <p class="text-dim">Tidak ada film untuk ditampilkan.</p>
    @else
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3" id="np-grid">
            @foreach ($nowPlaying as $f)
                @php
                    $poster = pilemPoster($f['id'], $f['poster']);
                    $studio = $studioKey($f['nama'], $f['studio']);
                    $isActive = $feat && $feat['id'] === $f['id'];
                @endphp
                <div class="col np-col" data-studio="{{ $studio }}">
                    <article class="card-pilem np-card {{ $isActive ? 'active-feature' : '' }}">
                        <a href="{{ route('film.detail', $f['id']) }}" class="text-decoration-none text-white">
                            <div class="poster-wrap">
                                @if ($poster)
                                    <img src="{{ $poster }}" alt="Poster {{ $f['nama'] }}" loading="lazy">
                                @else
                                    <div class="poster-fallback">{{ $f['nama'] }}</div>
                                @endif
                                <span class="badge-studio {{ $badgeClass($studio) }}">{{ pilemStudioBadge($studio) }}</span>
                                @if ($f['rating'])<span class="rating-badge">&#9733; {{ number_format($f['rating'], 1) }}</span>@endif
                                @if ($f['tahun'])
                                    <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark bg-opacity-75 border border-secondary text-white" style="font-size: .65rem; font-weight: 700;">
                                        {{ substr($f['tahun'], 0, 4) }}
                                    </span>
                                @endif
                            </div>
                            <div class="np-title">{{ Str::limit($f['nama'], 28) }}</div>
                            <div class="np-sub">{{ Str::limit($f['genre'] ?? '', 34) }}</div>
                        </a>
                        <div class="np-meta">
                            <span>{{ $f['tahun'] ? substr($f['tahun'], 0, 4) : '-' }}</span>
                            <span>{{ pilemFormatDurasi($f['durasi']) ?? '-' }}</span>
                            <b>{{ $f['rating'] ? number_format($f['rating'], 1).'/10' : '-' }}</b>
                        </div>
                        @if ($f['sutradara'])
                            <div class="np-credit"><span>Sutradara</span>{{ Str::limit($f['sutradara'], 26) }}</div>
                        @endif
                        <div class="np-actions">
                            <a href="{{ $featureUrl($f['id'], '#trailer-section') }}" class="btn btn-outline-pilem">&#9654; Trailer</a>
                            <a href="{{ route('film.detail', $f['id']) }}#soundtrack" class="btn btn-sun">&#9835; OST</a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        <p id="np-empty" class="text-dim mt-3 d-none">Belum ada film dari studio ini di daftar Now Playing.</p>
    @endif
</section>

{{-- ---------- TRAILER ---------- --}}
<section class="pt-5 mt-5" id="trailer-section">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h2 class="section-title mb-0">Official Trailers &amp; Clips</h2>
        @if ($feat)
            <a href="https://www.youtube.com/results?search_query={{ urlencode($feat['nama'].' official trailer') }}" target="_blank" rel="noopener"
               class="btn btn-sm px-3" style="background:var(--red); color:#fff; font-weight:700; border-radius:10px;">Cari di YouTube</a>
        @endif
    </div>

    @if ($feat)
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel player-panel h-100">
                <div class="player-label">Sedang diputar: {{ $feat['nama'] }} (trailer resmi)</div>
                @if ($featTrailer)
                    <div class="ratio ratio-16x9 rounded-4 overflow-hidden" style="background:#000;">
                        <iframe src="https://www.youtube.com/embed/{{ $featTrailer }}" title="Trailer {{ $feat['nama'] }}" allow="accelerometer; autoplay; encrypted-media; picture-in-picture" allowfullscreen style="border:0;"></iframe>
                    </div>
                @else
                    <div class="ratio ratio-16x9 rounded-4 overflow-hidden d-flex align-items-center justify-content-center" style="background:var(--surface);">
                        <div class="d-flex align-items-center justify-content-center text-dim small p-4 text-center">Trailer film ini belum tersedia di TMDB.</div>
                    </div>
                @endif

                @php $cast = collect($feat['castCrew'] ?? [])->where('peran', 'Aktor')->take(4); @endphp
                @if ($cast->isNotEmpty())
                    <div class="mt-4">
                        <div class="text-mute fw-bold mb-2" style="font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:var(--text-mute);">Pemeran utama</div>
                        <div class="row row-cols-2 row-cols-md-4 g-2">
                            @foreach ($cast as $orang)
                                <div class="col">
                                    <a href="{{ route('person.detail', $orang['id']) }}" class="cast-chip text-white">
                                        @if ($orang['foto'])
                                            <img src="{{ $orang['foto'] }}" class="avatar" alt="" loading="lazy">
                                        @else
                                            <span class="avatar">{{ mb_strtoupper(mb_substr($orang['nama'], 0, 1)) }}</span>
                                        @endif
                                        <span class="min-w-0">
                                            <span class="nm d-block">{{ Str::limit($orang['nama'], 18) }}</span>
                                            <span class="rl d-block">{{ !empty($orang['karakter']) ? 'sb. ' . Str::limit($orang['karakter'], 16) : 'Aktor' }}</span>
                                        </span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="panel p-3 h-100 d-flex flex-column">
                <div class="px-2 pt-2 pb-3">
                    <div class="fw-bold small">Playlist film</div>
                    <div class="text-dim" style="font-size:.74rem;">Pilih film untuk memutar trailernya.</div>
                </div>
                <div class="d-flex flex-column gap-1 flex-grow-1">
                    @foreach (array_slice($nowPlaying, 0, 5) as $f)
                        @php $pp = pilemPoster($f['id'], $f['poster']); @endphp
                        <a href="{{ $featureUrl($f['id'], '#trailer-section') }}" class="playlist-item {{ $f['id'] === $feat['id'] ? 'active' : '' }}">
                            @if ($pp)<img src="{{ $pp }}" alt="" loading="lazy">@else<span style="width:40px;height:58px;" class="rounded-2 bg-white bg-opacity-10"></span>@endif
                            <span>
                                <span class="pt d-block">{{ Str::limit($f['nama'], 30) }}</span>
                                <span class="py d-block">{{ $f['tahun'] ? substr($f['tahun'], 0, 4) : '' }} &middot; {{ pilemFormatDurasi($f['durasi']) ?? '-' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
                <a href="https://www.youtube.com/results?search_query={{ urlencode($feat['nama'].' trailer') }}" target="_blank" rel="noopener" class="btn btn-outline-pilem w-100 mt-3">Buka di YouTube</a>
            </div>
        </div>
    </div>
    @endif
</section>

{{-- ---------- ASK PILMY ---------- --}}
<section class="pt-5 mt-5" id="ask-pilmy">
    <div class="eyebrow mb-1">AI Assistant</div>
    <h2 class="section-title mb-4">Ask Pilmy!</h2>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel chat-panel">
                <div id="pilmy-messages" class="flex-grow-1 overflow-auto mb-3 d-flex flex-column gap-3 pe-1">
                    <div class="align-self-start" style="max-width:88%;">
                        <div class="chat-who mb-1">Pilmy</div>
                        <div class="chat-bubble-bot">Halo! Saya khusus membahas film Disney, Marvel &amp; Pixar yang ada di database CineGraph. Tanya rekomendasi, sutradara, pemeran, atau info film di katalog ini.</div>
                    </div>
                </div>
                <form id="pilmy-form" class="chat-input-row">
                    <input type="text" id="pilmy-input" class="form-control search-input" placeholder="Contoh: rekomendasikan film Pixar yang bagus" autocomplete="off" maxlength="1000" aria-label="Pesan untuk Pilmy">
                    <button type="submit" class="btn btn-sun px-4">Kirim</button>
                </form>
                <small id="pilmy-status" class="text-dim mt-2" style="min-height:1.2em;"></small>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel p-4">
                <div class="eyebrow mb-1">Contoh pertanyaan</div>
                <div class="text-dim small mb-3">Klik salah satu untuk langsung bertanya.</div>
                @foreach ([
                    'Rekomendasikan film Pixar yang paling emosional',
                    'Urutan nonton film Marvel Cinematic Universe yang benar?',
                    'Film Disney animasi klasik apa yang bagus untuk ditonton?',
                    'Rekomendasikan film superhero untuk anak-anak',
                ] as $prompt)
                    <button type="button" class="quick-prompt pilmy-quick-prompt"><span>{{ $prompt }}</span><span aria-hidden="true" style="color:var(--cyan)">&rarr;</span></button>
                @endforeach
                <div class="text-dim d-flex align-items-center gap-2 mt-3" style="font-size:.72rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--cyan)" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                    Jawaban berdasarkan data katalog
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ---------- STREAMING ---------- --}}
<section class="pt-5 mt-5" id="streaming">
    <div class="eyebrow mb-1">Ketersediaan resmi</div>
    <h2 class="section-title mb-4">Streaming Availability</h2>

    @if (empty($streamingPreview))
        <div class="card-flat p-4 text-dim small">Data streaming belum tersedia. Pastikan <code>TMDB_API_KEY_LARAVEL</code> sudah diisi di <code>.env</code>.</div>
    @else
        <div class="ledger">
            <div class="table-responsive">
                <table class="table table-pilem align-middle mb-0">
                    <thead>
                        <tr><th>No</th><th>Judul</th><th>Tahun</th><th>Rating</th><th class="text-end">Akses streaming</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($streamingPreview as $i => $s)
                            @php $sBadge = pilemStudioBadge(pilemFilmStudio($s['nama'], $s['studio'])); @endphp
                            <tr>
                                <td class="num">{{ sprintf('%03d', $i + 1) }}</td>
                                <td>
                                    <a href="{{ route('film.detail', $s['id']) }}" class="text-decoration-none text-white fw-semibold">{{ $s['nama'] }}</a>
                                    <span class="badge-studio {{ $sBadge === 'MCU' ? 'mcu' : ($sBadge === 'PIXAR' ? 'pixar' : 'disney') }} ms-2">{{ $sBadge }}</span>
                                </td>
                                <td class="text-dim">{{ $s['tahun'] ? substr($s['tahun'], 0, 4) : '-' }}</td>
                                <td style="color:var(--gold-soft); font-weight:700;">{{ $s['rating'] ? number_format($s['rating'], 1).' / 10' : '-' }}</td>
                                <td class="text-end">
                                    @if (!empty($s['providers']))
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            @foreach (array_slice($s['providers'], 0, 4) as $p)
                                                <a href="{{ $s['link'] ?? '#' }}" target="_blank" rel="noopener" class="pill">
                                                    @if (!empty($p['logo_path']))<img src="https://image.tmdb.org/t/p/w92{{ $p['logo_path'] }}" alt="">@endif{{ $p['provider_name'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-dim small">Belum tersedia di wilayah ini</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>

@endif {{-- end isSearchMode --}}

@endsection

{{-- ======================= MINI PLAYER OST ======================= --}}
@if (!$isSearchMode && !empty($tracks) && $tracks->isNotEmpty())
@push('player')
<div class="mini-player" id="mini-player" role="region" aria-label="Pemutar soundtrack">
    <div class="d-flex align-items-center gap-3 min-w-0" style="min-width:0;">
        <img class="mp-art" id="mp-art" alt="" src="{{ $tracks[0]['artworkUrl100'] ?? '' }}">
        <div style="min-width:0;">
            <div class="mp-title" id="mp-title">{{ $tracks[0]['trackName'] ?? '' }}</div>
            <div class="mp-sub" id="mp-sub">{{ $tracks[0]['artistName'] ?? '' }} &middot; {{ $feat['nama'] }}</div>
        </div>
    </div>
    <div class="mp-center">
        <div class="mp-ctrl">
            <button type="button" id="mp-prev" aria-label="Lagu sebelumnya"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg></button>
            <button type="button" class="mp-play" id="mp-play" aria-label="Putar atau jeda">
                <svg id="mp-ico-play" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                <svg id="mp-ico-pause" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="display:none"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
            </button>
            <button type="button" id="mp-next" aria-label="Lagu berikutnya"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 6h2v12h-2zM6 18l8.5-6L6 6z"/></svg></button>
        </div>
        <div class="mp-bar"><span id="mp-cur">0:00</span><div class="mp-track" id="mp-track"><div class="mp-fill" id="mp-fill"></div></div><span id="mp-dur">0:30</span></div>
    </div>
    <div class="mp-right">
        <a class="btn-spotify" id="mp-spotify" target="_blank" rel="noopener" href="https://open.spotify.com/search/{{ urlencode(($tracks[0]['trackName'] ?? '').' '.($tracks[0]['artistName'] ?? '')) }}">Buka di Spotify</a>
    </div>
    <audio id="mp-audio" preload="none"></audio>
</div>
@endpush
@endif

@push('scripts')
<script>
(function () {
    /* ---------- Filter studio (client-side) ---------- */
    const tabs = document.querySelectorAll('#studio-tabs button');
    const cols = document.querySelectorAll('.np-col');
    const emptyMsg = document.getElementById('np-empty');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.toggle('active', t === tab));
            const want = tab.dataset.studio;
            let shown = 0;
            cols.forEach(function (c) {
                const ok = want === 'all' || c.dataset.studio === want;
                c.classList.toggle('d-none', !ok);
                if (ok) shown++;
            });
            if (emptyMsg) emptyMsg.classList.toggle('d-none', shown > 0);
        });
    });

    /* ---------- Mini player OST ---------- */
    const player = document.getElementById('mini-player');
    if (player) {
        const tracks = @json($trackData ?? []);
        const film = @json($feat['nama'] ?? '');
        const audio = document.getElementById('mp-audio');
        const $ = id => document.getElementById(id);
        let idx = 0;

        const fmt = s => isFinite(s) ? Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0') : '0:00';
        function load(i, autoplay) {
            idx = (i + tracks.length) % tracks.length;
            const t = tracks[idx];
            audio.src = t.src;
            $('mp-title').textContent = t.title;
            $('mp-sub').textContent = t.artist + ' · ' + film;
            if (t.art) $('mp-art').src = t.art;
            $('mp-spotify').href = 'https://open.spotify.com/search/' + encodeURIComponent(t.title + ' ' + t.artist);
            $('mp-fill').style.width = '0%';
            $('mp-cur').textContent = '0:00';
            if (autoplay) audio.play().catch(() => {});
        }
        function setIcons(playing) {
            $('mp-ico-play').style.display = playing ? 'none' : '';
            $('mp-ico-pause').style.display = playing ? '' : 'none';
        }
        $('mp-play').addEventListener('click', function () {
            if (!audio.src) load(0, true);
            else if (audio.paused) audio.play().catch(() => {});
            else audio.pause();
        });
        $('mp-prev').addEventListener('click', () => load(idx - 1, true));
        $('mp-next').addEventListener('click', () => load(idx + 1, true));
        audio.addEventListener('play', () => setIcons(true));
        audio.addEventListener('pause', () => setIcons(false));
        audio.addEventListener('ended', () => load(idx + 1, true));
        audio.addEventListener('timeupdate', function () {
            if (audio.duration) $('mp-fill').style.width = (audio.currentTime / audio.duration * 100) + '%';
            $('mp-cur').textContent = fmt(audio.currentTime);
            $('mp-dur').textContent = fmt(audio.duration || 30);
        });
        $('mp-track').addEventListener('click', function (e) {
            if (!audio.duration) return;
            const r = this.getBoundingClientRect();
            audio.currentTime = ((e.clientX - r.left) / r.width) * audio.duration;
        });
        load(0, false);
    }

    /* ---------- Ask Pilmy ---------- */
    const form = document.getElementById('pilmy-form');
    if (!form) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const messages = document.getElementById('pilmy-messages');
    const input = document.getElementById('pilmy-input');
    const status = document.getElementById('pilmy-status');
    const filmDetailBase = @json(url('/film'));

    function appendMessage(role, text) {
        const wrap = document.createElement('div');
        wrap.className = role === 'user' ? 'align-self-end text-end' : 'align-self-start';
        wrap.style.maxWidth = '88%';
        const who = document.createElement('div');
        who.className = 'chat-who mb-1';
        who.textContent = role === 'user' ? 'Kamu' : 'Pilmy';
        const bubble = document.createElement('div');
        bubble.className = role === 'user' ? 'chat-bubble-user d-inline-block text-start' : 'chat-bubble-bot';
        bubble.style.whiteSpace = 'pre-wrap';
        bubble.textContent = text;
        wrap.append(who, bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendFilmCards(films) {
        if (!films || !films.length) return;
        const wrap = document.createElement('div');
        wrap.className = 'align-self-start w-100';
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; gap:10px; overflow-x:auto; padding-bottom:6px; scrollbar-width:thin;';
        films.forEach(function (film) {
            const a = document.createElement('a');
            a.href = filmDetailBase + '/' + film.film_id;
            a.title = film.title + (film.studio ? ' · ' + film.studio : '');
            a.style.cssText = 'flex:0 0 92px; text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:5px;';
            const poster = document.createElement(film.poster ? 'img' : 'div');
            if (film.poster) { poster.src = film.poster; poster.alt = film.title; poster.loading = 'lazy'; }
            poster.style.cssText = 'width:92px; height:138px; object-fit:cover; border-radius:10px; border:1px solid var(--line-strong); background:var(--surface-2);';
            const t = document.createElement('div');
            t.style.cssText = 'font-size:.68rem; line-height:1.3; color:var(--text-dim); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;';
            t.textContent = film.title;
            a.append(poster, t);
            row.appendChild(a);
        });
        wrap.appendChild(row);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    }

    async function sendMessage(message) {
        if (!message.trim()) return;
        appendMessage('user', message);
        input.value = '';
        status.textContent = 'Pilmy sedang mengetik...';
        try {
            const res = await fetch(@json(route('chatbot.send')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ message: message }),
            });
            const data = await res.json();
            if (!res.ok) { appendMessage('bot', data.error || 'Terjadi kesalahan.'); status.textContent = ''; return; }
            appendMessage('bot', data.reply);
            appendFilmCards(data.filmSuggestions || []);
            status.textContent = data.messagesUsed + '/' + data.messagesLimit + ' pesan dipakai';
        } catch (err) {
            appendMessage('bot', 'Pilmy sedang tidak bisa dihubungi. Coba lagi.');
            status.textContent = '';
        }
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); sendMessage(input.value); });
    document.querySelectorAll('.pilmy-quick-prompt').forEach(function (btn) {
        btn.addEventListener('click', () => sendMessage(btn.querySelector('span').textContent.trim()));
    });
})();

/* ---------- Helper pencarian ---------- */
function setHomeGenre(g) {
    document.querySelector('#home-search-form input[name="genre"]').value = g;
    document.getElementById('home-search-form').submit();
}
function setView(v) {
    const grid = document.getElementById('results-grid');
    const list = document.getElementById('results-list');
    if (!grid || !list) return;
    const isGrid = v === 'grid';
    grid.classList.toggle('d-none', !isGrid);
    list.classList.toggle('d-none', isGrid);
    list.classList.toggle('d-flex', !isGrid);
    document.getElementById('view-grid').classList.toggle('active', isGrid);
    document.getElementById('view-list').classList.toggle('active', !isGrid);
    try { localStorage.setItem('pilem_view', v); } catch (e) {}
}
try { if (localStorage.getItem('pilem_view') === 'list') setView('list'); } catch (e) {}
</script>
@endpush
