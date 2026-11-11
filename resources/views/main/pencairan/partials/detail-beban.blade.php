@include('main.pencairan.partials.grid', [
    'columns' => ['Nominal', 'Pot Pajak', 'Nominal Setelah Pajak', 'Nama Bank', 'Nama Rekening', 'No Rekening'],
    'pasteColumns' => 'Nominal, Pot Pajak, Nominal Setelah Pajak, Nama Bank, Nama Rekening, No Rekening',
    'detailHelp' => 'Nominal Setelah Pajak otomatis dihitung dari Nominal dikurangi Pot Pajak. Isi Nominal Setelah Pajak untuk memakai nilai manual; mengubah Nominal atau Pot Pajak akan kembali menghitung otomatis.',
])
