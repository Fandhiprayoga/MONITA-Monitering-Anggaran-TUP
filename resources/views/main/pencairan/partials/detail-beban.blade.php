@include('main.pencairan.partials.grid', [
    'columns' => ['Nominal', 'Pot Pajak', 'Nominal Setelah Pajak', 'Nama Bank', 'Nama Rekening', 'No Rekening'],
    'pasteColumns' => 'Nominal, Pot Pajak, Nominal Setelah Pajak, Nama Bank, Nama Rekening, No Rekening',
    'detailHelp' => 'Nominal Setelah Pajak diisi manual dalam rupiah, tidak dihitung otomatis dari Nominal dan Pot Pajak.',
])
