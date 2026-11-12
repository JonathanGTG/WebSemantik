{{--
    Layout utama -- tema gelap navy "pilem" (mengikuti referensi desain tim).
    Dipakai semua halaman (home, search, detail, person, statistik).
    Nama variabel lama (--sun, --sky, --sand, --ocean) dipertahankan sebagai
    alias supaya halaman lain ikut berganti tema tanpa perlu diubah satu-satu.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'pilem. -- Search Engine Film')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            /* Permukaan */
            --bg: #060b18;
            --bg-raised: #0b1326;
            --surface: #0e1830;
            --surface-2: #131f3b;
            --line: rgba(130, 160, 255, .13);
            --line-strong: rgba(130, 160, 255, .26);
            /* Teks */
            --text: #eef2fb;
            --text-dim: #9aa6c2;
            --text-mute: #6b7794;
            /* Aksen */
            --gold: #f7b928;
            --gold-soft: #ffd877;
            --cyan: #27c4e8;
            --red: #e03b3b;
            --blue: #2f7fe0;
            /* Alias nama lama (dipakai view lain) */
            --sun: var(--gold);
            --sand: var(--gold-soft);
            --sky: var(--cyan);
            --ocean: var(--surface);
            --midnight: var(--bg-raised);
            --white: #fff;
            --black: #000;
            --radius: 18px;
        }
        * { -webkit-font-smoothing: antialiased; }
        html { scroll-behavior: smooth; }
        body {
            background:
                radial-gradient(1100px 520px at 12% -8%, rgba(47, 127, 224, .16), transparent 60%),
                radial-gradient(900px 480px at 100% 0%, rgba(39, 196, 232, .08), transparent 60%),
                var(--bg);
            background-attachment: fixed;
            color: var(--text);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            padding-bottom: 5.5rem; /* ruang untuk mini player */
        }
        .container { max-width: 1240px; }
        a { color: inherit; }
        h1, h2, h3, .section-title { letter-spacing: -.02em; }
        :focus-visible { outline: 2px solid var(--cyan); outline-offset: 2px; border-radius: 6px; }

        /* ---------- Navbar ---------- */
        .navbar-pilem {
            background: rgba(6, 11, 24, .82);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
            position: sticky; top: 0; z-index: 1030;
        }
        .navbar-pilem .brand { font-weight: 800; font-size: 1.7rem; letter-spacing: -.04em; line-height: 1; text-decoration: none; color: var(--text); }
        .navbar-pilem .brand .dot { color: var(--gold); }
        .btn-filter-nav {
            background: rgba(255, 255, 255, .05);
            border: 1px solid var(--line-strong);
            color: var(--text);
            border-radius: 10px;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
            position: relative;
            flex-shrink: 0;
        }
        .btn-filter-nav:hover, .btn-filter-nav.active {
            background: rgba(39, 196, 232, .15);
            border-color: var(--cyan);
            color: var(--cyan);
        }
        .btn-filter-nav .filter-indicator {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 6px var(--gold);
        }
        .filter-navbar-panel {
            background: rgba(8, 15, 34, .97);
            backdrop-filter: blur(18px);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line-strong);
            box-shadow: 0 16px 40px rgba(0, 0, 0, .55);
            animation: fadeInDown .2s ease-out;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .letter-bar {
            scrollbar-width: thin;
        }
        .letter-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            height: 28px;
            padding: 0 .4rem;
            border-radius: 7px;
            font-size: .75rem;
            font-weight: 700;
            color: var(--text-dim);
            background: rgba(255, 255, 255, .04);
            border: 1px solid var(--line);
            text-decoration: none;
            transition: all .15s;
            cursor: pointer;
            font-family: inherit;
        }
        .letter-btn:hover {
            color: var(--text);
            border-color: var(--cyan);
            background: rgba(39, 196, 232, .14);
        }
        .letter-btn.active {
            background: linear-gradient(180deg, #ffc93d, #f2a900);
            color: #1a1200;
            border-color: var(--gold);
            box-shadow: 0 2px 8px rgba(247, 185, 40, .3);
        }
        .nav-group {
            display: flex; gap: .25rem; padding: .3rem;
            border: 1px solid var(--line); border-radius: 14px; background: rgba(255,255,255,.03);
        }
        .nav-pill {
            padding: .5rem 1rem; border-radius: 10px; font-size: .82rem; font-weight: 600;
            color: var(--text-dim); text-decoration: none; border: 1px solid transparent;
            transition: color .15s, background .15s;
        }
        .nav-pill:hover { color: var(--text); }
        .nav-pill.active { background: rgba(39,196,232,.12); border-color: rgba(39,196,232,.45); color: var(--text); }
        .nav-pill.accent { color: var(--gold); }
        .nav-search { position: relative; }
        .nav-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-mute); pointer-events: none; }
        .nav-search input { padding-left: 2.4rem; width: 300px; }


        /* ---------- Dropdown kustom (senada tema) ---------- */
        select.form-select { color-scheme: dark; }
        select.form-select option { background: #0e1830; color: #eef2fb; }
        .dd { position: relative; }
        .dd select { display: none; }
        .dd-btn {
            width: 100%; display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            padding: .3rem .6rem; min-height: 31px; text-align: left; cursor: pointer; font-family: inherit;
            background: rgba(255,255,255,.04); border: 1px solid var(--line); border-radius: 12px;
            color: var(--text); font-size: .88rem; transition: border-color .15s, background .15s, box-shadow .15s;
        }
        .dd-btn:hover { border-color: var(--line-strong); background: rgba(255,255,255,.07); }
        .dd.open .dd-btn, .dd-btn:focus-visible {
            outline: none; border-color: var(--cyan); background: rgba(255,255,255,.07);
            box-shadow: 0 0 0 3px rgba(39,196,232,.15);
        }
        .dd-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dd-caret { flex: none; color: var(--text-mute); transition: transform .18s, color .15s; }
        .dd.open .dd-caret { transform: rotate(180deg); color: var(--cyan); }
        .dd-menu {
            position: absolute; z-index: 1080; left: 0; right: 0; top: calc(100% + 6px);
            min-width: 100%; max-height: 260px; overflow-y: auto; padding: .3rem; margin: 0; list-style: none;
            background: var(--surface); border: 1px solid var(--line-strong); border-radius: 12px;
            box-shadow: 0 18px 40px rgba(0,0,0,.6);
            opacity: 0; transform: translateY(-4px); pointer-events: none; visibility: hidden;
            transition: opacity .14s, transform .14s, visibility .14s;
            scrollbar-width: thin; scrollbar-color: rgba(130,160,255,.3) transparent;
        }
        .dd.open .dd-menu { opacity: 1; transform: none; pointer-events: auto; visibility: visible; }
        .dd-menu::-webkit-scrollbar { width: 8px; }
        .dd-menu::-webkit-scrollbar-thumb { background: rgba(130,160,255,.28); border-radius: 8px; }
        .dd-opt {
            padding: .42rem .65rem; border-radius: 8px; font-size: .85rem; color: var(--text-dim);
            cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        }
        .dd-opt.active, .dd-opt:hover { background: rgba(39,196,232,.14); color: var(--text); }
        .dd-opt.selected { color: var(--gold-soft); font-weight: 700; }
        .dd-opt.selected::after {
            content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--gold);
            box-shadow: 0 0 6px var(--gold); flex: none;
        }

        /* ---------- Form & tombol ---------- */
        .search-input, .form-control, .form-select {
            background: rgba(255,255,255,.04); border: 1px solid var(--line);
            color: var(--text); border-radius: 12px; font-size: .88rem;
        }
        .search-input::placeholder, .form-control::placeholder { color: var(--text-mute); }
        .search-input:focus, .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,.07); border-color: var(--cyan); color: var(--text);
            box-shadow: 0 0 0 3px rgba(39,196,232,.15);
        }
        .btn-sun {
            background: linear-gradient(180deg, #ffc93d, #f2a900); color: #1a1200; font-weight: 700;
            border: none; border-radius: 12px; box-shadow: 0 6px 20px rgba(247,185,40,.22);
            transition: transform .12s, box-shadow .12s, filter .12s;
        }
        .btn-sun:hover { color: #1a1200; filter: brightness(1.06); transform: translateY(-1px); box-shadow: 0 10px 26px rgba(247,185,40,.3); }
        .btn-sun:active { transform: translateY(0); }
        .btn-outline-pilem {
            border: 1px solid var(--line-strong); color: var(--text); font-weight: 600;
            background: rgba(255,255,255,.03); border-radius: 12px; transition: background .15s, border-color .15s;
        }
        .btn-outline-pilem:hover { background: rgba(255,255,255,.08); color: var(--text); border-color: rgba(255,255,255,.35); }
        .btn-cyan { background: rgba(39,196,232,.14); border: 1px solid rgba(39,196,232,.5); color: var(--text); font-weight: 600; border-radius: 12px; }
        .btn-cyan:hover { background: rgba(39,196,232,.24); color: var(--text); }

        /* ---------- Chip, badge ---------- */
        .badge-studio {
            background: var(--blue); color: #fff; font-weight: 800; font-size: .62rem;
            padding: .3rem .55rem; border-radius: 7px; letter-spacing: .04em;
        }
        .badge-studio.mcu { background: var(--red); }
        .badge-studio.pixar { background: var(--blue); }
        .badge-studio.disney { background: #5b4bd6; }
        .rating-badge {
            background: rgba(6,11,24,.72); border: 1px solid rgba(247,185,40,.55); color: var(--gold-soft);
            font-weight: 700; border-radius: 999px; padding: .28rem .65rem; font-size: .78rem;
            backdrop-filter: blur(6px);
        }

        /* ---------- Kartu ---------- */
        .card-pilem {
            background: linear-gradient(180deg, rgba(255,255,255,.045), rgba(255,255,255,.02));
            border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden;
            transition: border-color .18s, transform .18s, box-shadow .18s; height: 100%;
        }
        .card-pilem:hover { border-color: rgba(39,196,232,.5); transform: translateY(-3px); }
        .card-pilem.active-feature { border-color: var(--cyan); box-shadow: 0 0 0 1px var(--cyan), 0 0 34px rgba(39,196,232,.22); }
        .card-pilem img.poster { width: 100%; aspect-ratio: 2/3; object-fit: cover; background: var(--surface); }
        .card-flat { background: rgba(255,255,255,.035); border: 1px solid var(--line); border-radius: var(--radius); }
        .panel { background: var(--bg-raised); border: 1px solid var(--line); border-radius: 22px; }
        .poster-fallback {
            aspect-ratio: 2/3; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(160deg, var(--surface-2), var(--surface)); color: var(--text-mute);
            font-weight: 800; text-align: center; padding: 1rem; font-size: .9rem;
        }

        /* ---------- Tipografi util ---------- */
        .section-title { font-weight: 800; font-size: 1.7rem; }
        .eyebrow { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--cyan); }
        .text-white-50, .text-dim { color: var(--text-dim) !important; }
        .text-sand { color: var(--gold-soft) !important; }
        .text-cyan { color: var(--cyan) !important; }
        .pilem-hr { border-color: var(--line); opacity: 1; }
        a.link-sky { color: var(--cyan); }
        .hero-tab { font-size: .8rem; padding: .35rem .75rem; border-radius: 10px; color: var(--text-mute); font-weight: 600; text-decoration: none; transition: color .15s; }
        .hero-tab:hover { color: var(--text); }
        .hero-tab.active { color: var(--gold-soft); box-shadow: inset 0 -2px 0 var(--gold); border-radius: 0; }

        /* ---------- Komponen lain (dipakai view lama) ---------- */
        .list-group-item { background: rgba(255,255,255,.04); border-color: var(--line); color: var(--text); }
        table.table-pilem { color: var(--text); --bs-table-bg: transparent; --bs-table-color: var(--text); }
        table.table-pilem th { color: var(--text-mute); font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; border-color: var(--line); padding: 1rem; }
        table.table-pilem td { border-color: var(--line); vertical-align: middle; padding: 1.1rem 1rem; }
        .provider-logo { width: 34px; height: 34px; border-radius: 8px; }
        .chat-bubble-user { color: var(--text); background: rgba(247,185,40,.12); border: 1px solid rgba(247,185,40,.3); border-radius: 14px 14px 4px 14px; padding: .6rem .9rem; max-width: 80%; font-size: .88rem; }
        .chat-bubble-bot { color: var(--text); background: rgba(255,255,255,.05); border: 1px solid var(--line); border-radius: 14px 14px 14px 4px; padding: .6rem .9rem; max-width: 85%; font-size: .88rem; line-height: 1.55; }
        .quick-prompt {
            display: flex; justify-content: space-between; align-items: center; gap: .75rem; width: 100%; text-align: left;
            background: rgba(255,255,255,.03); border: 1px solid var(--line); color: var(--text-dim);
            border-radius: 12px; padding: .8rem 1rem; margin-bottom: .6rem; font-size: .84rem; cursor: pointer; transition: .15s;
        }
        .quick-prompt:hover { background: rgba(39,196,232,.08); border-color: rgba(39,196,232,.4); color: var(--text); }
        .filter-badge {
            display: inline-flex; align-items: center; gap: .4rem; background: rgba(255,255,255,.04);
            border: 1px solid var(--line); border-radius: 999px; padding: .32rem .85rem; font-size: .78rem;
            color: var(--text-dim); cursor: pointer; transition: .15s; text-decoration: none;
        }
        .filter-badge:hover, .filter-badge.active { background: rgba(39,196,232,.14); border-color: var(--cyan); color: var(--text); }

        /* ---------- Footer ---------- */
        footer.pilem-footer { border-top: 1px solid var(--line); background: rgba(0,0,0,.25); }
        footer.pilem-footer a { text-decoration: none; color: var(--text-dim); transition: color .15s; }
        footer.pilem-footer a:hover { color: var(--text); }

        @media (max-width: 991.98px) {
            .nav-search input { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
    @stack('meta')
    @stack('styles')
</head>
<body>

<nav class="navbar-pilem py-3">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn-filter-nav {{ request()->anyFilled(['huruf','genre','tahun','rated','sort']) ? 'active' : '' }}" id="btn-toggle-filter" aria-label="Buka Filter Film" title="Filter & Urutkan Film">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="4" y1="7" x2="20" y2="7"></line>
                    <line x1="4" y1="12" x2="20" y2="12"></line>
                    <line x1="4" y1="17" x2="20" y2="17"></line>
                </svg>
                @if (request()->anyFilled(['huruf','genre','tahun','rated','sort']))
                    <span class="filter-indicator" title="Filter aktif"></span>
                @endif
            </button>
            <a href="{{ route('home') }}" class="brand">pilem<span class="dot">.</span></a>
        </div>

        <div class="nav-group">
            <a href="{{ route('home') }}" class="nav-pill {{ request()->routeIs('home') && !request()->anyFilled(['q','genre','tahun','negara','rated','huruf','sort']) ? 'active' : '' }}">Now Playing</a>
            <a href="{{ route('film.statistik') }}" class="nav-pill {{ request()->routeIs('film.statistik') ? 'active' : '' }}">Statistik</a>
            <a href="{{ route('home') }}#ask-pilmy" class="nav-pill accent">Ask Pilmy</a>
        </div>

        <form action="{{ route('home') }}" method="GET" class="nav-search" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm search-input"
                   placeholder="Cari film, sutradara, aktor..." aria-label="Cari film">
        </form>
    </div>

    {{-- ======================= EXPANDABLE FILTER NAVBAR ======================= --}}
    <div id="filter-navbar-panel" class="filter-navbar-panel mt-3 {{ request()->anyFilled(['huruf','genre','tahun','rated','sort']) ? 'show' : '' }}"
         style="display: {{ request()->anyFilled(['huruf','genre','tahun','rated','sort']) ? 'block' : 'none' }};">
        <div class="container py-3">
            <form action="{{ route('home') }}" method="GET" id="filter-nav-form">
                {{-- Header Filter --}}
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold small text-sand" style="letter-spacing: .06em; text-transform: uppercase;">
                             Filter &amp; Urutkan Katalog Film
                        </span>
                        @if (request()->anyFilled(['q','huruf','genre','tahun','rated','sort']))
                            <span class="badge border border-info border-opacity-50 text-cyan bg-info bg-opacity-10" style="font-size: .68rem;">
                                Filter Aktif
                            </span>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if (request()->anyFilled(['q','huruf','genre','tahun','rated','sort']))
                            <a href="{{ route('home') }}" class="btn btn-sm btn-outline-pilem text-danger-emphasis py-1 px-2" style="font-size: .75rem;">
                                Reset Filter
                            </a>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-pilem py-1 px-2" id="btn-close-filter" aria-label="Tutup Filter" style="font-size: .75rem;">
                            Tutup
                        </button>
                    </div>
                </div>

                {{-- Filter berdasarkan Judul A-Z (Alphabet Bar) --}}
                <div class="filter-az-section mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label mb-0 small text-dim fw-semibold" style="font-size: .75rem;">
                            Filter berdasarkan Judul A-Z:
                        </label>
                        @if (request('huruf'))
                            <span class="small text-sand" style="font-size: .72rem;">
                                Awalan: <strong>{{ request('huruf') }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="letter-bar d-flex flex-wrap gap-1">
                        <input type="hidden" name="huruf" id="input-huruf" value="{{ request('huruf') }}">
                        <button type="button" class="letter-btn {{ !request('huruf') ? 'active' : '' }}" onclick="selectLetter('')" title="Semua Huruf">Semua</button>
                        @foreach (range('A', 'Z') as $char)
                            <button type="button" class="letter-btn {{ request('huruf') === $char ? 'active' : '' }}" onclick="selectLetter('{{ $char }}')" title="Judul berawalan {{ $char }}">{{ $char }}</button>
                        @endforeach
                        <button type="button" class="letter-btn {{ request('huruf') === '#' ? 'active' : '' }}" onclick="selectLetter('#')" title="Angka atau Simbol">#</button>
                    </div>
                </div>

                {{-- Input Baris: Judul, Genre, Tahun, Rating Usia, dan Urutkan --}}
                <div class="row g-2 align-items-end">
                    {{-- 1. Judul --}}
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Judul Film</label>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm search-input" placeholder="Cari judul..." autocomplete="off">
                    </div>

                    {{-- 2. Genre --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Genre</label>
                        <select name="genre" class="form-select form-select-sm">
                            <option value="">Semua Genre</option>
                            @php
                                $daftarGenre = [
                                    'Action', 'Adventure', 'Animation', 'Comedy', 'Crime',
                                    'Documentary', 'Drama', 'Family', 'Fantasy', 'History',
                                    'Horror', 'Music', 'Mystery', 'Romance', 'Science Fiction',
                                    'Thriller', 'TV Movie', 'Western'
                                ];
                            @endphp
                            @foreach ($daftarGenre as $g)
                                <option value="{{ $g }}" {{ strtolower(request('genre')) === strtolower($g) ? 'selected' : '' }}>
                                    {{ $g }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 3. Tahun --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Tahun</label>
                        <select name="tahun" class="form-select form-select-sm">
                            <option value="">Semua Tahun</option>
                            @for ($yr = 2026; $yr >= 1983; $yr--)
                                <option value="{{ $yr }}" {{ request('tahun') == $yr ? 'selected' : '' }}>
                                    {{ $yr }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    {{-- 4. Rating Usia --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Rating Usia</label>
                        <select name="rated" class="form-select form-select-sm">
                            <option value="">Semua Usia</option>
                            <option value="G" {{ request('rated') === 'G' ? 'selected' : '' }}>G (Semua Umur)</option>
                            <option value="PG" {{ request('rated') === 'PG' ? 'selected' : '' }}>PG (Bimbingan Orang Tua)</option>
                            <option value="PG-13" {{ request('rated') === 'PG-13' ? 'selected' : '' }}>PG-13 (Remaja 13+)</option>
                            <option value="R" {{ request('rated') === 'R' ? 'selected' : '' }}>R (Dewasa 17+)</option>
                            <option value="NR" {{ request('rated') === 'NR' ? 'selected' : '' }}>NR (Not Rated)</option>
                        </select>
                    </div>

                    {{-- 5. Urutkan --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Urutkan</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="title_asc" {{ request('sort') === 'title_asc' || !request('sort') ? 'selected' : '' }}>Judul (A &rarr; Z)</option>
                            <option value="title_desc" {{ request('sort') === 'title_desc' ? 'selected' : '' }}>Judul (Z &rarr; A)</option>
                            <option value="year_desc" {{ request('sort') === 'year_desc' ? 'selected' : '' }}>Tahun Rilis (Terbaru)</option>
                            <option value="year_asc" {{ request('sort') === 'year_asc' ? 'selected' : '' }}>Tahun Rilis (Terlama)</option>
                            <option value="rating_desc" {{ request('sort') === 'rating_desc' ? 'selected' : '' }}>Rating Tertinggi</option>
                        </select>
                    </div>

                    {{-- Tombol Submit --}}
                    <div class="col-12 col-md-1">
                        <button type="submit" class="btn btn-sun btn-sm w-100 py-1" style="font-weight: 700; height: 31px;">
                            Terapkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</nav>

<main class="container py-5">
    @yield('content')
</main>

<footer class="pilem-footer py-5 mt-4">
    <div class="container d-flex flex-wrap justify-content-between gap-4">
        <div style="max-width: 340px;">
            <div class="brand mb-2" style="font-weight:800; font-size:1.6rem; letter-spacing:-.04em;">pilem<span style="color: var(--gold);">.</span></div>
            <p class="text-dim small mb-0">Katalog film Disney, Marvel, dan Pixar yang dibangun di atas data graph (RDF), lengkap dengan trailer, soundtrack, dan asisten AI.</p>
        </div>
        <div class="d-flex gap-5 flex-wrap">
            <div>
                <div class="fw-bold small mb-2">Navigasi</div>
                <div class="d-flex flex-column gap-1 small">
                    <a href="{{ route('home') }}">Now Playing</a>
                    <a href="{{ route('film.search') }}">Cari Film</a>
                    <a href="{{ route('home') }}#ask-pilmy">Ask Pilmy</a>
                    <a href="{{ route('film.statistik') }}">Statistik</a>
                </div>
            </div>
            <div>
                <div class="fw-bold small mb-2">Sumber Data</div>
                <div class="d-flex flex-column gap-1 small text-dim">
                    <span>CineGraph (film.ttl + ontology)</span>
                    <span>TMDB, DBpedia, Wikidata</span>
                    <span>iTunes, JustWatch via TMDB</span>
                </div>
            </div>
        </div>
    </div>
    <div class="container text-mute small mt-4 pt-3" style="border-top:1px solid var(--line); color: var(--text-mute);">
        &copy; {{ date('Y') }} pilem. Tugas Besar Web Semantik &mdash; bukan produk komersial.
    </div>
</footer>

@stack('player')

@unless ($hideChatWidget ?? false)
    @include('partials.chatbot-widget')
@endunless

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('btn-toggle-filter');
    const filterPanel = document.getElementById('filter-navbar-panel');
    const closeBtn = document.getElementById('btn-close-filter');
    const filterForm = document.getElementById('filter-nav-form');
    const inputHuruf = document.getElementById('input-huruf');

    if (toggleBtn && filterPanel) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const isHidden = filterPanel.style.display === 'none' || filterPanel.style.display === '';
            filterPanel.style.display = isHidden ? 'block' : 'none';
            toggleBtn.classList.toggle('active', isHidden);
            if (isHidden) {
                const firstInput = filterPanel.querySelector('input[name="q"]');
                if (firstInput) firstInput.focus();
            }
        });
    }

    if (closeBtn && filterPanel) {
        closeBtn.addEventListener('click', function() {
            filterPanel.style.display = 'none';
            if (toggleBtn) toggleBtn.classList.remove('active');
        });
    }


    // ---------- Dropdown kustom: ganti <select> di panel filter ----------
    document.querySelectorAll('#filter-navbar-panel select.form-select').forEach(function(sel) {
        const wrap = document.createElement('div');
        wrap.className = 'dd';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dd-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.innerHTML = '<span class="dd-label"></span><svg class="dd-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        const menu = document.createElement('ul');
        menu.className = 'dd-menu';
        menu.setAttribute('role', 'listbox');
        wrap.append(btn, menu);

        const label = btn.querySelector('.dd-label');
        const items = Array.from(sel.options).map(function(o, i) {
            const li = document.createElement('li');
            li.className = 'dd-opt';
            li.setAttribute('role', 'option');
            li.textContent = o.textContent.trim();
            li.addEventListener('click', function() { choose(i); close(); btn.focus(); });
            menu.appendChild(li);
            return li;
        });
        let cursor = sel.selectedIndex;

        function paint() {
            label.textContent = sel.options[sel.selectedIndex].textContent.trim();
            items.forEach(function(li, i) {
                li.classList.toggle('selected', i === sel.selectedIndex);
                li.classList.toggle('active', i === cursor);
            });
        }
        function choose(i) { sel.selectedIndex = i; cursor = i; paint(); }
        function open() {
            document.querySelectorAll('.dd.open').forEach(function(d) { d.classList.remove('open'); });
            wrap.classList.add('open'); cursor = sel.selectedIndex; paint();
            const a = items[cursor]; if (a) menu.scrollTop = a.offsetTop - menu.clientHeight / 2;
        }
        function close() { wrap.classList.remove('open'); }
        function move(d) {
            cursor = Math.max(0, Math.min(items.length - 1, cursor + d)); paint();
            items[cursor].scrollIntoView({ block: 'nearest' });
        }

        btn.addEventListener('click', function() { wrap.classList.contains('open') ? close() : open(); });
        btn.addEventListener('keydown', function(e) {
            const isOpen = wrap.classList.contains('open');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault(); if (!isOpen) open(); else move(e.key === 'ArrowDown' ? 1 : -1);
            } else if ((e.key === 'Enter' || e.key === ' ') && isOpen) {
                e.preventDefault(); choose(cursor); close();
            } else if (e.key === 'Escape' && isOpen) { close(); }
        });
        document.addEventListener('click', function(e) { if (!wrap.contains(e.target)) close(); });
        paint();
    });

    window.selectLetter = function(char) {
        if (inputHuruf && filterForm) {
            inputHuruf.value = char;
            filterForm.submit();
        }
    };
});
</script>

@stack('scripts')
</body>
</html>
