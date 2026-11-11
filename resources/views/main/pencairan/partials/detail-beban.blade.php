@include('main.pencairan.partials.grid', [
    'columns' => ['Nominal', 'Pot Pajak', 'Nominal Setelah Pajak', 'Nama Bank', 'Nama Rekening', 'No Rekening'],
    'pasteColumns' => 'Nominal, Pot Pajak, Nominal Setelah Pajak, Nama Bank, Nama Rekening, No Rekening',
    'detailHelp' => 'Nominal Setelah Pajak otomatis dihitung dari Nominal dikurangi Pot Pajak. Isi Nominal Setelah Pajak untuk memakai nilai manual; mengubah Nominal atau Pot Pajak akan kembali menghitung otomatis. Nilai manual atau hasil paste yang berbeda dari perhitungan ditandai kuning pada sel, dengan peringatan di atas tabel yang mencantumkan nomor baris dan nilai yang seharusnya, tanpa mengubah nilai isian atau menghalangi penyimpanan.',
])
