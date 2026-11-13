<section aria-labelledby="rincian-title">
    <div class="align-items-center gap-2 mb-3">
        <h6 id="rincian-title" class="mb-0">Rincian Pengajuan</h6>
    </div>
    <p id="grid-help-summary" class="visually-hidden">Gunakan tombol Bantuan untuk petunjuk pengisian dan copy-paste Excel.</p>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-primary" data-grid-add>Tambah Baris</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-grid-copy>Salin Semua ke Excel</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete>Hapus Baris Aktif/Rentang</button>
        <span data-grid-address class="spreadsheet-address align-self-center ms-auto" aria-live="polite">Pilih sel</span>
    </div>
    <div data-grid-message class="alert d-none" role="status" aria-live="polite"></div>
    <div data-grid-warnings class="alert alert-warning d-none" role="status" aria-live="polite"></div>
    <div class="table-responsive border rounded spreadsheet-viewport">
        <table class="table table-bordered align-middle mb-0 spreadsheet-grid" role="grid" aria-label="Rincian pengajuan" aria-multiselectable="true" aria-describedby="grid-help-summary">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="spreadsheet-row-header">
                        <button type="button" class="spreadsheet-selector" data-grid-select-all
                            aria-label="Pilih seluruh tabel" title="Pilih seluruh tabel">&#9698;</button>
                    </th>
                    @foreach ($columns as $column)
                        <th scope="col">
                            <div class="spreadsheet-header-tools">
                                <button type="button" class="spreadsheet-selector" data-grid-select-column="{{ $loop->index }}"
                                    aria-label="Pilih kolom {{ $column }}">
                                    {{ $column }}
                                </button>
                                @if ($column === 'Kd Jenis' && isset($jenisPenghasilanOptions))
                                    <button type="button" class="spreadsheet-header-help" data-bs-toggle="modal"
                                        data-bs-target="#jenis-penghasilan-help-modal" aria-controls="jenis-penghasilan-help-modal"
                                        aria-label="Lihat keterangan kode jenis penghasilan" title="Keterangan kode jenis penghasilan">
                                        <i class="bi bi-question-circle" aria-hidden="true"></i>
                                    </button>
                                @endif
                                @if ($column === 'Nama Bank' && isset($bankOptions))
                                    <button type="button" class="spreadsheet-header-help" data-bs-toggle="modal"
                                        data-bs-target="#bank-help-modal" aria-controls="bank-help-modal"
                                        aria-label="Lihat daftar bank" title="Daftar bank">
                                        <i class="bi bi-question-circle" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody data-grid-body></tbody>
        </table>
    </div>
    <div data-grid-errors class="text-danger small mt-2" role="status" aria-live="polite"></div>
</section>

@push('scripts')
    @isset($jenisPenghasilanOptions)
        <div class="modal fade" id="jenis-penghasilan-help-modal" tabindex="-1"
            aria-labelledby="jenis-penghasilan-help-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="jenis-penghasilan-help-title">Keterangan Kode Jenis Penghasilan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @if (count($jenisPenghasilanOptions))
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr><th scope="col">Kode</th><th scope="col">Keterangan</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($jenisPenghasilanOptions as $option)
                                            <tr>
                                                <td>{{ $option['value'] }}</td>
                                                <td>
                                                    <div>{{ $option['description'] }}</div>
                                                    @if ($option['deskripsi'] !== null && $option['deskripsi'] !== '')
                                                        <div class="text-muted" style="white-space: pre-line">{{ $option['deskripsi'] }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="mb-0">Belum ada data master jenis penghasilan.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endisset
    @if (in_array('Nama Bank', $columns, true) && isset($bankOptions))
        <div class="modal fade" id="bank-help-modal" tabindex="-1"
            aria-labelledby="bank-help-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bank-help-title">Daftar Bank</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @if (count($bankOptions))
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Kode Bank</th>
                                            <th scope="col">Inisial</th>
                                            <th scope="col">Nama Bank</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($bankOptions as $option)
                                            <tr>
                                                <td>{{ $option['code'] }}</td>
                                                <td>{{ $option['value'] ?: '-' }}</td>
                                                <td>{{ $option['description'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="form-text mb-0 mt-2">Hanya bank berstatus Aktif yang dapat dipilih.</p>
                        @else
                            <p class="mb-0">Belum ada data master bank.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="modal fade" id="grid-help-modal" tabindex="-1" aria-labelledby="grid-help-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="grid-help-title">Bantuan Rincian Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="grid-help">
                    <h6>Ketentuan pengisian</h6>
                    <p>{{ $detailHelp }}</p>
                    <h6>Copy dan paste Excel</h6>
                    <p>
                        Paste dari Excel mulai sel aktif dengan urutan kolom input: <strong>{{ $pasteColumns }}</strong>.
                        Jangan sertakan header.
                        Ctrl+C/Ctrl+V (Mac: Cmd+C/Cmd+V) menyalin/menempel kolom input.
                        Salin Semua ke Excel memakai urutan yang sama sehingga dapat ditempel kembali ke sel input pertama.
                    </p>
                    <h6>Format data</h6>
                    <p>
                        Angka menggunakan format Indonesia (contoh: 1.250,50).
                        No Rekening yang disalin dari tabel otomatis ditandai sebagai teks di Excel.
                        Saat menempel dari Excel ke tabel, formatkan kolom No Rekening sebagai teks agar nol di depan tetap utuh.
                        Maksimal 500 baris.
                    </p>
                    <h6>Pemilihan sel</h6>
                    <p>
                        Klik atau drag untuk memilih sel; Shift+klik atau Shift+panah untuk memilih rentang.
                        Klik nomor baris di sisi kiri untuk memilih seluruh baris, header untuk memilih kolom,
                        atau sudut kiri atas untuk memilih seluruh tabel.
                    </p>
                    <h6>Navigasi dan edit</h6>
                    <p class="mb-0">
                        Panah, Tab/Shift+Tab dan Enter/Shift+Enter berpindah sel. Ketik untuk mengganti isi;
                        klik dua kali atau F2 untuk mengedit teks, Escape untuk selesai.
                        Delete/Backspace mengosongkan sel terpilih.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endpush
