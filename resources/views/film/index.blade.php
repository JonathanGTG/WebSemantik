@extends('layouts.app')

@section('title', 'Cari Film - pilem.')

@push('styles')
<style>
    .filter-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
        border-radius: 999px; padding: .3rem .8rem; font-size: .8rem; color: rgba(255,255,255,0.7);
        cursor: pointer; transition: background .15s, border-color .15s;
    }
    .filter-badge:hover, .filter-badge.active { background: var(--sky); border-color: var(--sky); color: #fff; }
    .search-hero { padding: 2.5rem 0 1.5rem; }
    .result-count { font-size: .85rem; color: rgba(255,255,255,0.5); }
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
        display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.5rem;
    }
</style>
@endpush

@section('content')

{{-- ======================= HERO SEARCH ======================= --}}
<div class="search-hero">
    <div class="text-uppercase small fw-bold mb-1 text-sand" style="letter-spacing:.05em;">CineGraph Database</div>
    <h1 class="fw-bold mb-1">Cari Film</h1>
    <p class="text-white-50 small mb-4">Pencarian via SPARQL query langsung ke dataset RDF — genre, tahun, dan negara sebagai resource graph.</p>

    <form method="GET" action="{{ route('film.search') }}" id="search-form">
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-6">
                <input type="text" name="q" id="search-q"
                       class="form-control form-control-lg search-input"
                       placeholder="Judul film, sutradara..."
                       value="{{ $keyword }}" autocomplete="off">
            </div>
            <div class="col-6 col-md-2">
                <input type="text" name="genre" class="form-control search-input"
                       placeholder="Genre" value="{{ $genre }}">
            </div>
            <div class="col-6 col-md-2">
                <input type="number" name="tahun" class="form-control search-input"
                       placeholder="Tahun" value="{{ $tahun }}" min="1900" max="{{ date('Y') }}">
            </div>
            <div class="col-6 col-md-1">
                <input type="text" name="negara" class="form-control search-input"
                       placeholder="Negara" value="{{ $negara }}">
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-sun w-100 h-100">Cari</button>
            </div>
        </div>

        {{-- Quick genre filters --}}
        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach (['Action', 'Adventure', 'Animation', 'Comedy', 'Drama', 'Science Fiction', 'Family'] as $g)
                <button type="button" class="filter-badge {{ strtolower($genre) === strtolower($g) ? 'active' : '' }}"
                        onclick="setGenre('{{ $g }}')">{{ $g }}</button>
            @endforeach
            @if ($genre)
                <a href="{{ route('film.search') }}" class="filter-badge" style="color: rgba(255,100,100,0.8); border-color: rgba(255,100,100,0.3);">✕ Reset filter</a>
            @endif
        </div>
    </form>
</div>

<hr class="pilem-hr mb-4">

{{-- ======================= ERROR ======================= --}}
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first('sparql') }}</div>
@endif

{{-- ======================= BELUM SEARCH ======================= --}}
@if ($belumMencari ?? false)
    <div class="text-center py-5">
        <div style="font-size: 4rem; opacity: .3;">🎬</div>
        <p class="text-white-50 mb-3">Masukkan judul, atau pilih filter di atas untuk mulai mencari.</p>
        <a href="{{ route('home') }}" class="btn btn-outline-pilem">← Kembali ke Now Playing</a>
    </div>

{{-- ======================= TIDAK DITEMUKAN ======================= --}}
@elseif (empty($films))
    <div class="text-center py-5">
        <div style="font-size: 4rem; opacity: .3;">🔍</div>
        <p class="text-white-50 mb-1">Film tidak ditemukan.</p>
        <p class="text-white-50 small mb-3">Coba kata kunci lain atau hapus beberapa filter.</p>
        <a href="{{ route('film.search') }}" class="btn btn-outline-pilem">Reset Pencarian</a>
    </div>

{{-- ======================= HASIL ======================= --}}
@else
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

    {{-- GRID VIEW --}}
    <div id="results-grid" class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
        @foreach ($films as $film)
            <div class="col">
                <a href="{{ route('film.detail', $film['id']) }}" class="text-decoration-none text-white">
                    <div class="card-pilem h-100">
                        @if ($film['poster'])
                            <div class="position-relative">
                                <img src="{{ $film['poster'] }}" class="poster" alt="{{ $film['nama'] }}">
                                @if ($film['rating'] ?? false)
                                    <span class="rating-badge position-absolute top-0 end-0 m-2" style="font-size:.7rem; padding:.2rem .5rem;">
                                        ⭐ {{ number_format($film['rating'], 1) }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="d-flex align-items-center justify-content-center" style="aspect-ratio:2/3; background: rgba(255,255,255,0.05);">
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
                                    <span class="badge" style="background: var(--sky); font-size:.6rem;">{{ Str::limit(explode(',', $film['genre'])[0], 16) }}</span>
                                @endif
                                @if ($film['negara'])
                                    <span class="badge" style="background: rgba(255,255,255,0.12); font-size:.6rem;">{{ Str::limit(explode(',', $film['negara'])[0], 12) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    {{-- LIST VIEW (hidden by default) --}}
    <div id="results-list" class="d-none d-flex flex-column gap-2">
        @foreach ($films as $i => $film)
            <a href="{{ route('film.detail', $film['id']) }}" class="film-card-list">
                <div class="text-white-50 fw-bold" style="width:28px; font-size:.8rem; flex-shrink:0;">
                    {{ sprintf('%02d', $i + 1) }}
                </div>
                @if ($film['poster'])
                    <img src="{{ $film['poster'] }}" alt="{{ $film['nama'] }}">
                @else
                    <div class="no-poster">🎬</div>
                @endif
                <div class="flex-grow-1">
                    <div class="fw-bold mb-1">{{ $film['nama'] }}</div>
                    <div class="text-white-50 small mb-2">
                        {{ $film['tahun'] ? substr($film['tahun'], 0, 4) : '-' }}
                        @if ($film['genre']) · {{ $film['genre'] }} @endif
                        @if ($film['negara']) · {{ $film['negara'] }} @endif
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        @if ($film['genre'])
                            <span class="badge" style="background: var(--sky); font-size:.65rem;">{{ Str::limit(explode(',', $film['genre'])[0], 20) }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-sand fw-bold" style="font-size:.8rem; flex-shrink:0;">→</div>
            </a>
        @endforeach
    </div>
@endif

@endsection

@push('scripts')
<script>
function setGenre(g) {
    document.querySelector('input[name="genre"]').value = g;
    document.getElementById('search-form').submit();
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
// Restore saved view preference
const savedView = localStorage.getItem('pilem_view');
if (savedView === 'list') setView('list');
</script>
@endpush