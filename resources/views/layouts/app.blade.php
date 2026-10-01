{{--
    Layout utama -- tema gelap "pilem" (dari Figma tim), dipakai semua
    halaman (search, detail, statistik, person, DAN home). Sebelumnya
    home.blade.php punya <head>/nav/footer sendiri yang duplikat CSS-nya --
    sekarang satu sumber di sini saja.
--}}


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'pilem. -- Search Engine Film')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --white: #FFFFFF;
            --sun: #F2A900;
            --ocean: #003087;
            --sand: #FFE264;
            --sky: #307FE2;
            --midnight: #00205B;
            --black: #000000;
        }
        body {
            background: linear-gradient(180deg, var(--sky) 0%, var(--ocean) 45%, var(--black) 100%);
            background-attachment: fixed;
            color: var(--white);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
        }
        a { color: inherit; }
        a.link-sky { color: var(--sky); }
        .navbar-pilem {
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(6px);
            position: sticky; top: 0; z-index: 1030;
        }
        .navbar-pilem .brand { font-weight: 800; font-size: 1.3rem; }
        .navbar-pilem .brand .dot { color: var(--sun); }
        .nav-pill { padding: .4rem .9rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .nav-pill.active { background: var(--sky); color: var(--white); }
        .nav-pill.accent { color: var(--sun); }
        .search-input {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            color: var(--white); border-radius: 999px;
        }
        .search-input::placeholder { color: rgba(255,255,255,0.5); }
        .search-input:focus { background: rgba(255,255,255,0.12); border-color: var(--sky); color: var(--white); box-shadow: none; }
        .btn-sun { background: var(--sun); color: var(--black); font-weight: 700; border: none; }
        .btn-sun:hover { background: var(--sand); color: var(--black); }
        .btn-outline-pilem { border: 1px solid rgba(255,255,255,0.4); color: var(--white); font-weight: 600; background: transparent; }
        .btn-outline-pilem:hover { background: rgba(255,255,255,0.1); color: var(--white); }
        .badge-studio {
            background: var(--sky); color: var(--white); font-weight: 700; font-size: .7rem;
            padding: .3rem .6rem; border-radius: 6px;
        }
        .rating-badge {
            background: rgba(0,0,0,0.55); border: 1px solid var(--sand); color: var(--sand);
            font-weight: 700; border-radius: 999px; padding: .3rem .7rem; font-size: .85rem;
        }
        .card-pilem {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px; overflow: hidden; transition: transform .15s, border-color .15s;
            height: 100%;
        }
        .card-pilem:hover { transform: translateY(-4px); border-color: var(--sky); }
        .card-pilem.active-feature { border-color: var(--sun); box-shadow: 0 0 0 2px var(--sun); }
        .card-pilem img.poster { width: 100%; aspect-ratio: 2/3; object-fit: cover; }
        .card-flat {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px;
        }
        .section-title { font-weight: 800; font-size: 1.4rem; }
        .pilem-hr { border-color: rgba(255,255,255,0.15); }
        .hero-tab { font-size: .8rem; padding: .3rem .7rem; border-radius: 8px; color: rgba(255,255,255,0.6); }
        .hero-tab.active { background: var(--sky); color: var(--white); }
        .text-white-50 { color: rgba(255,255,255,0.55) !important; }
        .text-sand { color: var(--sand) !important; }
        .list-group-item {
            background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1); color: var(--white);
        }
        .form-control, .form-select {
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: var(--white);
        }
        .form-control:focus, .form-select:focus { background: rgba(255,255,255,0.12); color: var(--white); border-color: var(--sky); box-shadow: none; }
        .form-control::placeholder { color: rgba(255,255,255,0.4); }
        table.table-pilem { color: var(--white); }
        table.table-pilem th { color: var(--sand); font-size: .75rem; text-transform: uppercase; border-color: rgba(255,255,255,0.15); }
        table.table-pilem td { border-color: rgba(255,255,255,0.1); vertical-align: middle; }
        .provider-logo { width: 34px; height: 34px; border-radius: 6px; }
        .chat-bubble-user { background: var(--sky); border-radius: 12px 12px 0 12px; padding: .5rem .8rem; max-width: 80%; }
        .chat-bubble-bot { background: rgba(255,255,255,0.08); border-radius: 12px 12px 12px 0; padding: .5rem .8rem; max-width: 85%; }
        .quick-prompt {
            display: block; width: 100%; text-align: left; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12); color: var(--white); border-radius: 10px;
            padding: .6rem .8rem; margin-bottom: .5rem; font-size: .85rem; cursor: pointer;
        }
        .quick-prompt:hover { background: rgba(255,255,255,0.14); }
        footer.pilem-footer { background: rgba(0,0,0,0.5); border-top: 1px solid rgba(255,255,255,0.1); }
    </style>
    @stack('meta')
    @stack('styles')
</head>
<body>

<nav class="navbar navbar-pilem py-3">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-3">
        <a href="{{ route('home') }}" class="brand text-decoration-none text-white">pilem<span class="dot">.</span></a>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('home') }}" class="nav-pill {{ request()->routeIs('home') && !request()->anyFilled(['q','genre','tahun','negara']) ? 'active' : '' }} text-decoration-none">Now Playing</a>
            <a href="{{ route('film.statistik') }}" class="nav-pill {{ request()->routeIs('film.statistik') ? 'active' : '' }} text-decoration-none">Statistik</a>
            <a href="{{ route('home') }}#ask-pilmy" class="nav-pill accent text-decoration-none">Ask Pilmy</a>
        </div>

        <form action="{{ route('home') }}" method="GET" class="d-none d-md-block">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm search-input" style="width:260px;"
                   placeholder="Cari film, sutradara, OST...">
        </form>
    </div>
</nav>

<main class="container py-5">
    @yield('content')
</main>

<footer class="pilem-footer py-5 mt-4">
    <div class="container d-flex flex-wrap justify-content-between gap-4">
        <div style="max-width: 320px;">
            <div class="brand mb-2">pilem<span class="dot" style="color: var(--sun);">.</span></div>
            <p class="text-white-50 small">"Pile of Movies" &mdash; katalog film Disney, Marvel, dan Pixar, lengkap dengan trailer, soundtrack, dan asisten AI.</p>
        </div>
        <div>
            <div class="fw-bold small mb-2 text-sand">Navigasi</div>
            <div class="d-flex flex-column gap-1 small text-white-50">
                <a href="{{ route('home') }}" class="text-decoration-none text-white-50">Now Playing</a>
                <a href="{{ route('film.search') }}" class="text-decoration-none text-white-50">Cari Film</a>
                <a href="{{ route('home') }}#ask-pilmy" class="text-decoration-none text-white-50">Ask Pilmy</a>
                <a href="{{ route('film.statistik') }}" class="text-decoration-none text-white-50">Statistik</a>
            </div>
        </div>
        <div>
            <div class="fw-bold small mb-2 text-sand">Sumber Data</div>
            <div class="d-flex flex-column gap-1 small text-white-50">
                <span>TMDB, DBpedia, Wikidata</span>
                <span>iTunes, JustWatch (via TMDB)</span>
            </div>
        </div>
    </div>
    <div class="text-center text-white-50 small mt-4">&copy; {{ date('Y') }} pilem. Tugas Besar Web Semantik &mdash; bukan produk komersial.</div>
</footer>

@unless ($hideChatWidget ?? false)
    @include('partials.chatbot-widget')
@endunless

@stack('scripts')
</body>
</html>