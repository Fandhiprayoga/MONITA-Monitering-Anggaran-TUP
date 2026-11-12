@php
    $simkugHeader = [
        'tahun_anggaran' => $pengajuan->tahun_anggaran,
        'triwulan' => $pengajuan->triwulan,
        'uraian_pengajuan' => $pengajuan->uraian_pengajuan,
    ] + $visibleHeader + ['nominal' => $formatNominal($pengajuan->total_bruto)];
@endphp
<details class="mb-3">
    <summary>Salin data header</summary>
    <div class="row g-2 mt-1 mb-3">
        @foreach($simkugHeader as $key => $value)
            @php
                $fieldId = 'simkug-'.$pengajuan->id.'-'.$loop->index;
                $fieldLabel = ucfirst(str_replace('_', ' ', $key));
            @endphp
            <div class="col-md-4">
                <label class="form-label" for="{{ $fieldId }}">{{ $fieldLabel }}</label>
                <div class="input-group">
                    @if(in_array($key, ['uraian_pengajuan', 'uraian_drk']))
                        <textarea class="form-control" id="{{ $fieldId }}" rows="2" readonly>{{ $value }}</textarea>
                    @else
                        <input type="text" class="form-control" id="{{ $fieldId }}" value="{{ $value }}" readonly>
                    @endif
                    <button type="button" class="btn btn-outline-secondary" data-simkug-copy aria-label="Salin {{ $fieldLabel }}" title="Salin {{ $fieldLabel }}"><i class="bi bi-clipboard" aria-hidden="true"></i></button>
                </div>
            </div>
        @endforeach
    </div>
    <p class="small mb-3" role="status" aria-live="polite" data-simkug-copy-status></p>
</details>
