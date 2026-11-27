@php
    $isInline = $isInline ?? false;
    $formId = $formId ?? ($isInline ? 'inpage-filter-form' : 'filter-nav-form');
@endphp

<div class="filter-form-panel {{ $isInline ? 'filter-inline-card' : '' }}">
    <form action="{{ route('home') }}" method="GET" id="{{ $formId }}">
        <input type="hidden" name="from_filter" value="1">

        {{-- Header Filter --}}
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold small text-sand" style="letter-spacing: .06em; text-transform: uppercase;">
                    Filter &amp; Urutkan Katalog Film
                </span>
                @if (request()->anyFilled(['q','huruf','genre','tahun','rated','sort','rating','urutan_tahun']))
                    <span class="badge border border-info border-opacity-50 text-cyan bg-info bg-opacity-10" style="font-size: .68rem;">
                        Filter Aktif
                    </span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                @if (request()->anyFilled(['q','huruf','genre','tahun','rated','sort','rating','urutan_tahun']))
                    <a href="{{ route('home') }}" class="btn btn-sm btn-outline-pilem text-danger-emphasis py-1 px-2" style="font-size: .75rem;">
                        Reset Filter
                    </a>
                @endif
                @if (!$isInline)
                    <button type="button" class="btn btn-sm btn-outline-pilem py-1 px-2 btn-close-filter-nav" aria-label="Tutup Filter" style="font-size: .75rem;">
                        Tutup
                    </button>
                @endif
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
                <input type="hidden" name="huruf" value="{{ request('huruf') }}">
                <button type="button" class="letter-btn {{ !request('huruf') ? 'active' : '' }}" onclick="selectFilterLetter(this, '')" title="Semua Huruf">Semua</button>
                @foreach (range('A', 'Z') as $char)
                    <button type="button" class="letter-btn {{ request('huruf') === $char ? 'active' : '' }}" onclick="selectFilterLetter(this, '{{ $char }}')" title="Judul berawalan {{ $char }}">{{ $char }}</button>
                @endforeach
                <button type="button" class="letter-btn {{ request('huruf') === '#' ? 'active' : '' }}" onclick="selectFilterLetter(this, '#')" title="Angka atau Simbol">#</button>
            </div>
        </div>

        {{-- Input Baris 1: Judul, Genre, Tahun, Rating Usia --}}
        <div class="row g-2 align-items-end mb-2">
            {{-- 1. Judul --}}
            <div class="col-12 col-md-4">
                <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Judul Film</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm search-input" placeholder="Cari judul..." autocomplete="off">
            </div>

            {{-- 2. Genre --}}
            <div class="col-6 col-md-3">
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
            <div class="col-12 col-md-3">
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
        </div>

        {{-- Input Baris 2: Urutkan Judul, Rating, Urutkan Tahun Rilis, dan Tombol Terapkan --}}
        <div class="row g-2 align-items-end">
            {{-- 5. Urutkan Judul --}}
            <div class="col-6 col-md-3">
                <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Urutkan Judul</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="title_asc" {{ request('sort') === 'title_asc' || !request('sort') ? 'selected' : '' }}>Judul (A &rarr; Z)</option>
                    <option value="title_desc" {{ request('sort') === 'title_desc' ? 'selected' : '' }}>Judul (Z &rarr; A)</option>
                </select>
            </div>

            {{-- 6. Rating (Filter & Urutan) --}}
            <div class="col-6 col-md-4">
                <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Rating (Filter &amp; Urutan)</label>
                <select name="rating" class="form-select form-select-sm">
                    <option value="">Semua Rating</option>
                    <option value="rating_desc" {{ request('rating') === 'rating_desc' ? 'selected' : '' }}>Rating Tertinggi</option>
                    <option value="rating_asc" {{ request('rating') === 'rating_asc' ? 'selected' : '' }}>Rating Terendah</option>
                    <option value="8" {{ request('rating') === '8' ? 'selected' : '' }}>Rating &ge; 8.0 (Sangat Bagus)</option>
                    <option value="7" {{ request('rating') === '7' ? 'selected' : '' }}>Rating &ge; 7.0 (Bagus)</option>
                    <option value="6" {{ request('rating') === '6' ? 'selected' : '' }}>Rating &ge; 6.0 (Cukup)</option>
                    <option value="5" {{ request('rating') === '5' ? 'selected' : '' }}>Rating &ge; 5.0 (Rata-rata)</option>
                </select>
            </div>

            {{-- 7. Urutkan Tahun Rilis --}}
            <div class="col-6 col-md-3">
                <label class="form-label small text-dim mb-1" style="font-size: .74rem;">Urutkan Tahun Rilis</label>
                <select name="urutan_tahun" class="form-select form-select-sm">
                    <option value="">Semua / Default</option>
                    <option value="year_desc" {{ request('urutan_tahun') === 'year_desc' ? 'selected' : '' }}>Tahun Rilis (Terbaru)</option>
                    <option value="year_asc" {{ request('urutan_tahun') === 'year_asc' ? 'selected' : '' }}>Tahun Rilis (Terlama)</option>
                </select>
            </div>

            {{-- Tombol Submit --}}
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-sun btn-sm w-100 py-1" style="font-weight: 700; height: 31px;">
                    Terapkan
                </button>
            </div>
        </div>
    </form>
</div>
