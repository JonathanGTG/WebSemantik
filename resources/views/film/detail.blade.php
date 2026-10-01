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
    @if ($film['poster'])
        <meta property="og:image" content="{{ $film['poster'] }}">
    @endif
    <meta property="og:description" content="{{ Str::limit($film['sinopsis'] ?? ('Detail film ' . $film['nama']), 200) }}">
    <meta property="og:url" content="{{ url()->current() }}">
@endpush

@push('styles')
<style>
    .detail-hero { padding: 2rem 0 1.5rem; }
    .genre-tag {
        display: inline-block; font-size: .72rem; font-weight: 700;
        padding: .25rem .65rem; border-radius: 6px; background: var(--sky);
        color: #fff; text-decoration: none; transition: background .15s;
    }
    .genre-tag:hover { background: var(--sun); color: #000; }
    .meta-row { display: flex; flex-wrap: wrap; gap: .3rem .8rem; font-size: .9rem; color: rgba(255,255,255,0.6); margin-bottom: 1rem; }
    .meta-row strong { color: #fff; }
    .meta-row .sep { color: rgba(255,255,255,0.2); }
    .section-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--sand); margin-bottom: .75rem; }
    .cast-card {
        text-align: center; text-decoration: none; color: inherit;
        display: flex; flex-direction: column; align-items: center; gap: .4rem;
    }
    .cast-card img, .cast-card .cast-placeholder {
        width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
        border: 2px solid transparent; transition: border-color .15s;
    }
    .cast-card .cast-placeholder {
        background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
    }
    .cast-card:hover img, .cast-card:hover .cast-placeholder { border-color: var(--sky); }
    .cast-name { font-size: .7rem; line-height: 1.2; }
    .cast-role-badge {
        font-size: .6rem; padding: .15rem .5rem; border-radius: 999px;
        background: rgba(255,255,255,0.12); font-weight: 600;
    }
    .cast-role-badge.sutradara { background: var(--sky); }
    .ost-track {
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px; padding: .75rem; transition: border-color .15s;
    }
    .ost-track:hover { border-color: var(--sky); }
    audio { border-radius: 6px; height: 32px; }
    .review-card {
        background: rgba(255,255,255,0.04); border-left: 3px solid var(--sky);
        border-radius: 0 10px 10px 0; padding: .8rem 1rem;
    }
    .sejenis-card .sejenis-title { font-size: .78rem; font-weight: 600; }
    .sejenis-card .sejenis-year { font-size: .68rem; color: rgba(255,255,255,0.5); }
    .data-source-note { font-size: .7rem; color: rgba(255,255,255,0.35); }
    .boxoffice-card {
        background: rgba(242,169,0,0.08); border: 1px solid rgba(242,169,0,0.25);
        border-radius: 10px; padding: .75rem 1rem;
    }
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
            @if ($film['poster'])
                <div class="position-relative">
                    <img src="{{ $film['poster'] }}" property="image"
                         class="img-fluid rounded-3 shadow w-100" alt="{{ $film['nama'] }}"
                         style="aspect-ratio:2/3; object-fit:cover;">
                    @if (!empty($film['rating']))
                        <span class="rating-badge position-absolute top-0 end-0 m-2">
                            ⭐ {{ number_format($film['rating'], 1) }}/10
                        </span>
                    @endif
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
                @if ($film['durasi'])
                    <span property="duration">{{ pilemFormatDurasi($film['durasi']) }}</span>
                    <span class="sep">·</span>
                @endif
                @if ($film['negara'])
                    <span property="countryOfOrigin">{{ $film['negara'] }}</span>
                @endif
                @if ($film['komposer'])
                    <span class="sep">·</span>
                    <span>🎼 <span property="musicBy">{{ $film['komposer'] }}</span></span>
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
                    <a href="#trailer" class="btn btn-sun px-4">▶ Tonton Trailer</a>
                @endif
                <a href="#soundtrack" class="btn btn-outline-pilem px-4">🎵 Dengar OST</a>
                @if (!empty($film['externalData']['streaming']))
                    <a href="#streaming" class="btn btn-outline-pilem px-4">📺 Streaming</a>
                @endif
            </div>

            {{-- Box Office --}}
            @if ($film['boxOfficeUSD'])
                <div class="boxoffice-card mt-4">
                    <div class="section-label mb-1">💰 Box Office</div>
                    <div class="fw-bold">
                        ${{ number_format($film['boxOfficeUSD'], 0, ',', '.') }} USD
                        @if (!empty($film['externalData']['boxOfficeIDR']))
                            &mdash; <span class="text-sand">Rp{{ number_format($film['externalData']['boxOfficeIDR'], 0, ',', '.') }}</span>
                        @endif
                    </div>
                    @if (!empty($film['externalData']['exchangeRate']))
                        <div class="data-source-note mt-1">
                            Kurs: 1 USD = Rp{{ number_format($film['externalData']['exchangeRate'], 0, ',', '.') }}
                            @if (!empty($film['externalData']['exchangeRateAt']))
                                · per {{ $film['externalData']['exchangeRateAt'] }} (ExchangeRate API)
                            @endif
                        </div>
                    @else
                        <div class="data-source-note mt-1">Konversi kurs sementara tidak tersedia.</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ======================= SINOPSIS ======================= --}}
    @if ($film['sinopsis'])
        <hr class="pilem-hr my-4">
        <div class="section-label">Sinopsis</div>
        <p property="description" class="text-white-50" style="max-width:800px; line-height:1.75;">
            {{ $film['sinopsis'] }}
        </p>
        @if ($film['sameAs'])
            <div class="data-source-note">
                Sumber: <a href="{{ $film['sameAs'] }}" target="_blank" rel="noopener" class="text-sand text-decoration-none">DBpedia</a>
                via federated SPARQL (<code>owl:sameAs</code>)
            </div>
        @endif
    @endif

    {{-- ======================= CAST & CREW ======================= --}}
    @if (!empty($film['castCrew']))
        <hr class="pilem-hr my-4">
        <div class="section-label">🎭 Pemeran &amp; Sutradara</div>
        <div class="d-flex flex-wrap gap-3">
            @foreach ($film['castCrew'] as $orang)
                <a href="{{ route('person.detail', $orang['id']) }}"
                   class="cast-card"
                   property="{{ $orang['peran'] === 'Sutradara' ? 'director' : 'actor' }}">
                    @if ($orang['foto'])
                        <img src="{{ $orang['foto'] }}" alt="{{ $orang['nama'] }}">
                    @else
                        <div class="cast-placeholder">{{ $orang['peran'] === 'Sutradara' ? '🎬' : '🎭' }}</div>
                    @endif
                    <div class="cast-name">{{ Str::limit($orang['nama'], 16) }}</div>
                    <span class="cast-role-badge {{ strtolower($orang['peran']) }}">{{ $orang['peran'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ======================= TRAILER ======================= --}}
    @if (!empty($film['externalData']['trailerKey']))
        <hr class="pilem-hr my-4">
        <div class="section-label" id="trailer">🎬 Trailer Resmi</div>
        <div class="ratio ratio-16x9 rounded-4 overflow-hidden" style="max-width: 860px;">
            <iframe src="https://www.youtube.com/embed/{{ $film['externalData']['trailerKey'] }}"
                    title="Trailer {{ $film['nama'] }}" allowfullscreen style="border:0;"></iframe>
        </div>
        <div class="data-source-note mt-2">Trailer via TMDB · YouTube</div>
    @endif

    {{-- ======================= OST + STREAMING + REVIEW ======================= --}}
    <hr class="pilem-hr my-4">
    <div class="row g-4">

        {{-- OST --}}
        <div class="col-lg-4" id="soundtrack">
            <div class="section-label">🎵 Soundtrack (iTunes)</div>
            @if (!empty($film['externalData']['soundtrack']))
                <div class="d-flex flex-column gap-2">
                    @foreach (array_slice($film['externalData']['soundtrack'], 0, 5) as $track)
                        <div class="ost-track">
                            <div class="fw-semibold" style="font-size:.85rem;">{{ $track['trackName'] ?? '-' }}</div>
                            <div class="text-white-50 small mb-2">{{ $track['artistName'] ?? '' }}</div>
                            @if (!empty($track['previewUrl']))
                                <audio controls preload="none" class="w-100">
                                    <source src="{{ $track['previewUrl'] }}" type="audio/mp4">
                                </audio>
                                <div class="data-source-note mt-1">
                                    Preview 30 detik ·
                                    <a href="https://open.spotify.com/search/{{ urlencode(trim(($track['trackName'] ?? '') . ' ' . ($track['artistName'] ?? ''))) }}"
                                       target="_blank" rel="noopener" class="text-sand">Cari di Spotify →</a>
                                </div>
                            @else
                                <div class="data-source-note">Preview tidak tersedia.</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card-flat p-3 text-center">
                    <div class="text-white-50 small">Data soundtrack tidak tersedia.</div>
                </div>
            @endif
        </div>

        {{-- Streaming --}}
        <div class="col-lg-4" id="streaming">
            <div class="section-label">📺 Streaming (Region Indonesia)</div>
            @php
                $streaming     = $film['externalData']['streaming'] ?? null;
                $streamingLink = $streaming['link'] ?? null;
                $kategoriLabel = ['flatrate' => 'Langganan', 'rent' => 'Sewa', 'buy' => 'Beli'];
                $adaProvider   = $streaming && (!empty($streaming['flatrate']) || !empty($streaming['rent']) || !empty($streaming['buy']));
            @endphp
            @if ($adaProvider)
                @foreach ($kategoriLabel as $kategori => $label)
                    @if (!empty($streaming[$kategori]))
                        <div class="mb-3">
                            <div class="text-white-50 small fw-bold mb-2">{{ $label }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($streaming[$kategori] as $provider)
                                    <a href="{{ $streamingLink ?? '#' }}" target="_blank" rel="noopener"
                                       title="{{ $provider['provider_name'] }}"
                                       style="display:flex; flex-direction:column; align-items:center; gap:.3rem; text-decoration:none;">
                                        <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}"
                                             alt="{{ $provider['provider_name'] }}" class="provider-logo">
                                        <span style="font-size:.6rem; color:rgba(255,255,255,0.5);">
                                            {{ Str::limit($provider['provider_name'], 12) }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                <div class="data-source-note">
                    Data oleh <a href="https://www.justwatch.com/" target="_blank" rel="noopener" class="text-sand">JustWatch</a>
                    via TMDB.
                </div>
            @else
                <div class="card-flat p-3 text-center">
                    <div class="text-white-50 small">Belum tersedia data streaming untuk wilayah Indonesia.</div>
                </div>
            @endif
        </div>

        {{-- Review --}}
        <div class="col-lg-4">
            <div class="section-label">💬 Review Penonton (TMDB)</div>
            @if (!empty($film['externalData']['reviews']))
                <div class="d-flex flex-column gap-3">
                    @foreach (array_slice($film['externalData']['reviews'], 0, 3) as $review)
                        <div class="review-card">
                            <div class="text-white-50 small mb-1">{{ Str::limit($review['content'], 180) }}</div>
                            <div class="text-sand small fw-semibold">&mdash; {{ $review['author'] }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card-flat p-3 text-center">
                    <div class="text-white-50 small">Belum ada review.</div>
                </div>
            @endif
        </div>
    </div>

    {{-- ======================= FILM SEJENIS ======================= --}}
    @if (!empty($film['filmSejenis']))
        <hr class="pilem-hr my-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="section-label mb-0">🎯 Film Sejenis</div>
                <div class="data-source-note">Berdasarkan sutradara atau genre yang sama — SPARQL graph traversal</div>
            </div>
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
                            @if ($mirip['poster'])
                                <img src="{{ $mirip['poster'] }}" class="poster" alt="{{ $mirip['nama'] }}">
                            @else
                                <div class="d-flex align-items-center justify-content-center"
                                     style="aspect-ratio:2/3; background: rgba(255,255,255,0.05);">
                                    <span style="font-size:1.8rem; opacity:.3;">🎬</span>
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