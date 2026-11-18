@extends('layouts.app')

@section('title', 'Monitoring SLA | MONITA')

@section('content')
    @php
        $statusLabels = [
            'aman' => 'Aman',
            'mendekati' => 'Mendekati tenggat',
            'terlambat' => 'Terlambat',
            'data_missing' => 'Tanggal awal tidak tersedia',
        ];
        $statusClasses = [
            'aman' => 'text-bg-success',
            'mendekati' => 'text-bg-warning',
            'terlambat' => 'text-bg-danger',
            'data_missing' => 'text-bg-secondary',
        ];
        $activeFilters = [];
        if (request()->filled('q')) {
            $activeFilters['q'] = 'Pencarian: '.request('q');
        }
        if (in_array($filters['tahap'] ?? '', ['verifikasi', 'transaksi'], true)) {
            $activeFilters['tahap'] = 'Tahap: '.($filters['tahap'] === 'verifikasi' ? 'Verifikasi' : 'Transaksi');
        }
        if (in_array($filters['jenis'] ?? '', ['imprestfund', 'beban', 'honor'], true)) {
            $activeFilters['jenis'] = 'Jenis: '.ucfirst($filters['jenis']);
        }
        if (isset($statusLabels[$filters['status_sla'] ?? ''])) {
            $activeFilters['status_sla'] = 'SLA: '.$statusLabels[$filters['status_sla']];
        }
        $advancedCount = count(array_diff(array_keys($activeFilters), ['q']));
        $resetUrl = route('pencairan.sla');
    @endphp
    <div class="row">
        @foreach ([
            ['label' => 'Dalam Pemantauan', 'value' => $summary['total'], 'class' => 'bg-secondary-dark', 'icon' => 'ti ti-list-check'],
            ['label' => 'Aman', 'value' => $summary['aman'], 'class' => 'bg-teal-900', 'icon' => 'ti ti-circle-check'],
            ['label' => 'Mendekati Tenggat', 'value' => $summary['mendekati'], 'class' => 'bg-yellow-900', 'icon' => 'ti ti-clock'],
            ['label' => 'Terlambat', 'value' => $summary['terlambat'], 'class' => 'bg-primary-dark', 'icon' => 'ti ti-alert-circle'],
        ] as $card)
            <div class="col-6 col-xl-3">
                <div class="card {{ $card['class'] }} dashnum-card text-white overflow-hidden">
                    <span class="round small"></span>
                    <span class="round big"></span>
                    <div class="card-body">
                        <div class="bg-dark bg-opacity-25 rounded d-flex align-items-center justify-content-center"
                            style="width: 48px; height: 48px;">
                            <i class="{{ $card['icon'] }} text-white fs-3" aria-hidden="true"></i>
                        </div>
                        <div class="fs-2 fw-semibold mt-2">{{ $card['value'] }}</div>
                        <p class="mb-0 opacity-75">{{ $card['label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Monitoring SLA Pencairan</h5>
                <p class="text-muted mb-0">Pantau waktu penyelesaian verifikasi dan transaksi pengajuan aktif.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFilterSla">
                    <i class="ti ti-filter me-1" aria-hidden="true"></i>Filter
                    @if ($advancedCount)
                        <span class="badge text-bg-primary ms-1">{{ $advancedCount }}</span>
                    @endif
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalKeteranganSla"
                    aria-label="Bantuan keterangan SLA" title="Keterangan SLA">
                    <i class="ti ti-help" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            @if ($summary['data_missing'] > 0)
                <div class="alert alert-warning" role="status">
                    {{ $summary['data_missing'] }} pengajuan aktif tidak memiliki tanggal awal SLA dan perlu diperiksa.
                </div>
            @endif
            @if ($activeFilters)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Filter aktif">
                    <small class="text-muted">Filter aktif:</small>
                    @foreach ($activeFilters as $key => $label)
                        @php
                            $remainingFilters = request()->only(['q', 'tahap', 'jenis', 'status_sla', 'per_page']);
                            unset($remainingFilters[$key]);
                            $removeUrl = route('pencairan.sla', $remainingFilters);
                        @endphp
                        <a class="pencairan-filter-chip" href="{{ $removeUrl }}" aria-label="Hapus filter {{ $label }}">
                            <span>{{ $label }}</span><span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $resetUrl }}" class="small">Hapus semua</a>
                </div>
            @endif
            <form method="GET" action="{{ route('pencairan.sla') }}" class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Jumlah data monitoring SLA">
                @foreach (request()->only(['q', 'tahap', 'jenis', 'status_sla']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="sla-per-page" class="mb-0">Tampilkan</label>
                <select id="sla-per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <span>data</span>
                <noscript><button type="submit" class="btn btn-sm btn-primary">Terapkan</button></noscript>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="bg-secondary">
                        <tr class="text-center">
                            <th class="text-white">No</th>
                            <th class="text-white">Tahap</th>
                            <th class="text-white">Pengajuan</th>
                            <th class="text-white">Unit</th>
                            <th class="text-white">Mulai</th>
                            <th class="text-white">Target / Tenggat</th>
                            <th class="text-white">Sisa / Keterlambatan</th>
                            <th class="text-white">Status SLA</th>
                            <th class="text-white">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $index => $pengajuan)
                            @php($sla = $pengajuan->sla_measurement)
                            <tr>
                                <td class="text-center">{{ $items->firstItem() + $index }}</td>
                                <td>{{ $sla['stage'] === 'verifikasi' ? 'Verifikasi' : 'Transaksi' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $pengajuan->nomor_pengajuan ?: 'Belum bernomor' }}</div>
                                    <small class="text-muted">{{ $pengajuan->jenisLabel() }} — {{ $pengajuan->uraian_pengajuan ?: 'Tanpa uraian' }}</small>
                                </td>
                                <td>{{ $pengajuan->nama_pp }} <small class="text-muted">({{ $pengajuan->kode_pp }})</small></td>
                                <td>{{ $sla['started_at']?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    {{ $sla['target_days'] }} hari kerja
                                    <div class="small text-muted">{{ $sla['due_date']?->format('d/m/Y') ?? 'Tidak tersedia' }}</div>
                                </td>
                                <td>
                                    @if ($sla['status'] === 'terlambat')
                                        {{ $sla['overdue_days'] === 0 ? 'Tenggat terlewati' : $sla['overdue_days'].' hari kerja terlambat' }}
                                    @elseif ($sla['remaining_days'] !== null)
                                        {{ $sla['remaining_days'] }} hari kerja
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge {{ $statusClasses[$sla['status']] }}">{{ $statusLabels[$sla['status']] }}</span></td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => 'sla']) }}" aria-label="Lihat detail pengajuan" title="Lihat detail">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada pengajuan yang sesuai dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">SLA dihitung pada hari kerja Senin–Jumat; hari libur nasional belum diperhitungkan.</small>
                {{ $items->links() }}
            </div>
        </div>
    </div>

    <style>
        .pencairan-filter-primary { display: grid; grid-template-columns: minmax(0, 1fr) minmax(220px, 280px); gap: 16px; }
        .pencairan-filter-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding-top: 16px; }
        .pencairan-filters .form-label { font-weight: 500; margin-bottom: 6px; }
        .pencairan-filters .form-control, .pencairan-filters .form-select { width: 100%; min-width: 0; }
        .pencairan-filter-chip { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 20px; padding: 5px 10px; color: var(--bs-body-color); background: var(--bs-body-bg); text-decoration: none; font-size: 12px; }
        .pencairan-filter-chip span:first-child { overflow-wrap: anywhere; }
        .pencairan-filter-chip:hover { border-color: var(--bs-primary); color: var(--bs-primary); }
        @media (min-width: 1400px) { .pencairan-filter-fields { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .pencairan-filter-primary, .pencairan-filter-fields { grid-template-columns: minmax(0, 1fr); } }
    </style>
    <div class="modal fade" id="modalFilterSla" tabindex="-1" aria-labelledby="modalFilterSlaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="GET" action="{{ route('pencairan.sla') }}" class="modal-content pencairan-filters" aria-label="Filter monitoring SLA">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterSlaLabel">Filter monitoring SLA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="pencairan-filter-primary">
                        <div>
                            <label for="sla-filter-q" class="form-label">Cari pengajuan atau unit</label>
                            <input id="sla-filter-q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nomor, uraian, atau unit…" maxlength="100">
                        </div>
                        <div>
                            <label for="sla-filter-status" class="form-label">Status SLA</label>
                            <select id="sla-filter-status" name="status_sla" class="form-select">
                                <option value="">Semua status</option>
                                @foreach ($statusLabels as $code => $label)
                                    <option value="{{ $code }}" @selected(($filters['status_sla'] ?? '') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pencairan-filter-fields">
                        <div>
                            <label for="sla-filter-stage" class="form-label">Tahap</label>
                            <select id="sla-filter-stage" name="tahap" class="form-select">
                                <option value="">Semua tahap</option>
                                <option value="verifikasi" @selected(($filters['tahap'] ?? '') === 'verifikasi')>Verifikasi</option>
                                <option value="transaksi" @selected(($filters['tahap'] ?? '') === 'transaksi')>Transaksi</option>
                            </select>
                        </div>
                        <div>
                            <label for="sla-filter-jenis" class="form-label">Jenis pengajuan</label>
                            <select id="sla-filter-jenis" name="jenis" class="form-select">
                                <option value="">Semua jenis</option>
                                @foreach (['imprestfund' => 'Imprestfund', 'beban' => 'Beban', 'honor' => 'Honor'] as $code => $label)
                                    <option value="{{ $code }}" @selected(($filters['jenis'] ?? '') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ $resetUrl }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1" aria-hidden="true"></i>Terapkan filter</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalKeteranganSla" tabindex="-1" aria-labelledby="modalKeteranganSlaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalKeteranganSlaLabel">Keterangan Monitoring SLA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-semibold">Batas waktu proses</h6>
                    <ul class="mb-3">
                        <li>Verifikasi: maksimal 2 hari kerja sejak pengajuan dikirim.</li>
                        <li>Transaksi Beban/Honor: maksimal 5 hari kerja sejak diverifikasi.</li>
                        <li>Pencairan Imprestfund: maksimal 2 hari kerja sejak diverifikasi.</li>
                    </ul>
                    <h6 class="fw-semibold">Arti status</h6>
                    <ul class="list-unstyled mb-3">
                        <li class="mb-2"><span class="badge text-bg-success me-1">Aman</span>Masih lebih dari satu hari kerja sebelum tenggat.</li>
                        <li class="mb-2"><span class="badge text-bg-warning me-1">Mendekati tenggat</span>Sisa satu hari kerja atau kurang.</li>
                        <li><span class="badge text-bg-danger me-1">Terlambat</span>Tenggat sudah terlewati.</li>
                    </ul>
                    <div class="alert alert-light border mb-0">
                        Perhitungan saat ini menggunakan hari kerja Senin–Jumat dan belum memperhitungkan hari libur nasional.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
