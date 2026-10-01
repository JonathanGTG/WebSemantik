{{--
    Halaman utama ("Now Playing") -- sekarang EXTEND layouts.app (tema
    gelap terpusat, sama dengan halaman lain). $hideChatWidget di-set true
    di bawah karena halaman ini sudah punya "Ask Pilmy" inline sendiri,
    jangan sampai widget mengambang dobel muncul.
--}}
@extends('layouts.app')
@php($hideChatWidget = true)

@section('title', $isSearchMode ? 'Hasil Pencarian — pilem.' : 'pilem. — Now Playing')

@push('styles')
<style>
    .home-search-bar { margin-bottom: 2rem; }
    .home-search-bar .form-control { font-size: 1rem; }
    .filter-badge {
        display: inline-flex; align-items: center; gap:.4rem;
        background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15);
        border-radius: 999px; padding:.25rem .75rem; font-size:.78rem;
        color: rgba(255,255,255,0.65); cursor: pointer;
        transition: background .15s, border-color .15s;
    }
    .filter-badge:hover, .filter-badge.active { background: var(--sky); border-color: var(--sky); color:#fff; }
    .result-count { font-size:.85rem; color: rgba(255,255,255,0.5); }
    .film-card-list {
        display: flex; gap: 1rem; align-items: flex-start;
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 14px; padding: 1rem; transition: border-color .15s, transform .15s;
        text-decoration: none; color: inherit;
    }
    .film-card-list:hover { border-color: var(--sky); transform: translateX(4px); color: inherit; }
    .film-card-list img { width: 60px; height: 90px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
    .film-card-list .no-poster {
        width: 60px; height: 90px; background: rgba(255,255,255,0.08); border-radius: 8px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size:1.5rem;
    }
</style>
@endpush

@section('content')

{{-- ======================= SEARCH BAR UNIVERSAL ======================= --}}
<div class="home-search-bar">
    <form method="GET" action="{{ route('home') }}" id="home-search-form" class="row g-2 mb-2">
        <div class="col-12 col-md-6">
            <input type="text" name="q" id="home-search-q"
                   class="form-control search-input"
                   placeholder="🔍  Cari judul film, sutradara..."
                   value="{{ $keyword }}" autocomplete="off">
        </div>
        <div class="col-4 col-md-2">
            <input type="text" name="genre" class="form-control search-input"
                   placeholder="Genre" value="{{ $genre }}">
        </div>
        <div class="col-4 col-md-2">
            <input type="number" name="tahun" class="form-control search-input"
                   placeholder="Tahun" value="{{ $tahun }}" min="1900" max="{{ date('Y') }}">
        </div>
        <div class="col-4 col-md-1">
            <input type="text" name="negara" class="form-control search-input"
                   placeholder="Negara" value="{{ $negara }}">
        </div>
        <div class="col-12 col-md-1">
            <button type="submit" class="btn btn-sun w-100">Cari</button>
        </div>
    </form>
    <div class="d-flex flex-wrap gap-2">
        @foreach (['Action', 'Adventure', 'Animation', 'Comedy', 'Drama', 'Science Fiction', 'Family'] as $g)
            <button type="button" class="filter-badge {{ strtolower($genre) === strtolower($g) ? 'active' : '' }}"
                    onclick="setHomeGenre('{{ $g }}')">{{ $g }}</button>
        @endforeach
        @if ($isSearchMode)
            <a href="{{ route('home') }}" class="filter-badge" style="color:rgba(255,120,120,.8); border-color:rgba(255,100,100,.3);">✕ Kembali ke Now Playing</a>
        @endif
    </div>
</div>

{{-- ======================= SEARCH RESULTS ======================= --}}
@if ($isSearchMode)
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first('sparql') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <span class="result-count">
            <strong class="text-white">{{ count($films) }}</strong> film ditemukan
            @if ($keyword) — "<em>{{ $keyword }}</em>" @endif
            @if ($genre) · genre: <em>{{ $genre }}</em> @endif
            @if ($tahun) · tahun: <em>{{ $tahun }}</em> @endif
            @if ($negara) · negara: <em>{{ $negara }}</em> @endif
        </span>
        <div class="d-flex gap-2">
            <button class="filter-badge active" id="view-grid" onclick="setView('grid')">⊞ Grid</button>
            <button class="filter-badge" id="view-list" onclick="setView('list')">☰ List</button>
        </div>
    </div>

    @if (empty($films))
        <div class="text-center py-5">
            <div style="font-size:3.5rem; opacity:.3;">🔍</div>
            <p class="text-white-50 mb-1">Film tidak ditemukan.</p>
            <p class="text-white-50 small mb-3">Coba kata kunci lain atau hapus beberapa filter.</p>
            <a href="{{ route('home') }}" class="btn btn-outline-pilem">← Kembali ke Now Playing</a>
        </div>
    @else
        {{-- Grid View --}}
        <div id="results-grid" class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
            @foreach ($films as $film)
                <div class="col">
                    <a href="{{ route('film.detail', $film['id']) }}" class="text-decoration-none text-white">
                        <div class="card-pilem h-100">
                            @if ($film['poster'])
                                <div class="position-relative">
                                    <img src="{{ $film['poster'] }}" class="poster" alt="{{ $film['nama'] }}">
                                </div>
                            @else
                                <div class="d-flex align-items-center justify-content-center"
                                     style="aspect-ratio:2/3; background:rgba(255,255,255,0.05);">
                                    <span style="font-size:2.5rem; opacity:.3;">🎬</span>
                                </div>
                            @endif
                            <div class="p-2">
                                <div class="fw-bold small mb-1">{{ Str::limit($film['nama'], 24) }}</div>
                                <div class="text-white-50" style="font-size:.72rem;">
                                    {{ $film['tahun'] ? substr($film['tahun'], 0, 4) : '-' }}
                                </div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @if ($film['genre'])
                                        <span class="badge" style="background:var(--sky);font-size:.6rem;">
                                            {{ Str::limit(explode(',', $film['genre'])[0], 16) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        {{-- List View (hidden default) --}}
        <div id="results-list" class="d-none d-flex flex-column gap-2">
            @foreach ($films as $i => $film)
                <a href="{{ route('film.detail', $film['id']) }}" class="film-card-list">
                    <div class="text-white-50 fw-bold" style="width:26px; font-size:.78rem; flex-shrink:0;">
                        {{ sprintf('%02d', $i + 1) }}
                    </div>
                    @if ($film['poster'])
                        <img src="{{ $film['poster'] }}" alt="{{ $film['nama'] }}">
                    @else
                        <div class="no-poster">🎬</div>
                    @endif
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1">{{ $film['nama'] }}</div>
                        <div class="text-white-50 small mb-1">
                            {{ $film['tahun'] ? substr($film['tahun'], 0, 4) : '-' }}
                            @if ($film['genre']) · {{ $film['genre'] }} @endif
                            @if ($film['negara']) · {{ $film['negara'] }} @endif
                        </div>
                    </div>
                    <div class="text-sand fw-bold" style="font-size:.8rem;">→</div>
                </a>
            @endforeach
        </div>
    @endif

@else
{{-- ===== MODE NOW PLAYING (default) ===== --}}

{{-- ======================= HERO / FEATURED ======================= --}}
<div class="pb-4">
    @if ($featured)
        <div class="row g-5 align-items-center">
            <div class="col-lg-7">
                <div class="text-uppercase small fw-bold mb-2 text-sand" style="letter-spacing: .05em;">
                    {{ $featured['genre'] ? str_replace(', ', ' / ', $featured['genre']) : 'FILM' }}
                    @if ($featured['studio'])
                        &bull; {{ strtoupper(explode(',', $featured['studio'])[0]) }}
                    @endif
                </div>
                <h1 class="display-5 fw-bold mb-3">{{ $featured['nama'] }}</h1>
                <p class="text-white-50 mb-4" style="max-width: 600px;">
                    {{ Str::limit($featured['sinopsis'] ?? $featured['tagline'] ?? 'Sinopsis belum tersedia untuk film ini.', 220) }}
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('film.detail', $featured['id']) }}" class="btn btn-sun px-4">🎬 Lihat Detail</a>
                    <a href="{{ route('film.detail', $featured['id']) }}#soundtrack" class="btn btn-outline-pilem px-4">🎵 Dengar OST</a>
                </div>

                @if (count($nowPlaying) > 1)
                    <div class="d-flex gap-2 mt-4 flex-wrap">
                        @foreach (array_slice($nowPlaying, 0, 5) as $i => $f)
                            <a class="hero-tab text-decoration-none {{ $f['id'] === $featured['id'] ? 'active' : '' }}"
                               href="{{ route('home', array_filter(['studio' => $studioAktif ?: null, 'featured' => $f['id']])) }}">
                                {{ sprintf('%02d', $i + 1) }} {{ Str::limit($f['nama'], 14) }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="position-relative rounded-4 overflow-hidden" style="aspect-ratio: 16/10; background: #000;">
                    @if (!empty($featured['externalData']['trailerKey']))
                        <iframe class="w-100 h-100" src="https://www.youtube.com/embed/{{ $featured['externalData']['trailerKey'] }}"
                                title="Trailer {{ $featured['nama'] }}" allowfullscreen style="border:0;"></iframe>
                    @elseif ($featured['poster'])
                        <img src="{{ $featured['poster'] }}" class="w-100 h-100" style="object-fit:cover;" alt="{{ $featured['nama'] }}">
                    @endif
                    @if ($featured['rating'])
                        <span class="rating-badge position-absolute top-0 end-0 m-2">⭐ {{ number_format($featured['rating'], 1) }}/10</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between text-white-50 small mt-2">
                    <span>{{ $featured['tahun'] ? substr($featured['tahun'], 0, 4) : '-' }} &bull; {{ pilemFormatDurasi($featured['durasi']) ?? '-' }}</span>
                    @if ($featured['komposer'])
                        <span class="fw-bold text-sand">{{ $featured['komposer'] }}</span>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-5">
            <h1 class="fw-bold">pilem<span class="text-sand">.</span></h1>
            <p class="text-white-50">Belum ada film untuk ditampilkan &mdash; pastikan dataset sudah ter-load di Fuseki.</p>
        </div>
    @endif
</div>

<hr class="pilem-hr">

{{-- ======================= NOW PLAYING GRID ======================= --}}
<div class="py-5">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
        <div>
            <h2 class="section-title mb-1">Now Playing</h2>
            <p class="text-white-50 small mb-0">Temukan film-film terbaru dan terpopuler saat ini.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">


            @foreach ($studioTabs as $value => $label)
                <a href="{{ route('home', array_filter(['studio' => $value ?: null])) }}"
                   class="hero-tab text-decoration-none {{ $studioAktif === $value ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if (empty($nowPlaying))
        <p class="text-white-50 mt-4">Tidak ada film untuk studio ini di dataset.</p>
    @else
        <div class="row row-cols-2 row-cols-md-4 g-4 mt-2">
            @foreach ($nowPlaying as $f)
                <div class="col">
                    <div class="card-pilem {{ $featured && $featured['id'] === $f['id'] ? 'active-feature' : '' }}">
                        <a href="{{ route('film.detail', $f['id']) }}"
                           class="text-decoration-none text-white">
                            <div class="position-relative">
                                @if ($f['poster'])
                                    <img src="{{ $f['poster'] }}" class="poster" alt="{{ $f['nama'] }}">
                                @endif
                                @if (pilemStudioBadge($f['studio']))
                                    <span class="badge-studio position-absolute top-0 start-0 m-2">{{ pilemStudioBadge($f['studio']) }}</span>
                                @endif
                                @if ($f['rating'])
                                    <span class="rating-badge position-absolute top-0 end-0 m-2" style="font-size:.7rem; padding:.2rem .5rem;">⭐ {{ number_format($f['rating'], 1) }}</span>
                                @endif
                            </div>
                        </a>
                        <div class="p-2">
                            <div class="fw-bold small">{{ Str::limit($f['nama'], 22) }}</div>
                            <div class="text-white-50" style="font-size:.72rem;">{{ Str::limit($f['tagline'] ?? $f['genre'] ?? '', 30) }}</div>
                            <div class="d-flex justify-content-between text-white-50 mt-1" style="font-size:.7rem;">
                                <span>{{ $f['tahun'] ? substr($f['tahun'], 0, 4) : '-' }}</span>
                                <span>{{ pilemFormatDurasi($f['durasi']) ?? '-' }}</span>
                            </div>
                            @if ($f['sutradara'])
                                <div class="text-white-50 mt-1" style="font-size:.68rem;">🎬 {{ Str::limit($f['sutradara'], 20) }}</div>
                            @endif
                            @if ($f['komposer'])
                                <div class="mt-1 text-sand" style="font-size:.68rem;">🎼 {{ Str::limit($f['komposer'], 20) }}</div>
                            @endif
                            <div class="d-flex gap-1 mt-2">
                                <a href="{{ route('home', array_filter(['studio' => $studioAktif ?: null, 'featured' => $f['id']])) }}#trailer-section"
                                   class="btn btn-outline-pilem btn-sm flex-fill" style="font-size:.7rem;">▶ Trailer</a>
                                <a href="{{ route('film.detail', $f['id']) }}#soundtrack" class="btn btn-sun btn-sm flex-fill" style="font-size:.7rem;">🎵 OST</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<hr class="pilem-hr">

{{-- ======================= TRAILER & CAST ======================= --}}
<div class="py-5" id="trailer-section">
    <h2 class="section-title mb-1">Official Trailers &amp; Clips</h2>
    <p class="text-white-50 small mb-4">Trailer film yang lagi disorot -- klik kartu di atas untuk ganti.</p>

    @if ($featured && !empty($featured['externalData']['trailerKey']))
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden mb-3">
                    <iframe src="https://www.youtube.com/embed/{{ $featured['externalData']['trailerKey'] }}"
                            title="Trailer {{ $featured['nama'] }}" allowfullscreen style="border:0;"></iframe>
                </div>
                <div class="fw-bold">{{ $featured['nama'] }}</div>
                <div class="text-white-50 small mb-3">Trailer resmi &mdash; YouTube (via TMDB)</div>

                @if (!empty($featured['castCrew']))
                    <div class="d-flex flex-wrap gap-3">
                        @foreach (collect($featured['castCrew'])->where('peran', 'Aktor')->take(4) as $orang)
                            <a href="{{ route('person.detail', $orang['id']) }}" class="text-decoration-none text-white text-center" style="width:70px;">
                                @if ($orang['foto'])
                                    <img src="{{ $orang['foto'] }}" class="rounded-circle mb-1" style="width:56px;height:56px;object-fit:cover;">
                                @else
                                    <div class="rounded-circle bg-white bg-opacity-10 mx-auto mb-1" style="width:56px;height:56px;"></div>
                                @endif
                                <div style="font-size:.68rem;">{{ Str::limit($orang['nama'], 14) }}</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card-pilem p-3">
                    <div class="fw-bold small mb-3 text-uppercase text-sand">Film lain di Now Playing</div>
                    @foreach (collect($nowPlaying)->where('id', '!=', $featured['id'])->take(5) as $f)
                        <a href="{{ route('home', array_filter(['studio' => $studioAktif ?: null, 'featured' => $f['id']])) }}#trailer-section"
                           class="d-flex align-items-center gap-2 text-decoration-none text-white mb-2 pb-2 border-bottom" style="border-color: rgba(255,255,255,0.08) !important;">
                            @if ($f['poster'])
                                <img src="{{ $f['poster'] }}" style="width:36px;height:54px;object-fit:cover;border-radius:4px;">
                            @endif
                            <div>
                                <div style="font-size:.78rem;" class="fw-semibold">{{ Str::limit($f['nama'], 24) }}</div>
                                <div class="text-white-50" style="font-size:.68rem;">{{ $f['tahun'] ? substr($f['tahun'],0,4) : '' }}</div>
                            </div>
                        </a>
                    @endforeach
                    <a href="https://www.youtube.com/results?search_query={{ urlencode($featured['nama'] . ' official trailer') }}"
                       target="_blank" rel="noopener" class="btn btn-outline-pilem btn-sm w-100 mt-2">🔴 Cari di YouTube</a>
                </div>
            </div>
        </div>
    @else
        <p class="text-white-50">Trailer tidak tersedia untuk film ini di TMDB.</p>
    @endif
</div>

<hr class="pilem-hr">

{{-- ======================= ASK PILMY (CHATBOT INLINE) ======================= --}}
<div class="py-5" id="ask-pilmy">
    <div class="text-uppercase small fw-bold mb-1 text-sand">AI Assistant</div>
    <h2 class="section-title mb-4">Ask Pilmy!</h2>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-pilem p-3 d-flex flex-column" style="height: 420px;">
                <div id="pilmy-messages" class="flex-grow-1 overflow-auto mb-3 d-flex flex-column gap-2">
                    <div class="chat-bubble-bot align-self-start">
                        Halo! Tanya apa saja soal film Disney/Marvel/Pixar di sini, atau soal film lain secara umum. 🎬
                    </div>
                </div>
                <form id="pilmy-form" class="d-flex gap-2">
                    <input type="text" id="pilmy-input" class="form-control search-input" placeholder="Tanya Pilmy..." autocomplete="off" maxlength="1000">
                    <button type="submit" class="btn btn-sun px-4">Send</button>
                </form>
                <small id="pilmy-status" class="text-white-50 mt-1"></small>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card-pilem p-3">
                <div class="fw-bold small mb-2 text-uppercase text-sand">Quick Prompts</div>
                @foreach ([
                    'Rekomendasikan film animasi Pixar dengan soundtrack emosional',
                    'Urutan nonton film Marvel yang benar apa?',
                    'Siapa sutradara Ratatouille?',
                    'Film Disney apa yang paling banyak menang Oscar?',
                ] as $prompt)
                    <button type="button" class="quick-prompt pilmy-quick-prompt">{{ $prompt }}</button>
                @endforeach
                <small class="text-white-50 d-block mt-2">Jawaban dari Google Gemini, general knowledge -- bukan cuma dari dataset CineGraph.</small>
            </div>
        </div>
    </div>
</div>

<hr class="pilem-hr">

{{-- ======================= STREAMING AVAILABILITY ======================= --}}
<div class="py-5" id="streaming">
    <div class="text-uppercase small fw-bold mb-1 text-sand">Official Availability Ledger</div>
    <h2 class="section-title mb-4">Streaming Availability</h2>

    @if (empty($streamingPreview))
        <p class="text-white-50">Data streaming tidak tersedia saat ini.</p>
    @else
        <div class="table-responsive">
            <table class="table table-pilem align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Judul</th>
                        <th>Tahun</th>
                        <th>Rating</th>
                        <th>Tersedia di</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($streamingPreview as $i => $s)
                        <tr>
                            <td>{{ sprintf('%03d', $i + 1) }}</td>
                            <td><a href="{{ route('film.detail', $s['id']) }}" class="text-decoration-none text-white fw-semibold">{{ $s['nama'] }}</a></td>
                            <td>{{ $s['tahun'] ? substr($s['tahun'], 0, 4) : '-' }}</td>
                            <td>{{ $s['rating'] ? number_format($s['rating'], 1) . '/10' : '-' }}</td>
                            <td>
                                @if (!empty($s['providers']))
                                    <div class="d-flex gap-2">
                                        @foreach (array_slice($s['providers'], 0, 5) as $p)
                                            <a href="{{ $s['link'] ?? '#' }}" target="_blank" rel="noopener" title="{{ $p['provider_name'] }}">
                                                <img src="https://image.tmdb.org/t/p/w92{{ $p['logo_path'] }}" class="provider-logo" alt="{{ $p['provider_name'] }}">
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-white-50 small">Tidak tersedia untuk wilayah ini</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <small class="text-white-50">Data oleh <a href="https://www.justwatch.com/" target="_blank" rel="noopener" class="text-decoration-underline text-sand">JustWatch</a>. Logo mengarah ke halaman JustWatch film terkait (TMDB tidak sediakan deep-link per-platform).</small>
    @endif
</div>

@endif {{-- end isSearchMode else --}}

@endsection

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const messages = document.getElementById('pilmy-messages');
    const form = document.getElementById('pilmy-form');
    const input = document.getElementById('pilmy-input');
    const status = document.getElementById('pilmy-status');

    if (!form) return; // mode search, pilmy tidak tampil

    function appendMessage(role, text) {
        const bubble = document.createElement('div');
        bubble.className = (role === 'user' ? 'chat-bubble-user align-self-end' : 'chat-bubble-bot align-self-start');
        bubble.textContent = text;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
    }

    async function sendMessage(message) {
        if (!message.trim()) return;
        appendMessage('user', message);
        input.value = '';
        status.textContent = 'Pilmy sedang mengetik...';

        try {
            const res = await fetch('{{ route('chatbot.send') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: message }),
            });
            const data = await res.json();

            if (!res.ok) {
                appendMessage('bot', data.error || 'Terjadi kesalahan.');
                status.textContent = '';
                return;
            }

            appendMessage('bot', data.reply);
            status.textContent = data.messagesUsed + '/' + data.messagesLimit + ' pesan dipakai';
        } catch (err) {
            appendMessage('bot', 'Pilmy sedang tidak bisa dihubungi. Coba lagi.');
            status.textContent = '';
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(input.value);
    });

    document.querySelectorAll('.pilmy-quick-prompt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            sendMessage(btn.textContent.trim());
        });
    });
})();

// Search helpers
function setHomeGenre(g) {
    document.querySelector('input[name="genre"]').value = g;
    document.getElementById('home-search-form').submit();
}
function setView(v) {
    const grid = document.getElementById('results-grid');
    const list = document.getElementById('results-list');
    const btnGrid = document.getElementById('view-grid');
    const btnList = document.getElementById('view-list');
    if (!grid || !list) return;
    if (v === 'grid') {
        grid.classList.remove('d-none'); grid.classList.add('row');
        list.classList.add('d-none'); list.classList.remove('d-flex');
        btnGrid.classList.add('active'); btnList.classList.remove('active');
    } else {
        list.classList.remove('d-none'); list.classList.add('d-flex');
        grid.classList.add('d-none'); grid.classList.remove('row');
        btnList.classList.add('active'); btnGrid.classList.remove('active');
    }
    localStorage.setItem('pilem_view', v);
}
const savedView = localStorage.getItem('pilem_view');
if (savedView === 'list') setView('list');
</script>
@endpush
