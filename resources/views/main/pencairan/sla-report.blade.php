@extends('layouts.app')

@section('title', 'Report SLA | MONITA')

@section('content')
    @php
        $activeFilters = [];
        foreach (['start_date' => 'Submit awal mulai', 'end_date' => 'Submit awal sampai', 'jenis' => 'Jenis', 'pp' => 'Unit', 'q' => 'Pencarian'] as $key => $label) {
            if (!empty($filters[$key])) {
                $activeFilters[$key] = $label.': '.($key === 'jenis' ? $types[$filters[$key]] : $filters[$key]);
            }
        }
    @endphp
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Report SLA</h5>
                <p class="text-muted mb-0">Kepatuhan SLA per tahap. Filter periode menggunakan tanggal submit pertama.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFilterReportSla">
                    <i class="ti ti-filter me-1" aria-hidden="true"></i>Filter
                    @if ($activeFilters)
                        <span class="badge text-bg-primary ms-1">{{ count($activeFilters) }}</span>
                    @endif
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalHelpReportSla" aria-label="Bantuan Report SLA" title="Keterangan Report SLA">
                    <i class="ti ti-help" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            @include('main.pencairan.partials.feedback')
            <nav class="nav nav-tabs flex-wrap mb-3" aria-label="Tab Report SLA">
                @foreach (['overview' => 'Overview', 'detail' => 'Detail Pengajuan'] as $code => $label)
                    <a class="nav-link {{ $tab === $code ? 'active' : '' }}"
                        @if ($tab === $code) aria-current="page" @endif
                        href="{{ route('pencairan.sla-report', array_replace($filterQuery, ['tab' => $code])) }}">{{ $label }}</a>
                @endforeach
            </nav>
            @if ($activeFilters)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Filter aktif">
                    <small class="text-muted">Filter aktif:</small>
                    @foreach ($activeFilters as $key => $label)
                        <a class="btn btn-sm btn-outline-secondary rounded-pill"
                            href="{{ route('pencairan.sla-report', \Illuminate\Support\Arr::except($filterQuery, [$key])) }}" aria-label="Hapus filter {{ $label }}">
                            {{ $label }} <span aria-hidden="true">&times;</span>
                        </a>
                    @endforeach
                    <a class="small" href="{{ route('pencairan.sla-report', ['tab' => $tab]) }}">Hapus semua filter</a>
                </div>
            @endif
            @if ($tab === 'overview')
                @include('main.pencairan.partials.sla-report-overview')
            @else
                @include('main.pencairan.partials.sla-report-detail')
            @endif
        </div>
    </div>

    <div class="modal fade" id="modalFilterReportSla" tabindex="-1" aria-labelledby="modalFilterReportSlaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="GET" action="{{ route('pencairan.sla-report') }}" class="modal-content" aria-label="Filter Report SLA">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 25 }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterReportSlaLabel">Filter Report SLA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="report-sla-start" class="form-label">Tanggal submit awal mulai</label>
                            <input id="report-sla-start" name="start_date" type="date" class="form-control" value="{{ $filters['start_date'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-end" class="form-label">Tanggal submit awal sampai</label>
                            <input id="report-sla-end" name="end_date" type="date" class="form-control" value="{{ $filters['end_date'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-search" class="form-label">Cari pengajuan</label>
                            <input id="report-sla-search" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nomor atau uraian pengajuan">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-unit" class="form-label">Kode unit / PP</label>
                            <input id="report-sla-unit" name="pp" class="form-control" value="{{ $filters['pp'] ?? '' }}" maxlength="100" placeholder="Semua unit">
                        </div>
                        <div class="col-md-6">
                            <label for="report-sla-type" class="form-label">Jenis transaksi</label>
                            <select id="report-sla-type" name="jenis" class="form-select">
                                <option value="">Semua jenis</option>
                                @foreach ($types as $code => $label)
                                    <option value="{{ $code }}" @selected(($filters['jenis'] ?? '') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Filter tanggal berlaku di kedua tab dan memakai tanggal submit pertama ({{ config('app.timezone') }}). Tanggal awal dan akhir termasuk dalam periode. Kosongkan kedua tanggal untuk menampilkan semua periode.</p>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('pencairan.sla-report', ['tab' => $tab]) }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1" aria-hidden="true"></i>Terapkan filter</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="modalHelpReportSla" tabindex="-1" aria-labelledby="modalHelpReportSlaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHelpReportSlaLabel">Keterangan Report SLA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <ul class="mb-0">
                        <li>Periode memilih pengajuan berdasarkan submit pertama. Draft yang belum pernah di-submit tidak masuk laporan. Hasil tahap tetap ditampilkan jika selesai setelah akhir periode.</li>
                        <li>Verifikasi: submit sampai verifikasi pada siklus yang sama, target 2 hari kerja. Transaksi: verifikasi sampai selesai di sistem, target 2 hari kerja untuk Imprestfund dan 5 untuk Beban/Honor.</li>
                        <li>Hari mulai tidak dihitung. Hari kerja Senin–Jumat, belum termasuk kalender libur nasional. Tenggat berlaku sampai akhir tanggalnya.</li>
                        <li>Tanggal transaksi diisi petugas dan bisa berbeda dari tanggal selesai di sistem. Perhitungan SLA memakai tanggal selesai di sistem.</li>
                        <li>Satu pengajuan dihitung maksimal sekali per tahap, dari siklus terakhir yang menyelesaikan tahap itu. Jika submit ulang masih menunggu verifikasi, hasil sebelumnya tetap tampil dan proses baru dicatat sebagai aktif.</li>
                        <li>Kartu Tepat Waktu menghitung pengajuan selesai yang memenuhi kedua SLA. Kartu Melewati SLA menghitung pengajuan dengan minimal satu hasil tahap terlambat, meskipun pengajuan belum selesai.</li>
                        <li>Kepatuhan per tahap = tepat waktu ÷ (tepat waktu + melewati SLA). Proses aktif dan riwayat yang tidak dapat dihitung tidak masuk persentase.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
