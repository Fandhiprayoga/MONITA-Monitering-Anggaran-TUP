@extends('layouts.app')

@section('title', 'Dashboard Pencairan | MONITA')

@section('content')
    @php
        $rupiah = fn ($value) => \App\Services\PencairanDashboardService::rupiah($value);
        $parameters = ['tahun' => $year, 'unit' => $unit];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h3 class="mb-1">Dashboard Pencairan Anggaran</h3>
            <p class="text-muted mb-0">Ringkasan anggaran dan pengajuan per unit.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('pencairan.dashboard') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tw" value="{{ $period }}">
                @if ($actor['role'] === 'admin')
                    <div class="col-md-5">
                        <label for="dashboard-unit" class="form-label">Unit</label>
                        <select id="dashboard-unit" name="unit" class="form-select">
                            @foreach ($units as $code => $name)
                                <option value="{{ $code }}" @selected($unit === (string) $code)>{{ $code }} - {{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-md-5">
                        <span class="form-label d-block">Unit</span>
                        <strong>{{ $unit }} - {{ $units[$unit] }}</strong>
                    </div>
                @endif
                <div class="col-md-3">
                    <label for="dashboard-year" class="form-label">Tahun anggaran</label>
                    <select id="dashboard-year" name="tahun" class="form-select">
                        @foreach ($years as $availableYear)
                            <option value="{{ $availableYear }}" @selected($year === $availableYear)>{{ $availableYear }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
            </form>
        </div>
    </div>

    <nav class="mb-4" aria-label="Periode dashboard pencairan">
        <div class="nav nav-pills flex-wrap gap-2">
            @foreach (['1' => 'TW1', '2' => 'TW2', '3' => 'TW3', '4' => 'TW4', 'tahunan' => 'Tahunan'] as $value => $label)
                <a class="nav-link {{ $period === (string) $value ? 'active' : '' }}"
                    @if ($period === (string) $value) aria-current="page" @endif
                    href="{{ route('pencairan.dashboard', $parameters + ['tw' => $value]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </nav>

    @if ($error)
        <div class="alert alert-danger" role="alert">{{ $error }}</div>
    @else
        <p class="text-muted small">Pagu berasal dari saldo awal DRK BANG dan OPERASIONAL. Realisasi adalah bruto pengajuan selesai di MONITA, bukan konfirmasi pembayaran eksternal. Pengajuan yang ditandai dummy tidak termasuk perhitungan dashboard.</p>
        <div class="row">
            @foreach ([
                ['Pagu Anggaran', 'pagu', 'bg-secondary-dark'],
                ['Realisasi Anggaran', 'realisasi', 'bg-teal-900'],
                ['Dalam Proses Pencairan', 'proses', 'bg-yellow-900'],
                ['Saldo Tersedia', 'saldo', 'bg-primary-dark'],
            ] as [$label, $key, $class])
                <div class="col-sm-6 col-xl-3">
                    <div class="card {{ $class }} dashnum-card text-white">
                        <div class="card-body">
                            <div class="small opacity-75">{{ $label }}</div>
                            <div class="amount-text mt-2">{{ $rupiah($data['totals'][$key]) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body">
                <h5>Komposisi Anggaran</h5>
                <p class="text-muted small">Selesai dan dalam proses menggunakan total bruto pengajuan MONITA.</p>
                @if ($data['chart'])
                    <div class="d-flex overflow-hidden rounded border" style="height: 32px" role="img"
                        aria-label="Selesai {{ $rupiah($data['totals']['realisasi']) }}, dalam proses {{ $rupiah($data['totals']['proses']) }}, saldo tersedia {{ $rupiah($data['totals']['saldo']) }}">
                        <div class="bg-success" style="width: {{ $data['chart']['realisasi'] }}%"></div>
                        <div class="bg-warning" style="width: {{ $data['chart']['proses'] }}%"></div>
                        <div class="bg-white" style="width: {{ $data['chart']['saldo'] }}%"></div>
                    </div>
                @else
                    <div class="alert alert-warning mb-2">Grafik tidak dapat ditampilkan karena pagu nol/negatif atau salah satu komponen bernilai negatif. Periksa angka anggaran di bawah.</div>
                @endif
                <div class="d-flex flex-wrap gap-3 mt-3 small">
                    <span><span class="badge bg-success">&nbsp;</span> Selesai: {{ $rupiah($data['totals']['realisasi']) }}</span>
                    <span><span class="badge bg-warning">&nbsp;</span> Dalam proses: {{ $rupiah($data['totals']['proses']) }}</span>
                    <span><span class="badge bg-white border">&nbsp;</span> Saldo tersedia: {{ $rupiah($data['totals']['saldo']) }}</span>
                </div>
            </div>
        </div>

        <div class="row">
            @foreach ([
                ['Total Pengajuan', 'total', 'ti-list'],
                ['Selesai', 'completed', 'ti-circle-check'],
                ['Dalam Proses', 'process', 'ti-clock'],
                ['Perlu Perbaikan', 'returned', 'ti-alert-circle'],
            ] as [$label, $key, $icon])
                <div class="col-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="ti {{ $icon }} fs-3 text-primary" aria-hidden="true"></i>
                            <div class="fs-3 fw-semibold mt-2">{{ $data['counts'][$key] }}</div>
                            <span class="text-muted">{{ $label }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-muted small">Total tidak termasuk draft dan dibatalkan. Status yang ditampilkan adalah status saat ini.</p>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">Detail Anggaran Unit</h5>
                <p class="text-muted mb-0">DRK jenis BANG dan OPERASIONAL{{ $period === 'tahunan' ? ', digabung per DRK, akun dan jenis.' : '.' }}</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead class="bg-secondary text-center">
                            <tr>
                                <th class="text-white" scope="col">DRK</th>
                                <th class="text-white" scope="col">Akun</th>
                                <th class="text-white" scope="col">Jenis</th>
                                <th class="text-white" scope="col">Uraian DRK</th>
                                <th class="text-white" scope="col">Saldo Awal</th>
                                <th class="text-white" scope="col">Realisasi</th>
                                <th class="text-white" scope="col">Dalam Proses</th>
                                <th class="text-white" scope="col">Saldo Terkini</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['rows'] as $row)
                                <tr>
                                    <td>{{ $row['drk'] }}</td><td>{{ $row['akun'] }}</td><td>{{ $row['tipe'] }}</td><td>{{ $row['uraian'] }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($row['pagu']) }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($row['realisasi']) }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($row['proses']) }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($row['saldo']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada DRK BANG atau OPERASIONAL untuk periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
