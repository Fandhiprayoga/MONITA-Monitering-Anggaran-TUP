@extends('layouts.app')
@section('title', 'Detail Pengajuan | MONITA')
@section('content')
@php
    $showSimkug = $pengajuan->status === 'verified'
        || ($pengajuan->jenis === 'impresfund' && $pengajuan->status === 'completed');
    $simkugActive = $showSimkug && (request('tab') === 'simkug' || session()->hasOldInput('simkug'));
@endphp
<link rel="stylesheet" href="{{ asset('css/monita/pencairan-grid.css') }}">
<link rel="stylesheet" href="{{ asset('css/monita/pencairan-timeline.css') }}">
<div class="card"><div class="card-header d-flex justify-content-between"><h5>{{ $pengajuan->nomor_pengajuan ?? 'Draft #'.$pengajuan->id }} — {{ ucfirst($pengajuan->jenis) }}</h5><span class="badge {{ $pengajuan->statusBadgeClass() }}">{{ $pengajuan->statusLabel() }}</span></div>
<div class="card-body">
    @include('main.pencairan.partials.feedback')
    <ul class="nav nav-tabs mb-3" id="pengajuan-tabs-{{ $pengajuan->id }}" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $simkugActive ? '' : 'active' }}" id="pengajuan-detail-tab-{{ $pengajuan->id }}" data-bs-toggle="tab" data-bs-target="#pengajuan-detail-{{ $pengajuan->id }}" type="button" role="tab" aria-controls="pengajuan-detail-{{ $pengajuan->id }}" aria-selected="{{ $simkugActive ? 'false' : 'true' }}">Detail</button>
        </li>
        @if($showSimkug)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $simkugActive ? 'active' : '' }}" id="pengajuan-simkug-tab-{{ $pengajuan->id }}" data-bs-toggle="tab" data-bs-target="#pengajuan-simkug-{{ $pengajuan->id }}" type="button" role="tab" aria-controls="pengajuan-simkug-{{ $pengajuan->id }}" aria-selected="{{ $simkugActive ? 'true' : 'false' }}">SIMKUG</button>
            </li>
        @endif
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pengajuan-riwayat-tab-{{ $pengajuan->id }}" data-bs-toggle="tab" data-bs-target="#pengajuan-riwayat-{{ $pengajuan->id }}" type="button" role="tab" aria-controls="pengajuan-riwayat-{{ $pengajuan->id }}" aria-selected="false">Riwayat</button>
        </li>
    </ul>
    <div class="tab-content" id="pengajuan-tab-content-{{ $pengajuan->id }}">
    <section class="tab-pane fade {{ $simkugActive ? '' : 'show active' }}" id="pengajuan-detail-{{ $pengajuan->id }}" role="tabpanel" aria-labelledby="pengajuan-detail-tab-{{ $pengajuan->id }}" tabindex="0">
    @include('main.pencairan.partials.detail-data', ['simkug' => false])
    @if($pengajuan->histories->where('action','return')->last())<div class="alert alert-info">Remark pengembalian terakhir: {{ $pengajuan->histories->where('action','return')->last()->remark }}</div>@endif
    @php
        $ownPp = $actor['role'] === 'admin' || ($actor['kode_pp'] !== '' && $actor['kode_pp'] === $pengajuan->kode_pp);
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
    </section>
    @if($showSimkug)
        <section class="tab-pane fade {{ $simkugActive ? 'show active' : '' }}" id="pengajuan-simkug-{{ $pengajuan->id }}" role="tabpanel" aria-labelledby="pengajuan-simkug-tab-{{ $pengajuan->id }}" tabindex="0" data-simkug-panel>
            @if($pengajuan->jenis === 'impresfund')
                @include('main.pencairan.partials.simkug-impresfund')
            @else
                @include('main.pencairan.partials.detail-data', ['simkug' => true])
            @endif
        </section>
    @endif
    <section class="tab-pane fade" id="pengajuan-riwayat-{{ $pengajuan->id }}" role="tabpanel" aria-labelledby="pengajuan-riwayat-tab-{{ $pengajuan->id }}" tabindex="0">
    @if($pengajuan->completion)<div class="alert alert-success">{{ $pengajuan->statusLabel() }} pada {{ $pengajuan->completion->completion_date->format('d/m/Y') }}. {{ $pengajuan->completion->notes }}</div>@foreach($pengajuan->completion->attachments as $file)<p><a href="{{ route('pencairan.attachment',$file->id) }}">{{ $file->original_filename }}</a></p>@endforeach @endif
    <h6 class="mb-3">Riwayat Tindakan</h6>
    @php
        $actionLabels = [
            'create' => 'Pengajuan dibuat', 'save' => 'Data pengajuan diperbarui', 'submit' => 'Diajukan untuk verifikasi',
            'return' => 'Dikembalikan untuk perbaikan', 'verify' => 'Pengajuan diverifikasi',
            'cancel' => 'Pengajuan dibatalkan', 'complete' => 'Proses diselesaikan',
            'simkug' => 'Data SIMKUG disimpan',
        ];
        $actionIcons = [
            'create' => '＋', 'save' => '✎', 'submit' => '➤', 'return' => '↩',
            'verify' => '✓', 'cancel' => '×', 'complete' => '✓',
        ];
        $actionStyles = [
            'create' => 'created', 'save' => 'saved', 'submit' => 'submitted', 'return' => 'returned',
            'verify' => 'verified', 'cancel' => 'cancelled', 'complete' => 'completed', 'simkug' => 'saved',
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
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3" data-workflow-actions>
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Kembali ke Daftar</a>
        @if($ownPp && in_array($pengajuan->status,['draft','returned']))
            <a href="{{ route('pencairan.edit', ['pengajuan' => $pengajuan, 'menu' => $activeMenu]) }}" class="btn btn-primary">Edit / Submit</a>
        @endif
        @if($actor['role'] === 'admin' && in_array($pengajuan->status, ['submitted', 'verified'], true))
            <a href="{{ route('pencairan.edit', ['pengajuan' => $pengajuan, 'menu' => $activeMenu]) }}" class="btn btn-primary">Edit Data Pengajuan</a>
        @endif
        @foreach($actions as $action => $label)
            <button type="button" class="btn {{ in_array($action,['cancel','return']) ? 'btn-outline-danger' : 'btn-primary' }}{{ $loop->first ? ' ms-auto' : '' }}" data-bs-toggle="modal" data-bs-target="#workflow-{{ $action }}-{{ $pengajuan->id }}">{{ $label }}</button>
        @endforeach
    </div>
</div></div>
@foreach($actions as $action => $label)
    @php $modalId = 'workflow-'.$action.'-'.$pengajuan->id; @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true" data-workflow-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('pencairan.'.$action,$pengajuan) }}" enctype="multipart/form-data" data-workflow-form>
                    @csrf
                    <input type="hidden" name="lock_version" value="{{ $pengajuan->lock_version }}">
                    <input type="hidden" name="confirmed" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="{{ $modalId }}-title">Konfirmasi {{ $label }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p>Anda yakin ingin {{ $action === 'complete' ? 'menyelesaikan' : strtolower($label) }} pengajuan ini? Tindakan ini akan dicatat dalam riwayat.</p>
                        @if(in_array($action, ['return', 'cancel']))
                            <label class="form-label" for="{{ $modalId }}-remark">Alasan {{ strtolower($label) }} (wajib)</label>
                            <textarea class="form-control" id="{{ $modalId }}-remark" name="remark" rows="4" maxlength="5000" required></textarea>
                        @endif
                        @if($action === 'complete')
                            <input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}">
                            <label class="form-label" for="{{ $modalId }}-date">Tanggal Penyelesaian</label>
                            <input class="form-control mb-2" id="{{ $modalId }}-date" type="date" name="completion_date" value="{{ now()->toDateString() }}" min="{{ $pengajuan->verified_at?->toDateString() }}" max="{{ now()->toDateString() }}" required>
                            <label class="form-label" for="{{ $modalId }}-notes">Catatan (opsional)</label>
                            <textarea class="form-control mb-2" id="{{ $modalId }}-notes" name="notes" maxlength="5000"></textarea>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn {{ in_array($action,['cancel','return']) ? 'btn-danger' : 'btn-primary' }}">Ya, {{ $label }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
@push('scripts')
<script type="module">
    import { mountReadOnlySpreadsheetGrid } from "{{ asset('js/monita/pencairan-detail-grid.js') }}";
    import { mountSimkugCopy } from "{{ asset('js/monita/pencairan-simkug.js') }}";
    import { mountSimkugImpresfund } from "{{ asset('js/monita/pencairan-simkug-impresfund.js') }}";
    document.querySelectorAll('[data-detail-grid]').forEach(mountReadOnlySpreadsheetGrid);
    document.querySelectorAll('[data-simkug-panel]').forEach(mountSimkugCopy);
    document.querySelectorAll('[data-simkug-impresfund]').forEach(mountSimkugImpresfund);
</script>
@endpush
@push('scripts')
<script>
document.querySelectorAll('[data-workflow-form]').forEach(form => form.addEventListener('submit', event => {
    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
    const remark = form.querySelector('[name="remark"]');
    if (remark) {
        remark.value = remark.value.trim();
        if (!form.reportValidity()) { event.preventDefault(); return; }
    }
    form.dataset.submitting = 'true';
    form.querySelectorAll('button').forEach(button => { button.disabled = true; });
    form.querySelector('[type="submit"]').textContent = 'Memproses…';
}));
document.querySelectorAll('[data-workflow-modal]').forEach(modal => {
    modal.addEventListener('shown.bs.modal', () => modal.querySelector('textarea, input:not([type="hidden"]), [type="submit"]')?.focus());
    modal.addEventListener('hide.bs.modal', event => {
        if (modal.querySelector('form').dataset.submitting === 'true') event.preventDefault();
    });
});
</script>
@endpush