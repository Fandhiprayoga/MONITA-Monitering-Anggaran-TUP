@extends('layouts.app')

@section('title', 'History Pencairan | MONITA')

@section('content')
    @php
        $actionLabels = ['verify' => 'Terverifikasi', 'complete' => 'Transaksi selesai'];
        $selectedPp = strtoupper(trim($filters['pp'] ?? ''));
    @endphp
    <div class="card">
        <div class="card-header">
            <h5>History Pencairan</h5>
            <p class="text-muted mb-0">Riwayat verifikasi dan penyelesaian transaksi seluruh unit, dari aktivitas terbaru ke terlama.</p>
        </div>
        <div class="card-body">
            @include('main.pencairan.partials.feedback')
            <form method="GET" action="{{ route('pencairan.history') }}" aria-label="Filter history" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-4">
                        <label for="history-q" class="form-label">Cari pengajuan</label>
                        <input id="history-q" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nomor atau uraian pengajuan">
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label for="history-start" class="form-label">Tanggal mulai</label>
                        <input id="history-start" type="date" name="start_date" class="form-control" value="{{ $filters['start_date'] ?? '' }}">
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label for="history-end" class="form-label">Tanggal akhir</label>
                        <input id="history-end" type="date" name="end_date" class="form-control" value="{{ $filters['end_date'] ?? '' }}">
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label for="history-action" class="form-label">Aktivitas</label>
                        <select id="history-action" name="action" class="form-select">
                            <option value="">Semua aktivitas</option>
                            @foreach($actionLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label for="history-jenis" class="form-label">Jenis pengajuan</label>
                        <select id="history-jenis" name="jenis" class="form-select">
                            <option value="">Semua jenis</option>
                            @foreach(['impresfund' => 'Imprestfund', 'beban' => 'Beban', 'honor' => 'Honor'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['jenis'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <label for="history-pp" class="form-label">Unit</label>
                        <select id="history-pp" name="pp" class="form-select">
                            <option value="">Semua unit</option>
                            @if($selectedPp !== '' && !$unitOptions->has($selectedPp))
                                <option value="{{ $selectedPp }}" selected>{{ $selectedPp }}</option>
                            @endif
                            @foreach($unitOptions as $kode => $nama)
                                <option value="{{ $kode }}" @selected($selectedPp === (string) $kode)>{{ $kode }} - {{ $nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="small text-muted mt-2">Rentang tanggal berdasarkan waktu aktivitas tercatat ({{ config('app.timezone') }}), termasuk seluruh hari pada tanggal akhir, bukan tanggal proses transaksi.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i>Terapkan filter</button>
                    <a href="{{ route('pencairan.history') }}" class="btn btn-outline-secondary">Hapus semua</a>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <form method="GET" action="{{ route('pencairan.history') }}" aria-label="Jumlah data history" class="d-flex align-items-center gap-2">
                    @foreach($filters as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="history-per-page" class="mb-0">Tampilkan</label>
                    <select id="history-per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
                        @foreach(['10' => '10', '50' => '50', '100' => '100', 'all' => 'Semua data'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) $perPage === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span>data</span>
                    <noscript><button type="submit" class="btn btn-sm btn-primary">Terapkan</button></noscript>
                </form>
                <small class="text-muted">{{ $items->total() }} aktivitas ditemukan</small>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="bg-secondary">
                        <tr>
                            <th class="text-white" scope="col">Waktu aktivitas</th>
                            <th class="text-white" scope="col">Pengajuan</th>
                            <th class="text-white" scope="col">Unit</th>
                            <th class="text-white" scope="col">Jenis</th>
                            <th class="text-white" scope="col">Aktivitas</th>
                            <th class="text-white" scope="col">Oleh</th>
                            <th class="text-white" scope="col">Tanggal proses transaksi</th>
                            <th class="text-white" scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $event)
                            <tr>
                                <td class="text-nowrap"><time datetime="{{ $event->created_at->toISOString() }}">{{ $event->created_at->format('d/m/Y H:i:s') }}</time></td>
                                <td>{{ $event->pengajuan->nomor_pengajuan }}<small class="d-block text-muted">{{ $event->pengajuan->uraian_pengajuan }}</small></td>
                                <td>{{ $event->pengajuan->kode_pp }}<small class="d-block text-muted">{{ $event->pengajuan->nama_pp }}</small></td>
                                <td><span class="badge {{ $event->pengajuan->jenisBadgeClass() }}">{{ $event->pengajuan->jenis === 'impresfund' ? 'Imprestfund' : $event->pengajuan->jenisLabel() }}</span></td>
                                <td>
                                    <span class="badge {{ $event->action === 'verify' ? 'bg-light-primary' : 'bg-light-success' }}">{{ $actionLabels[$event->action] }}</span>
                                    @if($event->display_remark)<small class="d-block mt-1">{{ $event->display_remark }}</small>@endif
                                </td>
                                <td>{{ $event->actor_name }}</td>
                                <td>{{ $event->action === 'complete' ? $event->pengajuan->completion?->completion_date->format('d/m/Y') : '-' }}</td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('pencairan.show', ['pengajuan' => $event->pengajuan, 'menu' => 'history']) }}">Lihat Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada aktivitas verifikasi atau transaksi selesai untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
