@php
    $simkugColumns = [
        'kode_akun' => 'Kode Akun', 'nama_akun' => 'Nama Akun', 'dc' => 'DC',
        'keterangan' => 'Keterangan', 'nilai' => 'Nilai', 'kode_pp' => 'Kode PP',
        'nama_pp' => 'Nama PP', 'kode_drk' => 'Kode DRK', 'nama_drk' => 'Nama DRK',
    ];
    $canEditSimkug = $actor['role'] === 'admin' && $pengajuan->status === 'verified';
@endphp
<div data-simkug-impresfund>
    <p class="text-muted">Satu baris berdasarkan total pengajuan. Kolom selain D/C berasal dari data pengajuan dan hanya dapat diubah dari pengajuan.</p>
    @if($pengajuan->simkug_data === null)
        <p class="small text-warning">Data awal dari pengajuan; belum pernah disimpan sebagai data SIMKUG. Pilih D (Debit) atau C (Credit) sebelum menyimpan.</p>
    @endif
    <p class="small">Klik/drag untuk memilih rentang, klik header atau nomor baris untuk memilih kolom/baris. Ctrl+C menyalin pilihan. @if($canEditSimkug)Klik dua kali atau F2 untuk edit, Escape untuk selesai; Tab/panah berpindah sel. Paste Excel satu baris tanpa header; DC hanya D/C.@endif</p>
    <form method="POST" action="{{ route('pencairan.simkug.save', $pengajuan) }}" data-simkug-form data-editable="{{ $canEditSimkug ? 'true' : 'false' }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="lock_version" value="{{ $pengajuan->lock_version }}">
        <div class="table-responsive border rounded spreadsheet-viewport">
            <table class="table table-bordered align-middle mb-0 spreadsheet-grid" role="grid" aria-label="Data SIMKUG Imprestfund" aria-multiselectable="true">
                <thead class="table-light"><tr>
                    <th scope="col" class="spreadsheet-row-header"><button type="button" class="spreadsheet-selector" data-grid-select-all aria-label="Pilih seluruh tabel">&#9698;</button></th>
                    @foreach($simkugColumns as $label)
                        <th scope="col"><button type="button" class="spreadsheet-selector" data-grid-select-column="{{ $loop->index }}" aria-label="Pilih kolom {{ $label }}">{{ $label }}</button></th>
                    @endforeach
                </tr></thead>
                <tbody><tr data-grid-row>
                    <th scope="row" class="spreadsheet-row-header"><button type="button" class="spreadsheet-selector" data-grid-select-row aria-label="Pilih baris 1">1</button></th>
                    @foreach($simkugColumns as $key => $label)
                        @php
                            $value = $key === 'dc' && $canEditSimkug ? old('simkug.dc', $simkugData[$key]) : $simkugData[$key];
                            $fieldId = 'simkug-impresfund-'.$pengajuan->id.'-'.$key;
                        @endphp
                        <td role="gridcell" aria-selected="false" aria-readonly="{{ $key === 'dc' && $canEditSimkug ? 'false' : 'true' }}" data-grid-cell style="min-width: {{ $key === 'dc' ? '145' : '200' }}px;">
                            @if($key === 'dc')
                                <select id="{{ $fieldId }}" name="simkug[{{ $key }}]" class="spreadsheet-cell" aria-label="{{ $label }}" data-simkug-field @disabled(!$canEditSimkug) required>
                                    <option value="">Pilih D/C</option>
                                    <option value="D" @selected($value === 'D')>D (Debit)</option>
                                    <option value="C" @selected($value === 'C')>C (Credit)</option>
                                </select>
                            @elseif(in_array($key, ['keterangan', 'nama_drk'], true))
                                <textarea id="{{ $fieldId }}" name="simkug[{{ $key }}]" class="spreadsheet-cell" aria-label="{{ $label }}" rows="1" maxlength="5000" data-simkug-field disabled required>{{ $value }}</textarea>
                            @else
                                <input id="{{ $fieldId }}" name="simkug[{{ $key }}]" type="text" class="spreadsheet-cell{{ $key === 'nilai' ? ' spreadsheet-numeric' : '' }}" aria-label="{{ $label }}" value="{{ $value }}" maxlength="{{ $key === 'nilai' ? 64 : (str_starts_with($key, 'kode_') ? 100 : 255) }}" data-simkug-field disabled required>
                            @endif
                            @error('simkug.'.$key)<small class="text-danger">{{ $message }}</small>@enderror
                        </td>
                    @endforeach
                </tr></tbody>
            </table>
        </div>
        <p class="small text-danger mt-2" role="status" aria-live="polite" data-simkug-grid-status></p>
        @if($canEditSimkug)
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Simpan Data SIMKUG</button>
                <small class="text-muted">Nilai memakai format Indonesia, contoh 1.250,50. DC wajib dipilih.</small>
            </div>
        @else
            <p class="small text-muted mt-3">Data hanya dapat diubah admin saat status Terverifikasi.</p>
        @endif
    </form>
</div>
