<div data-pengajuan-header>
    <p><strong>{{ $pengajuan->nama_pp }} ({{ $pengajuan->kode_pp }})</strong> · {{ $pengajuan->tahun_anggaran }} / TW {{ $pengajuan->triwulan }}</p>
    <div class="mb-3">
        <h6 class="mb-1">Uraian Pengajuan</h6>
        <p class="text-muted mb-0">{{ $pengajuan->uraian_pengajuan }}</p>
    </div>
    <div class="row g-2 mb-3">@foreach($visibleHeader as $key => $value)<div class="col-md-4"><small class="text-muted">{{ ucfirst(str_replace('_',' ',$key)) }}</small><div>{{ $value }}</div></div>@endforeach</div>
    <div class="d-flex gap-4 flex-wrap mb-3"><strong>Nominal: Rp {{ $formatNominal($pengajuan->total_bruto) }}</strong></div>
</div>
