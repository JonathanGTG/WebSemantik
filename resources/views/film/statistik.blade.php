@extends('layouts.app')

@section('title', 'Statistik Dataset - pilem.')

@push('styles')
<style>
    .stat-card {
        background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 14px; padding: 1.5rem; text-align: center;
        transition: border-color .15s;
    }
    .stat-card:hover { border-color: var(--sky); }
    .stat-card .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--sand); line-height: 1; }
    .stat-card .stat-label { font-size: .8rem; color: rgba(255,255,255,0.5); margin-top: .3rem; text-transform: uppercase; letter-spacing: .05em; }
    .genre-bar-wrap { margin-bottom: .6rem; }
    .genre-bar-label { font-size: .82rem; color: rgba(255,255,255,0.75); margin-bottom: .2rem; display: flex; justify-content: space-between; }
    .genre-bar-track { background: rgba(255,255,255,0.08); border-radius: 999px; height: 10px; overflow: hidden; }
    .genre-bar-fill { background: linear-gradient(90deg, var(--sky), var(--sun)); height: 100%; border-radius: 999px; transition: width .8s ease; }
</style>
@endpush

@section('content')

<div class="text-uppercase small fw-bold mb-1 text-sand" style="letter-spacing:.05em;">Dataset Analytics</div>
<h1 class="fw-bold mb-1">Statistik Film</h1>
<p class="text-white-50 small mb-5">
    Query SPARQL agregasi (<code>GROUP BY</code> + <code>COUNT</code>) langsung ke Fuseki — bukan data statis.
</p>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first('sparql') }}</div>
@endif

@if (empty($stats))
    <div class="card-flat p-4 text-center">
        <p class="text-white-50 mb-0">Belum ada data untuk ditampilkan. Pastikan Fuseki berjalan dan dataset sudah di-load.</p>
    </div>
@else
    @php
        $totalFilm   = array_sum(array_column($stats, 'jumlah'));
        $topGenre    = $stats[0] ?? null;
        $totalGenre  = count($stats);
        $maxJumlah   = $topGenre['jumlah'] ?? 1;
    @endphp

    {{-- ======================= SUMMARY CARDS ======================= --}}
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number">{{ $totalFilm }}</div>
                <div class="stat-label">Total Film</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number">{{ $totalGenre }}</div>
                <div class="stat-label">Genre Unik</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number" style="font-size: 1.5rem;">{{ Str::limit($topGenre['genre'] ?? '-', 12) }}</div>
                <div class="stat-label">Genre Terbanyak</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number">{{ $maxJumlah }}</div>
                <div class="stat-label">Film di Top Genre</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ======================= CHART ======================= --}}
        <div class="col-lg-7">
            <div class="card-pilem p-4">
                <div class="fw-bold small text-sand text-uppercase mb-3" style="letter-spacing:.05em;">Distribusi per Genre</div>
                <canvas id="genreChart" height="110"></canvas>
            </div>
        </div>

        {{-- ======================= HORIZONTAL BAR (manual) ======================= --}}
        <div class="col-lg-5">
            <div class="card-pilem p-4 h-100">
                <div class="fw-bold small text-sand text-uppercase mb-3" style="letter-spacing:.05em;">Ranking Genre</div>
                @foreach ($stats as $stat)
                    <div class="genre-bar-wrap">
                        <div class="genre-bar-label">
                            <span>{{ $stat['genre'] }}</span>
                            <span class="text-sand fw-bold">{{ $stat['jumlah'] }}</span>
                        </div>
                        <div class="genre-bar-track">
                            <div class="genre-bar-fill" style="width: {{ round(($stat['jumlah'] / $maxJumlah) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ======================= TABEL LENGKAP ======================= --}}
    <div class="mt-5">
        <div class="fw-bold small text-sand text-uppercase mb-3" style="letter-spacing:.05em;">Tabel Lengkap</div>
        <div class="table-responsive">
            <table class="table table-pilem">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Genre</th>
                        <th>Jumlah Film</th>
                        <th>Persentase</th>
                        <th>Cari</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stats as $i => $stat)
                        <tr>
                            <td class="text-white-50" style="font-size:.8rem;">{{ sprintf('%02d', $i + 1) }}</td>
                            <td class="fw-semibold">{{ $stat['genre'] }}</td>
                            <td>{{ $stat['jumlah'] }}</td>
                            <td>
                                <span class="text-sand">{{ $totalFilm > 0 ? number_format(($stat['jumlah'] / $totalFilm) * 100, 1) : 0 }}%</span>
                            </td>
                            <td>
                                <a href="{{ route('film.search', ['genre' => $stat['genre']]) }}"
                                   class="btn btn-outline-pilem btn-sm" style="font-size:.7rem;">Lihat Film →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        new Chart(document.getElementById('genreChart'), {
            type: 'bar',
            data: {
                labels: @json(array_column($stats, 'genre')),
                datasets: [{
                    label: 'Jumlah Film',
                    data: @json(array_column($stats, 'jumlah')),
                    backgroundColor: 'rgba(48, 127, 226, 0.7)',
                    borderColor: '#307FE2',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#F2A900',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} film`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#ffffff6e' },
                        grid: { color: 'rgba(255,255,255,0.07)' }
                    },
                    x: {
                        ticks: { color: '#ffffff6e', maxRotation: 30 },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
@endif

@endsection