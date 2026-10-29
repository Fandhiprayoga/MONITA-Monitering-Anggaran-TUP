@include('main.pencairan.partials.grid', [
    'columns' => ['No', 'Uraian', 'Volume', 'Tarif', 'Subtotal'],
    'pasteColumns' => 'Uraian, Volume, Tarif, Subtotal',
    'detailHelp' => 'Subtotal otomatis = Volume x Tarif (ditampilkan sebagai petunjuk di sel kosong).
        Isi atau paste Subtotal untuk mengganti manual; kosongkan untuk kembali otomatis.
        Total pengajuan dan copy ke Excel memakai Subtotal yang berlaku. Subtotal otomatis
        yang disalin lalu ditempel kembali menjadi nilai manual.',
])
