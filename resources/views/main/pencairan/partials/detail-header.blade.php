<div data-pengajuan-header>
    <p><strong>{{ $pengajuan->nama_pp }} ({{ $pengajuan->kode_pp }})</strong> · {{ $pengajuan->tahun_anggaran }} / TW {{ $pengajuan->triwulan }}</p>
    <div class="mb-3">
        <h6 class="mb-1">Uraian Pengajuan</h6>
        <p class="text-muted mb-0">@include('main.pencairan.partials.header-value', ['value' => $pengajuan->uraian_pengajuan, 'label' => 'Uraian Pengajuan'])</p>
    </div>
    <div class="row g-2 mb-3">@foreach($visibleHeader as $key => $value)<div class="col-md-4"><small class="text-muted">{{ ucfirst(str_replace('_',' ',$key)) }}</small><div>@include('main.pencairan.partials.header-value', ['value' => $value, 'label' => ucfirst(str_replace('_', ' ', $key))])</div></div>@endforeach</div>
    <div class="d-flex gap-4 flex-wrap mb-3"><strong>Nominal: Rp @include('main.pencairan.partials.header-value', ['value' => $formatNominal($pengajuan->total_bruto), 'label' => 'Nominal'])</strong></div>
    @if($simkugHeader ?? false)
        <p class="small" role="status" aria-live="polite" data-header-copy-status></p>
    @endif
</div>
