@php
    $rows = $displayRows;
    $columnLabels = [
        'no' => 'No', 'uraian' => 'Uraian', 'volume' => 'Volume', 'tarif' => 'Tarif', 'subtotal' => 'Subtotal',
        'nominal' => 'Nominal', 'pajak' => 'Pot Pajak', 'neto' => 'Nominal Setelah Pajak', 'bank' => 'Nama Bank',
        'nama' => 'Nama Rekening', 'rekening' => 'No Rekening', 'kode_jenis' => 'Kd Jenis', 'kewarganegaraan' => 'Kd Jenis',
        'penerima' => 'Nama Penerima', 'identitas' => 'No KTP / Paspor', 'npwp' => 'No NPWP', 'jenis_pph' => 'Jenis PPh',
    ];
    $columns = array_values(array_filter(array_keys($rows[0] ?? []), fn ($column) => $column !== 'no'));
    if ($simkug && $pengajuan->jenis === 'honor') {
        $identityIndex = array_search('identitas', $columns, true);
        $npwpIndex = array_search('npwp', $columns, true);
        if ($identityIndex !== false && $npwpIndex !== false && $npwpIndex > $identityIndex) {
            [$columns[$identityIndex], $columns[$npwpIndex]] = [$columns[$npwpIndex], $columns[$identityIndex]];
            $npwpIndex = $identityIndex;
        }
        if ($npwpIndex !== false) {
            array_splice($columns, $npwpIndex + 1, 0, ['jenis_pph']);
        }
    }
    $numericColumns = ['volume', 'tarif', 'subtotal', 'nominal', 'pajak', 'neto'];
    $nominalColumns = ['tarif', 'subtotal', 'nominal', 'pajak', 'neto'];
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
                        @php
                            $cellValue = $row[$column] ?? '';
                            if ($column === 'jenis_pph') {
                                $kodeJenis = strtoupper(trim((string) ($row['kode_jenis'] ?? '')));
                                $cellValue = $kodeJenis === '' ? '' : ($jenisPphByCode[$kodeJenis] ?? 'Tidak ditemukan di data jenis penghasilan');
                            }
                            if (in_array($column, $nominalColumns, true)) {
                                $cellValue = $formatNominal($cellValue);
                            }
                        @endphp
                        <td role="gridcell" aria-selected="false" data-grid-cell><div class="spreadsheet-cell{{ in_array($column, $numericColumns, true) ? ' spreadsheet-numeric' : '' }}" tabindex="0" title="{{ $cellValue }}">{{ $cellValue }}</div></td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="text-center text-muted p-3">Belum ada rincian.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<small class="text-muted d-block mt-2">Klik atau tarik untuk memilih rentang, Shift+panah memperluas pilihan, pojok kiri atas memilih semua. Tekan Cmd/Ctrl+C untuk menyalin.</small>
