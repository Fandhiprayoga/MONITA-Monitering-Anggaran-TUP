<section aria-labelledby="rincian-title">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h6 id="rincian-title" class="mb-0">Rincian Pengajuan</h6>
        <button id="grid-help-button" type="button" class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="modal" data-bs-target="#grid-help-modal" aria-controls="grid-help-modal"
            aria-haspopup="dialog">Bantuan</button>
    </div>
    <p id="grid-help-summary" class="visually-hidden">Gunakan tombol Bantuan untuk petunjuk pengisian dan copy-paste Excel.</p>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-primary" data-grid-add>Tambah Baris</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-grid-copy>Salin Semua ke Excel</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete>Hapus Baris Aktif/Rentang</button>
        <span data-grid-address class="spreadsheet-address align-self-center" aria-live="polite">Pilih sel</span>
    </div>
    <div data-grid-message class="alert d-none" role="status" aria-live="polite"></div>
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
                            <button type="button" class="spreadsheet-selector" data-grid-select-column="{{ $loop->index }}"
                                aria-label="Pilih kolom {{ $column }}">
                                {{ $column }}
                            </button>
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
                        Paste dari Excel mulai sel aktif dengan urutan kolom input: <strong>No, {{ $pasteColumns }}</strong>.
                        Jangan sertakan header. Jika data Excel tidak menyertakan No, mulai paste dari kolom setelah No.
                        Ctrl+C/Ctrl+V (Mac: Cmd+C/Cmd+V) menyalin/menempel kolom input.
                        Salin Semua ke Excel memakai urutan yang sama sehingga dapat ditempel kembali ke sel input pertama.
                    </p>
                    <h6>Format data</h6>
                    <p>
                        No dapat diedit atau dikosongkan dan ikut disalin ke Excel. Nomor tidak diurutkan ulang otomatis.
                        Angka menggunakan format Indonesia (contoh: 1.250,50).
                        No Rekening harus berformat teks di Excel agar nol di depan tetap utuh. Maksimal 500 baris.
                    </p>
                    <h6>Pemilihan sel</h6>
                    <p>
                        Klik atau drag untuk memilih sel; Shift+klik atau Shift+panah untuk memilih rentang.
                        Klik nomor baris di sisi kiri untuk memilih seluruh baris, header untuk memilih kolom,
                        atau sudut kiri atas untuk memilih seluruh tabel. Nomor baris terpisah dari input No.
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
