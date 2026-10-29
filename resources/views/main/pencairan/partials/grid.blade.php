<section aria-labelledby="rincian-title">
    <h6 id="rincian-title">Rincian Pengajuan</h6>
    <p id="grid-help" class="text-muted small">
        Paste dari Excel mulai sel aktif dengan urutan kolom input: <strong>No, {{ $pasteColumns }}</strong>.
        No dapat diedit atau dikosongkan dan ikut disalin ke Excel. Nomor tidak diurutkan ulang otomatis.
        Jika data Excel tidak menyertakan No, mulai paste dari kolom setelah No.
        Jangan sertakan header. Subtotal Impresfund boleh disertakan. Angka menggunakan format Indonesia
        (contoh: 1.250,50). No Rekening harus berformat teks di Excel agar nol di depan tetap utuh.
        Klik atau drag untuk memilih sel; Shift+klik atau Shift+panah untuk memilih rentang.
        Klik nomor baris di sisi kiri untuk memilih seluruh baris, header untuk memilih kolom,
        atau sudut kiri atas untuk memilih seluruh tabel. Nomor baris terpisah dari input No.
        Ctrl+C/Ctrl+V (Mac: Cmd+C/Cmd+V) menyalin/menempel kolom input termasuk No dan Subtotal Impresfund.
        Salin Semua ke Excel memakai urutan yang sama sehingga dapat ditempel kembali ke sel input pertama.
        Panah, Tab/Shift+Tab dan Enter/Shift+Enter berpindah sel. Ketik untuk mengganti isi;
        klik dua kali atau F2 untuk mengedit teks, Escape untuk selesai.
        Delete/Backspace mengosongkan sel terpilih. Maksimal 500 baris.
    </p>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-primary" data-grid-add>Tambah Baris</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-grid-copy>Salin Semua ke Excel</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete>Hapus Baris Aktif/Rentang</button>
        <span data-grid-address class="spreadsheet-address align-self-center" aria-live="polite">Pilih sel</span>
    </div>
    <div data-grid-message class="alert d-none" role="status" aria-live="polite"></div>
    <div class="table-responsive border rounded spreadsheet-viewport">
        <table class="table table-bordered align-middle mb-0 spreadsheet-grid" role="grid" aria-label="Rincian pengajuan" aria-multiselectable="true" aria-describedby="grid-help">
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
