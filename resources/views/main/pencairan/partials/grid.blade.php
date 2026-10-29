<section aria-labelledby="rincian-title">
    <h6 id="rincian-title">Rincian Pengajuan</h6>
    <p id="grid-help" class="text-muted small">
        Paste dari Excel mulai sel aktif dengan urutan kolom input: <strong>{{ $pasteColumns }}</strong>.
        Jangan sertakan header, nomor, atau kolom hasil hitung. Angka menggunakan format Indonesia
        (contoh: 1.250,50). No Rekening harus berformat teks di Excel agar nol di depan tetap utuh.
        Klik sel lalu Shift+klik sel lain untuk memilih rentang. Ctrl+C menyalin kolom input pada rentang;
        tanpa rentang, copy/paste teks di dalam sel tetap dapat digunakan.
        Tombol Salin Semua ke Excel menyalin seluruh kolom termasuk No dan hasil hitung, tanpa header tabel.
        Untuk paste kembali ke form, ambil hanya kolom input sesuai urutan di atas.
        Tab/Shift+Tab berpindah sel. Maksimal 500 baris.
    </p>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-primary" data-grid-add>Tambah Baris</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-grid-copy>Salin Semua ke Excel</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete>Hapus Baris Aktif/Rentang</button>
    </div>
    <div data-grid-message class="alert d-none" role="status" aria-live="polite"></div>
    <div class="table-responsive border rounded">
        <table class="table table-bordered align-middle mb-0" aria-describedby="grid-help" style="min-width: 850px">
            <thead class="table-light">
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody data-grid-body></tbody>
        </table>
    </div>
    <div data-grid-errors class="text-danger small mt-2" role="status" aria-live="polite"></div>
</section>
