@extends('layouts.app')
@section('title', 'Detail Pengajuan | MONITA')
@section('content')
<div class="card"><div class="card-header d-flex justify-content-between"><h5>{{ $pengajuan->nomor_pengajuan ?? 'Draft #'.$pengajuan->id }} — {{ ucfirst($pengajuan->jenis) }}</h5><span class="badge bg-light-primary">{{ $pengajuan->statusLabel() }}</span></div>
<div class="card-body">
    @include('main.pencairan.partials.feedback')
    <p><strong>{{ $pengajuan->nama_pp }} ({{ $pengajuan->kode_pp }})</strong> · {{ $pengajuan->tahun_anggaran }} / TW {{ $pengajuan->triwulan }}</p>
    <p class="text-muted">{{ $pengajuan->uraian_pengajuan }}</p>
    <div class="row g-2 mb-3">@foreach(($pengajuan->header_snapshot ?? []) as $key => $value)@if(is_scalar($value) && !in_array($key, ['id','checked_at']) && !str_ends_with($key, '_decimal'))<div class="col-md-4"><small class="text-muted">{{ ucfirst(str_replace('_',' ',$key)) }}</small><div>{{ $value }}</div></div>@endif @endforeach</div>
    <div class="d-flex gap-4 flex-wrap mb-3"><strong>Bruto: Rp {{ number_format((float)$pengajuan->total_bruto,2,',','.') }}</strong><span>Pajak: Rp {{ number_format((float)$pengajuan->total_pajak,2,',','.') }}</span><span>Neto: Rp {{ number_format((float)$pengajuan->total_neto,2,',','.') }}</span></div>
    @if($actor['role'] === 'admin')<div class="alert alert-warning">Total antrean submitted/verified DRK ini: Rp {{ number_format((float)$pending,2,',','.') }}. Saldo tidak direservasi oleh MONITA.</div>@endif
    @php($rows = $displayRows)
    <div class="table-responsive"><table class="table table-bordered"><thead><tr>@foreach(array_keys($rows[0] ?? []) as $column)<th>{{ ucfirst(str_replace('_',' ',$column)) }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr>@foreach($row as $value)<td style="white-space:pre-wrap">{{ $value }}</td>@endforeach</tr>@empty<tr><td>Belum ada rincian.</td></tr>@endforelse</tbody></table></div>
    @if($pengajuan->histories->where('action','return')->last())<div class="alert alert-info">Remark pengembalian terakhir: {{ $pengajuan->histories->where('action','return')->last()->remark }}</div>@endif
    @php($ownPp = $actor['kode_pp'] !== '' && $actor['kode_pp'] === $pengajuan->kode_pp)
    @if($ownPp && in_array($pengajuan->status,['draft','returned']))
        <a href="{{ route('pencairan.edit',$pengajuan) }}" class="btn btn-primary mb-3">Edit / Submit</a>
    @endif
    @php($actions = $actor['role'] === 'admin' ? match($pengajuan->status) { 'submitted' => ['verify'=>'Verifikasi','return'=>'Kembalikan','cancel'=>'Batalkan'], 'returned'=>['cancel'=>'Batalkan'], 'verified'=>['return'=>'Kembalikan','complete'=>'Tandai Selesai'], default=>[] } : (in_array($pengajuan->status,['draft','returned']) ? ['cancel'=>'Batalkan'] : []))
    @if($ownPp && $pengajuan->status === 'draft') @php($actions['cancel'] = 'Batalkan') @endif
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
    @if($pengajuan->completion)<div class="alert alert-success">{{ $pengajuan->statusLabel() }} pada {{ $pengajuan->completion->completion_date->format('d/m/Y') }}. {{ $pengajuan->completion->notes }}</div>@foreach($pengajuan->completion->attachments as $file)<p><a href="{{ route('pencairan.attachment',$file->id) }}">{{ $file->original_filename }}</a></p>@endforeach @endif
    <h6>Riwayat Tindakan</h6><ul class="list-group mb-4">@foreach($pengajuan->histories as $event)<li class="list-group-item"><strong>{{ $event->action }}</strong> · {{ $event->actor_name }} ({{ $event->actor_role }}) · {{ $event->created_at->format('d/m/Y H:i:s') }}<div>{{ $event->from_status }} → {{ $event->to_status }} · Revisi {{ $event->revision_no ?? '-' }}</div>@if($event->remark)<div>{{ $event->remark }}</div>@endif</li>@endforeach</ul>
    <h6>Versi yang Disubmit</h6>@foreach($pengajuan->revisions as $revision)<details class="border rounded p-3 mb-2"><summary>Revisi {{ $revision->revision_no }} · {{ $revision->submitted_at->format('d/m/Y H:i') }}</summary><pre class="mt-2" style="white-space:pre-wrap">{{ json_encode($revision->snapshot, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endforeach
    <a href="{{ route('pencairan.pengajuan') }}" class="btn btn-outline-secondary mt-3">Kembali ke Daftar</a>
</div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-workflow-form]').forEach(form => form.addEventListener('submit', event => {
    if (!confirm('Lanjutkan tindakan ini? Perubahan akan dicatat dalam riwayat.')) { event.preventDefault(); return; }
    form.querySelector('button').disabled = true;
}));
</script>
@endpush