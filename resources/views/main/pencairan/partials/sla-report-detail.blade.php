<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <form method="GET" action="{{ route('pencairan.sla-report') }}" class="d-flex align-items-center gap-2" aria-label="Jumlah data Report SLA">
        @foreach (\Illuminate\Support\Arr::except($filterQuery, ['per_page']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <label for="report-sla-per-page" class="mb-0">Tampilkan</label>
        <select id="report-sla-per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit()">
            @foreach ([10, 25, 50, 100] as $size)
                <option value="{{ $size }}" @selected($items->perPage() === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </form>
    <div class="d-flex align-items-center gap-3">
        <small class="text-muted">{{ $items->total() }} pengajuan</small>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('pencairan.sla-report.export', \Illuminate\Support\Arr::except($filterQuery, ['tab', 'per_page', 'page'])) }}">
            <i class="ti ti-download me-1" aria-hidden="true"></i>Export CSV
        </a>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-bordered align-middle mb-0" aria-label="Detail Pengajuan Report SLA">
        <thead>
            <tr>
                <th scope="col">Pengajuan</th>
                <th scope="col">Unit</th>
                <th scope="col">Jenis</th>
                <th scope="col">Tgl Pengajuan</th>
                <th scope="col">Tgl Verifikasi</th>
                <th scope="col">Tgl Transaksi</th>
                <th scope="col">Tgl Selesai</th>
                <th scope="col">Durasi Verifikasi</th>
                <th scope="col">Durasi Transaksi</th>
                <th scope="col">Status</th>
                <th scope="col">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $row)
                @php
                    $pengajuan = $row['pengajuan'];
                    $verification = $row['stages']['verifikasi'];
                    $transaction = $row['stages'][$row['transaction_stage']];
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $pengajuan->nomor_pengajuan ?? '—' }}</div>
                        <small class="text-muted">{{ $pengajuan->uraian_pengajuan }}</small>
                    </td>
                    <td>{{ $pengajuan->kode_pp }}<small class="d-block text-muted">{{ $pengajuan->nama_pp }}</small></td>
                    <td><span class="badge {{ $pengajuan->jenisBadgeClass() }}">{{ $pengajuan->jenisLabel() }}</span></td>
                    <td class="text-nowrap">{{ $pengajuan->first_submitted_at->format('d/m/Y') }}</td>
                    <td class="text-nowrap">{{ ($verification['finished_at'] ?? null)?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-nowrap">{{ $pengajuan->completion?->completion_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-nowrap">{{ ($transaction['finished_at'] ?? null)?->format('d/m/Y') ?? '—' }}</td>
                    @foreach (['verifikasi', $row['transaction_stage']] as $stage)
                        <td>
                            @include('main.pencairan.partials.sla-report-duration', [
                                'measurement' => $row['stages'][$stage],
                                'active' => $row['active_stage'] === $stage ? $row['active'] : null,
                            ])
                        </td>
                    @endforeach
                    <td><span class="badge text-wrap {{ $pengajuan->statusBadgeClass() }}">{{ $pengajuan->statusLabel() }}</span></td>
                    <td class="text-center">
                        <a class="btn btn-sm btn-outline-primary"
                            href="{{ route('pencairan.show', ['pengajuan' => $pengajuan, 'menu' => 'sla-report', 'page' => $items->currentPage()] + \Illuminate\Support\Arr::except($filterQuery, ['tab'])) }}"
                            aria-label="Lihat detail pengajuan {{ $pengajuan->nomor_pengajuan }}" title="Lihat detail">
                            <i class="ti ti-eye" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted py-4">Tidak ada pengajuan untuk filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $items->links() }}</div>
