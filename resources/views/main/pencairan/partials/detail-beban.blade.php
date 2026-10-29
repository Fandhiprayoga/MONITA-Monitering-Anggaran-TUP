<p class="text-muted small">
    Nominal Setelah Pajak diisi manual dalam rupiah, tidak dihitung otomatis dari Nominal dan Pot Pajak.
</p>
@include('main.pencairan.partials.grid', [
    'columns' => ['No', 'Nominal', 'Pot Pajak', 'Nominal Setelah Pajak', 'Nama Bank', 'Nama Rekening', 'No Rekening'],
    'pasteColumns' => 'Nominal, Pot Pajak, Nominal Setelah Pajak, Nama Bank, Nama Rekening, No Rekening',
])
