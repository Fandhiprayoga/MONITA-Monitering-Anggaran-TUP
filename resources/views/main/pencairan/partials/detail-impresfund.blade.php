@include('main.pencairan.partials.grid', [
    'columns' => ['Uraian', 'Volume', 'Tarif', 'Subtotal'],
    'pasteColumns' => 'Uraian, Volume, Tarif, Subtotal',
    'detailHelp' => 'Subtotal otomatis = Volume x Tarif (ditampilkan sebagai petunjuk di sel kosong).
        Isi atau paste Subtotal untuk mengganti manual; kosongkan untuk kembali otomatis.
        Mengubah Volume atau Tarif pada baris tersebut mengembalikan Subtotal ke hitungan otomatis.
        Total pengajuan dan copy ke Excel memakai Subtotal yang berlaku. Subtotal otomatis
        yang disalin lalu ditempel kembali menjadi nilai manual.
        Subtotal manual atau hasil paste yang berbeda dari Volume x Tarif ditandai kuning pada sel,
        dengan peringatan di atas tabel yang mencantumkan nomor baris dan nilai yang seharusnya,
        tanpa mengubah nilai isian atau menghalangi penyimpanan.',
])
