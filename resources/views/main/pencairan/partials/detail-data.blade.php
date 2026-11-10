@php
    $headerFieldOrder = ['kode_akun', 'nama_akun', 'kode_pp', 'nama_pp', 'kode_drk', 'uraian_drk', 'saldo_awal', 'anggaran_terpakai', 'saldo_terkini', 'tipe', 'drk_tup'];
    $orderedHeaderSnapshot = array_replace(array_fill_keys($headerFieldOrder, null), $headerSnapshot);
    $visibleHeader = array_filter($orderedHeaderSnapshot, fn ($value, $key) => is_scalar($value) && !in_array($key, ['id', 'checked_at']) && !str_ends_with($key, '_decimal'), ARRAY_FILTER_USE_BOTH);
@endphp
@if($simkug)
    @php
        $simkugHeader = [
            'tahun_anggaran' => $pengajuan->tahun_anggaran,
            'triwulan' => $pengajuan->triwulan,
            'uraian_pengajuan' => $pengajuan->uraian_pengajuan,
        ] + $visibleHeader + ['nominal' => number_format((float) $pengajuan->total_bruto, 2, ',', '.')];
    @endphp
    <div class="row g-2 mb-3">
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
@else
    <p><strong>{{ $pengajuan->nama_pp }} ({{ $pengajuan->kode_pp }})</strong> · {{ $pengajuan->tahun_anggaran }} / TW {{ $pengajuan->triwulan }}</p>
    <p class="text-muted">{{ $pengajuan->uraian_pengajuan }}</p>
    <div class="row g-2 mb-3">@foreach($visibleHeader as $key => $value)<div class="col-md-4"><small class="text-muted">{{ ucfirst(str_replace('_',' ',$key)) }}</small><div>{{ $value }}</div></div>@endforeach</div>
    <div class="d-flex gap-4 flex-wrap mb-3"><strong>Nominal: Rp {{ number_format((float)$pengajuan->total_bruto,2,',','.') }}</strong></div>
@endif
@php
    $rows = $displayRows;
    $columnLabels = [
        'no' => 'No', 'uraian' => 'Uraian', 'volume' => 'Volume', 'tarif' => 'Tarif', 'subtotal' => 'Subtotal',
        'nominal' => 'Nominal', 'pajak' => 'Pot Pajak', 'neto' => 'Nominal Setelah Pajak', 'bank' => 'Nama Bank',
        'nama' => 'Nama Rekening', 'rekening' => 'No Rekening', 'kewarganegaraan' => 'WNI / WNA',
        'penerima' => 'Nama Penerima', 'identitas' => 'No KTP / Paspor', 'npwp' => 'No NPWP',
    ];
    $columns = array_values(array_filter(array_keys($rows[0] ?? []), fn ($column) => $column !== 'no'));
    $numericColumns = ['volume', 'tarif', 'subtotal', 'nominal', 'pajak', 'neto'];
@endphp
<div class="table-responsive border rounded spreadsheet-viewport">
    <table class="table table-bordered align-middle mb-0 spreadsheet-grid" role="grid" aria-label="{{ $simkug ? 'Rincian pengajuan SIMKUG' : 'Rincian pengajuan' }}" aria-multiselectable="true" data-detail-grid>
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
