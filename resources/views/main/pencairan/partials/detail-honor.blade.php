<p class="text-muted small">
    Isi kewarganegaraan dengan WNI atau WNA. Potongan Pajak diisi dalam rupiah (isi 0 jika tidak ada);
    Total Diterima diisi manual dalam rupiah, tidak dihitung dari nominal honor dan pajak.
    No NPWP boleh kosong jika belum tersedia.
    Format Nomor Rekening, No KTP / Paspor, dan No NPWP sebagai teks di Excel agar tidak berubah.
</p>
<datalist id="honor-kewarganegaraan">
    <option value="WNI"></option>
    <option value="WNA"></option>
</datalist>
@include('main.pencairan.partials.grid', [
    'columns' => ['No', 'WNI / WNA', 'Nominal Honor', 'Potongan Pajak', 'Total Diterima', 'Nama Penerima', 'Nama Rekening', 'Nama Bank', 'Nomor Rekening', 'No KTP / Paspor', 'No NPWP'],
    'pasteColumns' => 'WNI / WNA, Nominal Honor, Potongan Pajak, Total Diterima, Nama Penerima, Nama Rekening, Nama Bank, Nomor Rekening, No KTP / Paspor, No NPWP',
])
