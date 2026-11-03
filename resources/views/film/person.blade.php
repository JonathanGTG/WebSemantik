@extends('layouts.app')

@section('title', $person['nama'] . ' - pilem.')

@push('meta')
    <meta property="og:title" content="{{ $person['nama'] }} - pilem.">
    <meta property="og:type" content="profile">
    @if ($person['foto'])
        <meta property="og:image" content="{{ $person['foto'] }}">
    @endif
@endpush

@push('styles')
<style>
    .person-hero {
        display: flex; gap: 2rem; align-items: center;
        padding: 1.5rem 0 2rem;
    }
    .person-avatar {
        width: 120px; height: 120px; border-radius: 50%;
        object-fit: cover; border: 3px solid var(--sky); flex-shrink: 0;
    }
    .person-avatar-placeholder {
        width: 120px; height: 120px; border-radius: 50%; flex-shrink: 0;
        background: rgba(255,255,255,0.08); border: 3px solid rgba(255,255,255,0.15);
        display: flex; align-items: center; justify-content: center; font-size: 3rem;
    }
    .role-pill {
        display: inline-block; font-size: .7rem; font-weight: 700;
        padding: .25rem .7rem; border-radius: 999px; text-transform: uppercase;
        letter-spacing: .04em;
    }
    .role-pill.sutradara { background: var(--sky); color: #fff; }
    .role-pill.aktor     { background: rgba(255,255,255,0.15); color: #fff; }
    .filmografi-tabs .tab-btn {
        background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.6); border-radius: 8px; padding: .35rem .8rem;
        font-size: .82rem; font-weight: 600; cursor: pointer; transition: .15s;
    }
    .filmografi-tabs .tab-btn.active { background: var(--sky); border-color: var(--sky); color: #fff; }
</style>
@endpush

@section('content')
<div vocab="https://schema.org/" typeof="Person">

    {{-- ======================= HERO PERSON ======================= --}}
    <div class="person-hero">
        @if (!empty($person['foto']))
            <img src="{{ $person['foto'] }}" property="image" class="person-avatar" alt="{{ $person['nama'] }}">
        @else
            <div class="person-avatar-placeholder">🎬</div>
        @endif
        <div>
            <div class="text-uppercase small fw-bold mb-1 text-sand" style="letter-spacing:.05em;">Profil</div>
            <h1 property="name" class="fw-bold mb-2">{{ $person['nama'] }}</h1>

            @php
                $asSutradara = collect($filmografi)->where('peran', 'Sutradara');
                $asAktor     = collect($filmografi)->where('peran', 'Aktor');
            @endphp

            <div class="d-flex gap-2 flex-wrap mb-3">
                @if ($asSutradara->count())
                    <span class="role-pill sutradara">🎬 Sutradara ({{ $asSutradara->count() }})</span>
                @endif
                @if ($asAktor->count())
                    <span class="role-pill aktor">🎭 Aktor ({{ $asAktor->count() }})</span>
                @endif
            </div>

            <p class="text-white-50 mb-0" style="font-size:.9rem;">
                Terlibat di <strong class="text-white">{{ count($filmografi) }}</strong> film dalam dataset CineGraph.
            </p>
        </div>
    </div>

    <hr class="pilem-hr mb-4">

    {{-- ======================= FILMOGRAFI ======================= --}}
    @if (empty($filmografi))
        <div class="card-flat p-5 text-center">
            <div style="font-size:3rem; opacity:.3;">🎬</div>
            <p class="text-white-50 mb-0 mt-2">Belum ada film tercatat untuk orang ini di dataset.</p>
        </div>
    @else
        {{-- Tab filter jika punya kedua peran --}}
        @if ($asSutradara->count() && $asAktor->count())
            <div class="filmografi-tabs d-flex gap-2 mb-4">
                <button class="tab-btn active" onclick="filterPeran('semua', this)">Semua ({{ count($filmografi) }})</button>
                <button class="tab-btn" onclick="filterPeran('Sutradara', this)">🎬 Sebagai Sutradara ({{ $asSutradara->count() }})</button>
                <button class="tab-btn" onclick="filterPeran('Aktor', this)">🎭 Sebagai Aktor ({{ $asAktor->count() }})</button>
            </div>
        @endif

        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3" id="filmografi-grid">
            @foreach ($filmografi as $filmItem)
                <div class="col filmografi-item" data-peran="{{ $filmItem['peran'] }}">
                    <a href="{{ route('film.detail', $filmItem['id']) }}" class="text-decoration-none text-white">
                        <div class="card-pilem h-100">
                            @if ($filmItem['poster'])
                                <div class="position-relative">
                                    <img src="{{ $filmItem['poster'] }}" class="poster" alt="{{ $filmItem['nama'] }}">
                                    <span class="position-absolute bottom-0 start-0 m-1 role-pill {{ strtolower($filmItem['peran']) }}">
                                        {{ $filmItem['peran'] === 'Sutradara' ? '🎬' : '🎭' }}
                                    </span>
                                </div>
                            @else
                                <div class="d-flex align-items-center justify-content-center"
                                     style="aspect-ratio:2/3; background: rgba(255,255,255,0.05);">
                                    <span style="font-size:2rem; opacity:.3;">🎬</span>
                                </div>
                            @endif
                            <div class="p-2">
                                <div class="fw-bold small mb-1">{{ Str::limit($filmItem['nama'], 22) }}</div>
                                <div class="text-white-50" style="font-size:.72rem;">
                                    {{ $filmItem['tahun'] ? substr($filmItem['tahun'], 0, 4) : '-' }}
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function filterPeran(peran, btn) {
    document.querySelectorAll('.filmografi-tabs .tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.filmografi-item').forEach(item => {
        if (peran === 'semua' || item.dataset.peran === peran) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
@endpush