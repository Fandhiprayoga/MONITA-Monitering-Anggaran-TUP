@extends('layouts.app')

@section('title', 'Dashboard Pencairan Admin | MONITA')

@section('content')
    @include('main.pencairan.period-nav-styles')
    @include('main.pencairan.budget-table-styles')
    <style>
        .submission-stat-card {
            background: linear-gradient(120deg, #ffffff 60%, var(--stat-background));
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
        }

        .submission-stat-card .card-body {
            color: #1e293b;
        }

        .submission-stat-card .stat-label {
            color: #475569;
        }

        .submission-stat-card .stat-icon {
            color: var(--stat-accent);
            background-color: var(--stat-background);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
        }

        .submission-stat-card--masuk {
            --stat-background: #eff6ff;
            --stat-accent: #1d4ed8;
        }

        .submission-stat-card--returned {
            --stat-background: #fff7ed;
            --stat-accent: #c2410c;
        }

        .submission-stat-card--submitted {
            --stat-background: #fffbeb;
            --stat-accent: #92400e;
        }

        .submission-stat-card--imprestfund_queue {
            --stat-background: #f0fdfa;
            --stat-accent: #0f766e;
        }

        .submission-stat-card--beban_queue {
            --stat-background: #eef2ff;
            --stat-accent: #4338ca;
        }

        .submission-stat-card--honor_queue {
            --stat-background: #faf5ff;
            --stat-accent: #7e22ce;
        }

        .sla-gauge-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
            min-height: 280px;
        }

        .sla-gauge-title {
            color: #475569;
            font-weight: 600;
        }

        .sla-gauge {
            max-width: 220px;
        }

        .sla-gauge svg {
            display: block;
            overflow: visible;
        }

        .sla-gauge-target {
            color: #64748b;
            font-size: 0.8125rem;
        }

        .sla-gauge-average {
            color: #1e293b;
            font-weight: 600;
            margin-top: 0.35rem;
        }
    </style>
    @php
        $rupiah = fn ($value) => \App\Services\PencairanDashboardService::rupiah($value);
        $counts = $statistics['counts'];
        $parameters = ['tahun' => $year, 'unit' => $unit];
    @endphp
    <div class="mb-4">
        <h3 class="mb-1">Dashboard Pencairan Admin</h3>
        <p class="text-muted mb-0">Monitoring layanan keuangan dan anggaran per unit.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('pencairan.dashboard-admin') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tw" value="{{ $period }}">
                <div class="col-md-5">
                    <label for="admin-dashboard-unit" class="form-label">Unit</label>
                    <select id="admin-dashboard-unit" name="unit" class="form-select">
                        <option value="">Semua unit</option>
                        @foreach ($units as $code => $name)
                            <option value="{{ $code }}" @selected($unit === (string) $code)>{{ $code }} - {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="admin-dashboard-year" class="form-label">Tahun anggaran</label>
                    <select id="admin-dashboard-year" name="tahun" class="form-select">
                        @foreach ($years as $availableYear)
                            <option value="{{ $availableYear }}" @selected($year === $availableYear)>{{ $availableYear }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
            </form>
        </div>
    </div>

    <nav class="dashboard-period-nav mb-4" aria-label="Periode dashboard admin">
        <div class="nav nav-pills flex-wrap gap-2">
            @foreach (['1' => 'TW1', '2' => 'TW2', '3' => 'TW3', '4' => 'TW4', 'tahunan' => 'Tahunan'] as $value => $label)
                <a class="nav-link {{ $period === (string) $value ? 'active' : '' }}"
                    @if ($period === (string) $value) aria-current="page" @endif
                    href="{{ route('pencairan.dashboard-admin', $parameters + ['tw' => $value]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </nav>

    <h5>Statistik Pengajuan</h5>
    <p class="text-muted small">Pengajuan masuk dihitung sekali per pengajuan. Kartu lainnya menunjukkan status saat ini dan dapat tumpang tindih dengan pengajuan masuk.</p>
    <div class="row">
        @foreach ([
            ['Pengajuan Masuk', 'masuk', 'ti-inbox'],
            ['Dikembalikan untuk Revisi', 'returned', 'ti-arrow-back'],
            ['Menunggu Verifikasi', 'submitted', 'ti-clipboard-check'],
            ['Antrean Imprestfund', 'imprestfund_queue', 'ti-wallet'],
            ['Antrean Beban', 'beban_queue', 'ti-receipt'],
            ['Antrean Honor', 'honor_queue', 'ti-users'],
        ] as [$label, $key, $icon])
            <div class="col-sm-6 col-xl-4">
                <div class="card submission-stat-card submission-stat-card--{{ $key }}"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="stat-label">{{ $label }}</div>
                        <div class="fs-3 fw-semibold mt-2">{{ $counts[$key] }}</div>
                    </div>
                    <i class="ti {{ $icon }} fs-3 stat-icon flex-shrink-0" aria-hidden="true"></i>
                </div></div>
            </div>
        @endforeach
    </div>

    <h5>Statistik Total Layanan</h5>
    <div class="row gy-4">
        @foreach ([
            ['Total Layanan', 'total', 'ti-list'],
            ['Imprestfund', 'imprestfund', 'ti-wallet'],
            ['Beban', 'beban', 'ti-receipt'],
            ['Honor', 'honor', 'ti-users'],
        ] as [$label, $key, $icon])
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 mb-0 service-stat-card"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="text-muted">{{ $label }}</div>
                        <div class="fs-3 fw-semibold mt-2">{{ $counts[$key] }}</div>
                        @if ($key === 'total')
                            <div class="small mt-2 text-danger">Dibatalkan: <strong>{{ $counts['cancelled'] }}</strong></div>
                        @elseif ($key === 'imprestfund')
                            <div class="small mt-2">Pencairan</div>
                        @elseif ($key === 'beban' || $key === 'honor')
                            <div class="small mt-2">Transaksi</div>
                        @endif
                    </div>
                    <i class="ti {{ $icon }} fs-3 text-primary flex-shrink-0" aria-hidden="true"></i>
                </div></div>
            </div>
        @endforeach
    </div>
    <p class="text-muted small mt-3 mb-4">Total mencakup pengajuan dibatalkan; rincian jenis tidak mencakup pengajuan dibatalkan. Draft dan data dummy tidak dihitung.</p>

    <h5>Monitoring SLA Layanan Keuangan</h5>
    <p class="text-muted small">Jarum menunjukkan rata-rata hari kerja tahap selesai terhadap target SLA saat ini. Durasi 0 hari kerja yang tepat waktu dihitung sebagai 1 hari kerja untuk rata-rata, mengikuti tampilan Report SLA. Status tepat waktu atau terlambat tetap memakai target yang berlaku saat tahap dimulai; data tidak lengkap tidak masuk rata-rata.</p>
    <div class="row gy-4">
        @foreach (\App\Services\PencairanSlaReportService::STAGES as $key => $label)
            @php $stage = $statistics['stages'][$key]; @endphp
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 mb-0 sla-gauge-card"><div class="card-body text-center">
                    <h6 class="sla-gauge-title">SLA {{ $label }}</h6>
                    @php
                        $averageText = $stage['average'] === null ? 'Belum ada data' : number_format($stage['average'], 1, ',', '.').' hari kerja';
                        $angle = $stage['needle_fraction'] === null ? null : pi() * $stage['needle_fraction'];
                        $needleX = $angle === null ? null : number_format(100 - 70 * cos($angle), 3, '.', '');
                        $needleY = $angle === null ? null : number_format(90 - 70 * sin($angle), 3, '.', '');
                    @endphp
                    <div class="sla-gauge mx-auto my-3">
                        <svg viewBox="0 0 200 110" width="100%" role="img"
                            aria-label="SLA {{ $label }}: rata-rata {{ $averageText }}, target saat ini {{ $stage['target'] }} hari kerja, skala 0 sampai {{ 2 * $stage['target'] }} hari kerja">
                            <path d="M 20 90 A 80 80 0 0 1 100 10" fill="none" stroke="#20a67a" stroke-width="10" />
                            <path d="M 100 10 A 80 80 0 0 1 180 90" fill="none" stroke="#e77878" stroke-width="10" />
                            <circle cx="20" cy="90" r="5" fill="#20a67a" />
                            <circle cx="180" cy="90" r="5" fill="#e77878" />
                            @if ($angle !== null)
                                <line x1="100" y1="90" x2="{{ $needleX }}" y2="{{ $needleY }}" stroke="#334155" stroke-width="3" stroke-linecap="round" />
                                <circle cx="100" cy="90" r="5" fill="#334155" />
                                <circle cx="100" cy="90" r="2" fill="#ffffff" />
                            @endif
                            <text x="20" y="108" text-anchor="middle" font-size="10" fill="#64748b">0</text>
                            <text x="180" y="108" text-anchor="middle" font-size="10" fill="#64748b">{{ 2 * $stage['target'] }}</text>
                        </svg>
                        <div class="sla-gauge-target">Maks {{ $stage['target'] }} hari kerja</div>
                        <div class="sla-gauge-average">Rata-rata: {{ $averageText }}</div>
                        @if ($stage['average_raw'] !== null && $stage['average_raw'] > 2 * $stage['target'])
                            <div class="small text-muted">Rata-rata melebihi batas skala.</div>
                        @endif
                    </div>
                    @if ($stage['different_targets'])
                        <div class="small text-warning mt-2">Target historis sampel: {{ implode(', ', $stage['historical_targets']) }} hari kerja; berbeda dari target saat ini.</div>
                    @endif
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        <h5>Detail Anggaran Unit</h5>
        <p class="text-muted small">DRK BANG dan OPERASIONAL. Saldo terkini = saldo awal - realisasi - dalam proses. Realisasi adalah bruto pengajuan selesai di MONITA, bukan konfirmasi pembayaran eksternal. Tahunan menjumlahkan alokasi tiap triwulan.</p>
        @if ($error)
            <div class="alert alert-danger" role="alert">{{ $error }} Total dan rincian anggaran tidak ditampilkan.</div>
        @else
            <div class="card"><div class="card-body"><div class="table-responsive dashboard-budget-scroll" role="region" aria-label="Detail Anggaran Unit" tabindex="0">
                <table class="table table-striped table-bordered align-middle mb-0 dashboard-budget-table">
                    <thead class="bg-secondary text-center"><tr>
                        @foreach (['Unit', 'DRK', 'Akun', 'Jenis', 'Uraian DRK', 'Saldo Awal', 'Realisasi', 'Dalam Proses', 'Saldo Terkini'] as $heading)
                            <th class="text-white" scope="col">{{ $heading }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($budget['rows'] as $row)
                            <tr>
                                <td>{{ $row['unit'] }}</td><td>{{ $row['drk'] }}</td><td>{{ $row['akun'] }}</td>
                                <td>{{ $row['tipe'] }}</td><td>{{ $row['uraian'] }}</td>
                                @foreach (['pagu', 'realisasi', 'proses', 'saldo'] as $key)
                                    <td class="text-end text-nowrap">{{ $rupiah($row[$key]) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada DRK BANG atau OPERASIONAL untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div></div>
        @endif
    </div>
@endsection
