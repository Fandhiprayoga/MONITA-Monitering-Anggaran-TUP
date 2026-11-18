@extends('layouts.app')

@section('title', 'Report SLA | MONITA')

@section('content')
    @php
        $resultLabels = ['tepat_waktu' => 'Tepat waktu', 'terlambat' => 'Melewati SLA', 'data_missing' => 'Tidak dapat dihitung'];
        $resultClasses = ['tepat_waktu' => 'text-bg-success', 'terlambat' => 'text-bg-danger', 'data_missing' => 'text-bg-secondary'];
        $filterKeys = ['start_date', 'end_date', 'tahap', 'hasil', 'pp', 'q', 'per_page'];
        $activeFilters = [];
        foreach (['start_date' => 'Selesai mulai', 'end_date' => 'Selesai sampai', 'pp' => 'Unit', 'q' => 'Pencarian'] as $key => $label) {
            if (!empty($filters[$key])) {
                $activeFilters[$key] = $label.': '.$filters[$key];
            }
        }
        if (!empty($filters['hasil'])) {
            $activeFilters['hasil'] = 'Hasil: '.$resultLabels[$filters['hasil']];
        }
    @endphp
    <div class="row">
        @foreach ([
            ['label' => 'Tahap Selesai', 'value' => $summary['total'], 'class' => 'bg-secondary-dark', 'icon' => 'ti ti-list-check'],
            ['label' => 'Tepat Waktu', 'value' => $summary['on_time'], 'class' => 'bg-teal-900', 'icon' => 'ti ti-circle-check'],
            ['label' => 'Melewati SLA', 'value' => $summary['late'], 'class' => 'bg-primary-dark', 'icon' => 'ti ti-alert-circle'],
            ['label' => 'Kepatuhan SLA', 'value' => $summary['compliance'] !== null ? number_format($summary['compliance'], 1, ',', '.').'%' : '-', 'class' => 'bg-yellow-900', 'icon' => 'ti ti-chart-bar'],
        ] as $card)
            <div class="col-6 col-xl-3">
                <div class="card {{ $card['class'] }} dashnum-card text-white overflow-hidden">
                    <span class="round small"></span>
                    <span class="round big"></span>
                    <div class="card-body">
                        <div class="bg-dark bg-opacity-25 rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
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
                <h5 class="mb-1">Report SLA per Tahap</h5>
                <p class="text-muted mb-0">Hasil historis tahap yang telah selesai. Ringkasan mengikuti seluruh filter laporan.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFilterReportSla">
                    <i class="ti ti-filter me-1" aria-hidden="true"></i>Filter
                    @if (count($activeFilters))
                        <span class="badge text-bg-primary ms-1">{{ count($activeFilters) }}</span>
                    @endif
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalHelpReportSla" aria-label="Bantuan report SLA" title="Keterangan Report SLA">
                    <i class="ti ti-help" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            @include('main.pencairan.partials.feedback')
            <nav class="nav nav-tabs flex-wrap mb-3" aria-label="Tahap report SLA">
                @foreach (['' => 'Semua tahap'] + $stages as $code => $label)
                    <a class="nav-link {{ ($filters['tahap'] ?? '') === $code ? 'active' : '' }}"
                        @if (($filters['tahap'] ?? '') === $code) aria-current="page" @endif
                        href="{{ route('pencairan.sla-report', array_merge(request()->only($filterKeys), ['tahap' => $code])) }}">{{ $label }}</a>
                @endforeach
            </nav>
            @if ($activeFilters)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Filter aktif">
                    <small class="text-muted">Filter aktif:</small>
                    @foreach ($activeFilters as $key => $label)
                        @php
                            $remainingFilters = request()->only($filterKeys);
                            unset($remainingFilters[$key]);
                        @endphp
                        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('pencairan.sla-report', $remainingFilters) }}" aria-label="Hapus filter {{ $label }}">
                            {{ $label }} <span aria-hidden="true">&times;</span>
                        </a>
                    @endforeach
                    <a class="small" href="{{ route('pencairan.sla-report') }}">Hapus semua</a>
                </div>
            @endif
            @if ($summary['unmeasurable'] > 0)
                <div class="alert alert-warning" role="status">
                    {{ $summary['unmeasurable'] }} tahap tidak dapat dihitung karena riwayat tidak lengkap atau tidak valid.
                    Data tersebut tidak masuk perhitungan kepatuhan dan rata-rata durasi.
                </div>
            @endif
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0" aria-label="Ringkasan SLA per tahap">
                    <thead>
                        <tr>
                            <th scope="col">Tahap</th>
                            <th scope="col">Target hari kerja</th>
                            <th scope="col">Selesai</th>
                            <th scope="col">Tepat waktu</th>
                            <th scope="col">Melewati SLA</th>
                            <th scope="col">Tidak dapat dihitung</th>
                            <th scope="col">Kepatuhan</th>
                            <th scope="col">Rata-rata hari kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stages as $stage => $label)
                            @php($metrics = $stageSummary[$stage])
                            <tr>
                                <td>{{ $label }}</td>
                                <td>{{ $metrics['target_days'] }}</td>
                                <td>{{ $metrics['total'] }}</td>
                                <td>{{ $metrics['on_time'] }}</td>
                                <td>{{ $metrics['late'] }}</td>
                                <td>{{ $metrics['unmeasurable'] }}</td>
                                <td>{{ $metrics['compliance'] !== null ? number_format($metrics['compliance'], 1, ',', '.').'%' : '-' }}</td>
                                <td>{{ $metrics['average_days'] !== null ? number_format($metrics['average_days'], 1, ',', '.') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <form method="GET" action="{{ route('pencairan.sla-report') }}" class="d-flex align-items-center gap-2" aria-label="Jumlah data report SLA">
                    @foreach (request()->only(array_diff($filterKeys, ['per_page'])) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="report-sla-per-page" class="mb-0">Tampilkan</label>
                    <select id="report-sla-per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span>data</span>
                    <noscript><button type="submit" class="btn btn-sm btn-primary">Terapkan</button></noscript>
                </form>
                <small class="text-muted">{{ $items->total() }} tahap selesai ditemukan</small>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0" aria-label="Detail hasil SLA">
                    <thead class="bg-secondary">
                        <tr>
                            @foreach (['Pengajuan / Siklus', 'Unit', 'Tahap', 'Mulai', 'Selesai', 'Tenggat / Target', 'Durasi', 'Keterlambatan', 'Hasil SLA', 'Aksi'] as $heading)
                                <th class="text-white" scope="col">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $row)
                            @php($pengajuan = $row['event']->pengajuan)
                            <tr>
                                <td>
                                    <div>{{ $pengajuan->nomor_pengajuan }}</div>
                                    <small class="d-block text-muted">{{ $pengajuan->jenisLabel() }} / Siklus {{ $row['event']->revision_no ?? '-' }}</small>
                                    <small class="text-muted">{{ $pengajuan->uraian_pengajuan }}</small>
                                </td>
                                <td>{{ $pengajuan->kode_pp }}<small class="d-block text-muted">{{ $pengajuan->nama_pp }}</small></td>
                                <td>{{ $stages[$row['stage']] }}</td>
                                <td class="text-nowrap">{{ $row['started_at']?->format('d/m/Y H:i:s') ?? '-' }}</td>
                                <td class="text-nowrap">{{ $row['finished_at']->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $row['due_date']?->format('d/m/Y') ?? '-' }}<small class="d-block text-muted">{{ $row['target_days'] }} hari kerja</small></td>
                                <td>{{ $row['duration_days'] !== null ? $row['duration_days'].' hari kerja' : '-' }}</td>
                                <td>
                                    @if ($row['result'] === 'terlambat' && $row['overdue_days'] === 0)
                                        Tenggat terlewati
                                    @else
                                        {{ $row['overdue_days'] !== null ? $row['overdue_days'].' hari kerja' : '-' }}
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $resultClasses[$row['result']] }}">{{ $resultLabels[$row['result']] }}</span>
                                    @if ($row['problem'])<small class="d-block text-danger mt-1">{{ $row['problem'] }}</small>@endif
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => 'sla-report']) }}" aria-label="Lihat detail pengajuan" title="Lihat detail">
                                        <i class="ti ti-eye" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada tahap selesai untuk filter laporan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="modalFilterReportSla" tabindex="-1" aria-labelledby="modalFilterReportSlaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="GET" action="{{ route('pencairan.sla-report') }}" class="modal-content" aria-label="Filter report SLA">
                <input type="hidden" name="tahap" value="{{ $filters['tahap'] ?? '' }}">
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 25 }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterReportSlaLabel">Filter Report SLA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="report-sla-search" class="form-label">Cari pengajuan</label>
                            <input id="report-sla-search" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nomor atau uraian pengajuan">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-unit" class="form-label">Kode unit / PP</label>
                            <input id="report-sla-unit" name="pp" class="form-control" value="{{ $filters['pp'] ?? '' }}" maxlength="100" placeholder="Semua unit">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-start" class="form-label">Tanggal selesai mulai</label>
                            <input id="report-sla-start" name="start_date" type="date" class="form-control" value="{{ $filters['start_date'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-end" class="form-label">Tanggal selesai sampai</label>
                            <input id="report-sla-end" name="end_date" type="date" class="form-control" value="{{ $filters['end_date'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-result" class="form-label">Hasil SLA</label>
                            <select id="report-sla-result" name="hasil" class="form-select">
                                <option value="">Semua hasil</option>
                                @foreach ($resultLabels as $code => $label)
                                    <option value="{{ $code }}" @selected(($filters['hasil'] ?? '') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Periode berdasarkan waktu tahap selesai tercatat ({{ config('app.timezone') }}), termasuk seluruh tanggal akhir.</p>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('pencairan.sla-report') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1" aria-hidden="true"></i>Terapkan filter</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalHelpReportSla" tabindex="-1" aria-labelledby="modalHelpReportSlaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHelpReportSlaLabel">Keterangan Report SLA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <ul>
                        <li>Verifikasi: submit sampai verifikasi pada siklus yang sama, target 2 hari kerja.</li>
                        <li>Transaksi Imprestfund: verifikasi sampai selesai, target 2 hari kerja.</li>
                        <li>Transaksi Beban dan Honor: verifikasi sampai selesai, target 5 hari kerja.</li>
                        <li>Tanggal mulai tidak dihitung; hitungan dimulai pada hari kerja berikutnya. Tenggat berlaku sampai akhir tanggal tersebut.</li>
                        <li>Hari kerja Senin–Jumat; hari libur nasional belum diperhitungkan.</li>
                        <li>Waktu selesai memakai catatan tindakan sistem, bukan tanggal proses transaksi yang dapat diisi mundur.</li>
                        <li>Setiap siklus verifikasi yang selesai dihitung terpisah dan tetap tersedia meskipun pengajuan kemudian dikembalikan. Tahap yang dikembalikan sebelum selesai tidak masuk laporan tahap selesai.</li>
                    </ul>
                    <p>Kepatuhan = tahap tepat waktu / seluruh tahap yang dapat dihitung × 100%. Proses yang masih berjalan tersedia pada menu Monitoring SLA.</p>
                    <p class="mb-0">Riwayat tidak lengkap atau waktu tidak valid ditampilkan sebagai tidak dapat dihitung, tanpa membuat perkiraan tanggal pengganti.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
