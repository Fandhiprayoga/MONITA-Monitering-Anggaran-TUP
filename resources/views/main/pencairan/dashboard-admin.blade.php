@extends('layouts.app')

@section('title', 'Dashboard Pencairan Admin | MONITA')

@section('content')
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

    <nav class="mb-4" aria-label="Periode dashboard admin">
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
            ['Pengajuan Masuk', 'masuk'], ['Dikembalikan untuk Revisi', 'returned'],
            ['Menunggu Verifikasi', 'submitted'], ['Antrean Imprestfund', 'imprestfund_queue'],
            ['Antrean Beban', 'beban_queue'], ['Antrean Honor', 'honor_queue'],
        ] as [$label, $key])
            <div class="col-sm-6 col-xl-4">
                <div class="card"><div class="card-body">
                    <div class="text-muted">{{ $label }}</div>
                    <div class="fs-3 fw-semibold mt-2">{{ $counts[$key] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <h5>Statistik Total Layanan</h5>
    <div class="card"><div class="card-body">
        <div class="text-muted">Total Layanan</div>
        <div class="fs-3 fw-semibold">{{ $counts['total'] }}</div>
        <div class="d-flex flex-wrap gap-4 mt-3">
            <span>Dibatalkan: <strong>{{ $counts['cancelled'] }}</strong></span>
            <span>Imprestfund: <strong>{{ $counts['imprestfund'] }}</strong></span>
            <span>Beban: <strong>{{ $counts['beban'] }}</strong></span>
            <span>Honor: <strong>{{ $counts['honor'] }}</strong></span>
        </div>
        <p class="text-muted small mb-0 mt-2">Total mencakup pengajuan dibatalkan; rincian jenis tidak mencakup pengajuan dibatalkan. Draft dan data dummy tidak dihitung.</p>
    </div></div>

    <h5>Monitoring SLA Layanan Keuangan</h5>
    <p class="text-muted small">Tahap yang sudah selesai diukur menggunakan target SLA yang berlaku saat tahap dimulai. Rata-rata dalam hari kerja; data yang tidak lengkap tidak masuk persentase maupun rata-rata.</p>
    <div class="row gy-4">
        @foreach (\App\Services\PencairanSlaReportService::STAGES as $key => $label)
            @php $stage = $statistics['stages'][$key]; @endphp
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 mb-0" style="min-height: 330px"><div class="card-body text-center">
                    <h6>SLA {{ $label }}</h6>
                    @if ($stage['percent'] !== null)
                        <div class="mx-auto my-3 d-flex align-items-center justify-content-center rounded-circle"
                            style="width: 150px; height: 150px; background: conic-gradient(#198754 {{ $stage['percent'] }}%, #dc3545 0)"
                            role="img" aria-label="Tepat waktu {{ $stage['percent'] }} persen, terlambat {{ 100 - $stage['percent'] }} persen">
                            <div class="d-flex align-items-center justify-content-center rounded-circle bg-white fw-semibold"
                                style="width: 110px; height: 110px">{{ number_format($stage['percent'], 1, ',', '.') }}%</div>
                        </div>
                    @else
                        <div class="my-3 d-flex align-items-center justify-content-center text-muted" style="min-height: 150px">Belum ada data</div>
                    @endif
                    <div class="small"><span class="text-success">Tepat waktu: {{ $stage['tepat_waktu'] }}</span> · <span class="text-danger">Terlambat: {{ $stage['terlambat'] }}</span></div>
                    <div class="small">Data tidak lengkap: {{ $stage['data_missing'] }}</div>
                    <div class="small">Rata-rata: {{ $stage['average'] === null ? '—' : number_format($stage['average'], 1, ',', '.').' hari kerja' }}</div>
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
            <div class="row">
                @foreach (['pagu' => 'Saldo Awal', 'realisasi' => 'Realisasi', 'proses' => 'Dalam Proses', 'saldo' => 'Saldo Terkini'] as $key => $label)
                    <div class="col-sm-6 col-xl-3">
                        <div class="card"><div class="card-body">
                            <div class="text-muted">{{ $label }}</div>
                            <div class="fw-semibold mt-2">{{ $rupiah($budget['totals'][$key]) }}</div>
                        </div></div>
                    </div>
                @endforeach
            </div>
            <div class="card"><div class="card-body"><div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0">
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
