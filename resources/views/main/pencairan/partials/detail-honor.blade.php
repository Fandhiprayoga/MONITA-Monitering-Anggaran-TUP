@include('main.pencairan.partials.grid', [
    'columns' => ['Kd Jenis', 'Nominal Honor', 'Potongan Pajak', 'Total Diterima', 'Nama Penerima', 'Nama Rekening', 'Nama Bank', 'Nomor Rekening', 'No KTP / Paspor', 'No NPWP'],
    'pasteColumns' => 'Kd Jenis, Nominal Honor, Potongan Pajak, Total Diterima, Nama Penerima, Nama Rekening, Nama Bank, Nomor Rekening, No KTP / Paspor, No NPWP',
    'jenisPenghasilanOptions' => $jenisPenghasilanOptions,
    'detailHelp' => 'Pilih Kd Jenis dari master data jenis penghasilan. Potongan Pajak dihitung otomatis sebagai Nominal Honor dikali tarif PPh pada kode terpilih, dibulatkan ke 2 desimal.
        Total Diterima dihitung otomatis dari Nominal Honor dikurangi Potongan Pajak.
        No NPWP boleh kosong jika belum tersedia.
        Format Nomor Rekening, No KTP / Paspor, dan No NPWP sebagai teks di Excel agar tidak berubah.',
])
