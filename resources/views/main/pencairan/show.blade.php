@extends('layouts.app')
@section('title', 'Detail Pengajuan | MONITA')
@section('content')
<link rel="stylesheet" href="{{ asset('css/monita/pencairan-grid.css') }}">
<link rel="stylesheet" href="{{ asset('css/monita/pencairan-timeline.css') }}">
<div class="card"><div class="card-header d-flex justify-content-between"><h5>{{ $pengajuan->nomor_pengajuan ?? 'Draft #'.$pengajuan->id }} — {{ ucfirst($pengajuan->jenis) }}</h5><span class="badge bg-light-primary">{{ $pengajuan->statusLabel() }}</span></div>
<div class="card-body">
    @include('main.pencairan.partials.feedback')
    <ul class="nav nav-tabs mb-3" id="pengajuan-tabs-{{ $pengajuan->id }}" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pengajuan-detail-tab-{{ $pengajuan->id }}" data-bs-toggle="tab" data-bs-target="#pengajuan-detail-{{ $pengajuan->id }}" type="button" role="tab" aria-controls="pengajuan-detail-{{ $pengajuan->id }}" aria-selected="true">Detail</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pengajuan-riwayat-tab-{{ $pengajuan->id }}" data-bs-toggle="tab" data-bs-target="#pengajuan-riwayat-{{ $pengajuan->id }}" type="button" role="tab" aria-controls="pengajuan-riwayat-{{ $pengajuan->id }}" aria-selected="false">Riwayat</button>
        </li>
    </ul>
    <div class="tab-content" id="pengajuan-tab-content-{{ $pengajuan->id }}">
    <section class="tab-pane fade show active" id="pengajuan-detail-{{ $pengajuan->id }}" role="tabpanel" aria-labelledby="pengajuan-detail-tab-{{ $pengajuan->id }}" tabindex="0">
    <p><strong>{{ $pengajuan->nama_pp }} ({{ $pengajuan->kode_pp }})</strong> · {{ $pengajuan->tahun_anggaran }} / TW {{ $pengajuan->triwulan }}</p>
    <p class="text-muted">{{ $pengajuan->uraian_pengajuan }}</p>
    <div class="row g-2 mb-3">@foreach($headerSnapshot as $key => $value)@if(is_scalar($value) && !in_array($key, ['id','checked_at']) && !str_ends_with($key, '_decimal'))<div class="col-md-4"><small class="text-muted">{{ ucfirst(str_replace('_',' ',$key)) }}</small><div>{{ $value }}</div></div>@endif @endforeach</div>
    <div class="d-flex gap-4 flex-wrap mb-3"><strong>Bruto: Rp {{ number_format((float)$pengajuan->total_bruto,2,',','.') }}</strong></div>
    @if($actor['role'] === 'admin')<div class="alert alert-warning">Total antrean submitted/verified DRK ini: Rp {{ number_format((float)$pending,2,',','.') }}. Saldo tidak direservasi oleh MONITA.</div>@endif
    @php
        $rows = $displayRows;
        $columnLabels = [
            'no' => 'No', 'uraian' => 'Uraian', 'volume' => 'Volume', 'tarif' => 'Tarif', 'subtotal' => 'Subtotal',
            'nominal' => 'Nominal', 'pajak' => 'Pot Pajak', 'neto' => 'Nominal Setelah Pajak', 'bank' => 'Nama Bank',
            'nama' => 'Nama Rekening', 'rekening' => 'No Rekening', 'kewarganegaraan' => 'WNI / WNA',
            'penerima' => 'Nama Penerima', 'identitas' => 'No KTP / Paspor', 'npwp' => 'No NPWP',
        ];
        $columns = array_keys($rows[0] ?? []);
        $numericColumns = ['volume', 'tarif', 'subtotal', 'nominal', 'pajak', 'neto'];
    @endphp
    <div class="table-responsive border rounded spreadsheet-viewport">
        <table class="table table-bordered align-middle mb-0 spreadsheet-grid" role="grid" aria-label="Rincian pengajuan" aria-multiselectable="true">
            <thead class="table-light"><tr>
                <th scope="col" class="spreadsheet-row-header">
                    <button type="button" class="spreadsheet-selector" data-grid-select-all aria-label="Pilih seluruh tabel" title="Pilih seluruh tabel">&#9698;</button>
                </th>
                @foreach($columns as $column)
                    <th scope="col"><button type="button" class="spreadsheet-selector" data-grid-select-column="{{ $loop->index }}" aria-label="Pilih kolom {{ $columnLabels[$column] ?? ucfirst(str_replace('_', ' ', $column)) }}">{{ $columnLabels[$column] ?? ucfirst(str_replace('_', ' ', $column)) }}</button></th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr data-grid-row>
                        <th scope="row" class="spreadsheet-row-header"><button type="button" class="spreadsheet-selector" data-grid-select-row aria-label="Pilih baris {{ $loop->iteration }}">{{ $loop->iteration }}</button></th>
                        @foreach($columns as $column)
                            <td role="gridcell" aria-selected="false" data-grid-cell><div class="spreadsheet-cell{{ in_array($column, $numericColumns, true) ? ' spreadsheet-numeric' : '' }}" tabindex="0" title="{{ $row[$column] ?? '' }}">{{ $row[$column] ?? '' }}</div></td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 1 }}" class="text-center text-muted p-3">Belum ada rincian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <small class="text-muted d-block mt-2">Klik atau tarik untuk memilih rentang, Shift+panah memperluas pilihan, pojok kiri atas memilih semua. Tekan Cmd/Ctrl+C untuk menyalin.</small>
    @if($pengajuan->histories->where('action','return')->last())<div class="alert alert-info">Remark pengembalian terakhir: {{ $pengajuan->histories->where('action','return')->last()->remark }}</div>@endif
    @php
        $ownPp = $actor['kode_pp'] !== '' && $actor['kode_pp'] === $pengajuan->kode_pp;
        $actions = $actor['role'] === 'admin'
            ? match($pengajuan->status) {
                'submitted' => ['verify' => 'Verifikasi', 'return' => 'Kembalikan', 'cancel' => 'Batalkan'],
                'returned' => ['cancel' => 'Batalkan'],
                'verified' => ['return' => 'Kembalikan', 'complete' => 'Tandai Selesai'],
                default => [],
            }
            : (in_array($pengajuan->status, ['draft', 'returned']) ? ['cancel' => 'Batalkan'] : []);
        if ($ownPp && $pengajuan->status === 'draft') {
            $actions['cancel'] = 'Batalkan';
        }
    @endphp
    @if($ownPp && in_array($pengajuan->status,['draft','returned']))
        <a href="{{ route('pencairan.edit',$pengajuan) }}" class="btn btn-primary mb-3">Edit / Submit</a>
    @endif
    <div class="row g-3 mb-4">@foreach($actions as $action=>$label)
    <div class="col-md-6"><form method="POST" action="{{ route('pencairan.'.$action,$pengajuan) }}" enctype="multipart/form-data" data-workflow-form class="border rounded p-3">
        @csrf <input type="hidden" name="lock_version" value="{{ $pengajuan->lock_version }}">
        @if(in_array($action,['verify','return','cancel','complete']))<input type="hidden" name="confirmed" value="1">@endif
        @if($action === 'return' || ($action === 'cancel' && $actor['role'] === 'admin'))<label class="form-label">Alasan {{ strtolower($label) }} (wajib)</label><textarea class="form-control mb-2" name="remark" maxlength="5000" required></textarea>@endif
        @if($action === 'complete')
            <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
            <label class="form-label">Tanggal Penyelesaian</label><input class="form-control mb-2" type="date" name="completion_date" value="{{ now()->toDateString() }}" min="{{ $pengajuan->verified_at?->toDateString() }}" max="{{ now()->toDateString() }}" required>
            <label class="form-label">Catatan (opsional)</label><textarea class="form-control mb-2" name="notes" maxlength="5000"></textarea>
            <label class="form-label">Lampiran opsional (maks. 5, 10 MB/file, PDF/JPEG/PNG)</label><input class="form-control mb-2" type="file" name="attachments[]" accept="application/pdf,image/jpeg,image/png" multiple>
        @endif
        <button class="btn {{ in_array($action,['cancel','return']) ? 'btn-outline-danger' : 'btn-primary' }}">{{ $label }}</button>
    </form></div>@endforeach</div>
    </section>
    <section class="tab-pane fade" id="pengajuan-riwayat-{{ $pengajuan->id }}" role="tabpanel" aria-labelledby="pengajuan-riwayat-tab-{{ $pengajuan->id }}" tabindex="0">
    @if($pengajuan->completion)<div class="alert alert-success">{{ $pengajuan->statusLabel() }} pada {{ $pengajuan->completion->completion_date->format('d/m/Y') }}. {{ $pengajuan->completion->notes }}</div>@foreach($pengajuan->completion->attachments as $file)<p><a href="{{ route('pencairan.attachment',$file->id) }}">{{ $file->original_filename }}</a></p>@endforeach @endif
    <h6 class="mb-3">Riwayat Tindakan</h6>
    @php
        $actionLabels = [
            'create' => 'Pengajuan dibuat', 'save' => 'Draft diperbarui', 'submit' => 'Diajukan untuk verifikasi',
            'return' => 'Dikembalikan untuk perbaikan', 'verify' => 'Pengajuan diverifikasi',
            'cancel' => 'Pengajuan dibatalkan', 'complete' => 'Proses diselesaikan',
        ];
        $actionIcons = [
            'create' => '＋', 'save' => '✎', 'submit' => '➤', 'return' => '↩',
            'verify' => '✓', 'cancel' => '×', 'complete' => '✓',
        ];
        $actionStyles = [
            'create' => 'created', 'save' => 'saved', 'submit' => 'submitted', 'return' => 'returned',
            'verify' => 'verified', 'cancel' => 'cancelled', 'complete' => 'completed',
        ];
    @endphp
    @if($pengajuan->histories->isNotEmpty())
        <ol class="workflow-timeline list-unstyled" aria-label="Linimasa riwayat pengajuan">
            @foreach($pengajuan->histories as $event)
                @php
                    $fromStatus = $event->from_status ? (\App\Enums\PencairanStatus::tryFrom($event->from_status)?->label($pengajuan->jenis) ?? ucfirst($event->from_status)) : null;
                    $toStatus = $event->to_status ? (\App\Enums\PencairanStatus::tryFrom($event->to_status)?->label($pengajuan->jenis) ?? ucfirst($event->to_status)) : 'Status awal';
                @endphp
                <li class="workflow-timeline-item {{ $loop->last ? 'is-latest' : '' }}">
                    <span class="workflow-timeline-marker workflow-timeline-marker--{{ $actionStyles[$event->action] ?? 'default' }}" aria-hidden="true">{{ $actionIcons[$event->action] ?? '•' }}</span>
                    <article class="workflow-timeline-card">
                        <div class="workflow-timeline-heading">
                            <h6 class="mb-1">{{ $actionLabels[$event->action] ?? ucfirst(str_replace('_', ' ', $event->action)) }}</h6>
                            @if($loop->last)<span class="badge bg-light-primary">Terbaru</span>@endif
                        </div>
                        <div class="workflow-timeline-meta">
                            <span>{{ $event->actor_name }} <span class="text-muted">({{ $event->actor_role === 'admin' ? 'Admin' : 'Pengguna' }})</span></span>
                            <time datetime="{{ $event->created_at->toISOString() }}">{{ $event->created_at->format('d/m/Y H:i') }}</time>
                        </div>
                        <div class="workflow-timeline-transition">
                            @if($fromStatus)<span>{{ $fromStatus }}</span><span class="workflow-timeline-arrow" aria-hidden="true">→</span>@else<span class="text-muted">Pengajuan dimulai</span><span class="workflow-timeline-arrow" aria-hidden="true">→</span>@endif
                            <strong>{{ $toStatus }}</strong>
                            @if($event->revision_no)<span class="workflow-timeline-revision">Revisi {{ $event->revision_no }}</span>@endif
                        </div>
                        @if($event->remark)<div class="workflow-timeline-remark"><strong>Catatan:</strong> {{ $event->remark }}</div>@endif
                    </article>
                </li>
            @endforeach
        </ol>
    @else
        <p class="text-muted">Belum ada riwayat tindakan.</p>
    @endif
    <h6>Versi yang Disubmit</h6>@foreach($pengajuan->revisions as $revision)<details class="border rounded p-3 mb-2"><summary>Revisi {{ $revision->revision_no }} · {{ $revision->submitted_at->format('d/m/Y H:i') }}</summary><pre class="mt-2" style="white-space:pre-wrap">{{ json_encode($revision->snapshot, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endforeach
    </section>
    </div>
    <a href="{{ route('pencairan.pengajuan') }}" class="btn btn-outline-secondary mt-3">Kembali ke Daftar</a>
</div></div>
@endsection
@push('scripts')
<script type="module">
    import { mountReadOnlySpreadsheetGrid } from "{{ asset('js/monita/pencairan-detail-grid.js') }}";
    mountReadOnlySpreadsheetGrid(document.querySelector('[aria-label="Rincian pengajuan"]'));
</script>
@endpush
@push('scripts')
<script>
document.querySelectorAll('[data-workflow-form]').forEach(form => form.addEventListener('submit', event => {
    if (!confirm('Lanjutkan tindakan ini? Perubahan akan dicatat dalam riwayat.')) { event.preventDefault(); return; }
    form.querySelector('button').disabled = true;
}));
</script>
@endpush