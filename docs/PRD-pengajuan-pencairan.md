# PRD — Backend Pengajuan Pencairan MONITA

Versi: 1.5 — 31 Oktober 2026
Status: Siap menjadi acuan implementasi; asumsi pada bagian 14 perlu dikonfirmasi sebelum rilis produksi.

## 1. Tujuan

Mengaktifkan penyimpanan Form Impresfund, Beban, dan Honor dari draft sampai penyelesaian oleh admin. Pengguna dapat memantau status, memperbaiki pengajuan yang dikembalikan, dan melihat riwayat tindakan. Sistem harus mencegah nomor ganda, akses lintas PP, perubahan data setelah submit, dan hilangnya jejak revisi.

Kondisi awal: halaman form dan grid sudah tersedia, tetapi belum ada endpoint penyimpanan dan tombol simpan/submit masih dinonaktifkan. Data akun dan anggaran berasal dari Google Sheets; autentikasi memakai session spreadsheet, bukan otomatis akun Eloquent lokal.

## 2. Keputusan yang sudah disepakati

| Area | Keputusan |
|---|---|
| Penyimpanan | Database aplikasi; satu induk, rincian terpisah per jenis, riwayat dan snapshot revisi |
| Nomor resmi | Global untuk semua PP dan ketiga form; reset tiap tahun; diberikan saat submit pertama |
| Format | `000001/2026/3110/PP01`: urutan/tahun/tanggal-bulan/kode PP |
| Draft | Memiliki ID internal, belum memiliki nomor resmi |
| Akses pengguna | Satu akun per PP saat ini; pengguna dapat melihat dan mengedit pengajuan PP-nya dalam status yang diizinkan |
| Peran admin | Admin yang sama boleh melakukan verifikasi dan penyelesaian |
| Admin sebagai pengaju | Admin dengan PP dapat membuat, edit, dan submit pengajuan untuk PP sendiri; tidak boleh edit/submit atas nama PP lain |
| Tahapan | Tidak menggunakan status Sedang Diproses |
| Saldo | Google Sheets tetap sumber saldo; MONITA tidak memperbarui, mereservasi, atau mengurangi saldo |
| Sumber saldo gagal | Google Sheets adalah sumber kebenaran posisi anggaran terkini; jika gagal mendapatkan data, submit diblokir |
| Masa berlaku draft | Draft tidak boleh diajukan setelah melewati TW aktif asal draft, termasuk pergantian tahun; data lama tetap dapat dilihat/dibatalkan |
| Antrean lintas periode | Pengajuan yang sudah submitted/verified dapat diproses memakai DRK dan periode asal, tanpa diganti ke DRK periode baru |
| Pengembalian lintas periode | Jika returned setelah TW asal berakhir, admin verifikator membatalkan pengajuan lama; pengguna membuat pengajuan baru dengan DRK TW aktif dan nomor baru saat submit |
| Verifikasi saat sumber gagal | Persetujuan diblokir jika data Google Sheets gagal diperoleh; pengembalian dengan remark tetap boleh |
| Batas pengajuan | Total pengajuan tidak boleh melebihi saldo terkini TW DRK terpilih; submit diblokir dengan alasan |
| Nominal manual | Subtotal Impresfund dan neto Beban/Honor tetap boleh manual tanpa alasan tambahan |
| Pengembalian | Bisa dari verifikasi maupun antrean transaksi; remark admin wajib; pengguna memperbaiki dan submit ulang untuk verifikasi kembali |
| Pembatalan | Pengguna dapat membatalkan draft/returned; admin verifikator dapat membatalkan submitted/returned langsung tanpa edit pengguna, dengan remark wajib; verified harus dikembalikan dahulu |
| Penyelesaian | Tanggal proses wajib untuk semua jenis; tidak ada nomor bukti pencairan maupun referensi TUB; lampiran opsional |
| Tanggal penyelesaian | Default hari ini; boleh backdate, tidak sebelum tanggal verifikasi aktif dan tidak melebihi hari ini; waktu tindakan server tetap otomatis |
| Label akhir Impresfund | Tetap Dicairkan; tidak menambah tahap Sedang Dicairkan |
| Referensi TUB | Tidak dicatat dalam MONITA; proses dan dokumen TUB dikerjakan di luar sistem |
| Penolakan final | Tidak termasuk versi pertama |

## 3. Ruang lingkup

### Termasuk
- Simpan dan buka kembali draft untuk tiga jenis form.
- Submit pertama, penomoran resmi, dan submit ulang setelah revisi.
- Daftar/detail pengajuan berdasarkan PP, filter jenis/status/periode, pencarian nomor.
- Verifikasi admin, pengembalian dengan remark, dan antrean transaksi per jenis.
- Penyelesaian transaksi dengan tanggal proses dan flag sesuai jenis, tanpa pencatatan nomor bukti eksternal.
- Pembatalan, riwayat status, dan snapshot setiap versi yang disubmit.
- Validasi backend, kontrol akses, penanganan konflik, dan pengujian otomatis.

### Tidak termasuk
- Integrasi otomatis ke TUB/SIMKUG/bank dan pencairan dana secara langsung.
- Pencatatan nomor referensi atau pengelolaan dokumen TUB; proses tersebut berada di luar MONITA.
- Pencatatan nomor bukti pencairan Impresfund.
- Pengelolaan saldo, reservasi anggaran, atau pencatatan realisasi ke Google Sheets.
- Approval bertingkat, pemisahan wajib verifikator/pelaksana, dan penolakan final.
- Email/WhatsApp notifikasi, transaksi parsial, pembukaan kembali pengajuan selesai.
- Migrasi seluruh autentikasi spreadsheet ke autentikasi lokal.

## 4. Aktor dan hak akses

| Tindakan | Pengguna PP | Admin |
|---|---|---|
| Lihat daftar/detail | Hanya PP sendiri | Semua PP |
| Buat draft | PP sendiri | PP sendiri, wajib memiliki kode PP |
| Edit isi | PP sendiri, `draft`/`returned` | Hanya PP sendiri, `draft`/`returned`; tidak mengedit PP lain |
| Submit/submit ulang | PP sendiri, `draft`/`returned` | Hanya PP sendiri, `draft`/`returned` |
| Batalkan | PP sendiri, `draft`/`returned` | Verifikator, `submitted`/`returned`, serta `draft` PP sendiri; remark wajib |
| Verifikasi | Tidak | Hanya `submitted` |
| Kembalikan | Tidak | `submitted` atau `verified` |
| Tandai selesai | Tidak | Hanya `verified` |
| Lihat riwayat | PP sendiri | Semua PP |

- Otorisasi diterapkan di server pada setiap endpoint, termasuk lampiran dan snapshot, bukan hanya menyembunyikan tombol.
- `kode_pp`, role, dan identitas aktor harus berasal dari session yang tervalidasi, bukan payload browser.
- Tombol Tambah Pengajuan tersedia bagi user maupun admin yang memiliki PP. Admin tetap melihat seluruh PP untuk pengawasan; hak edit/submit tidak mengikuti hak lihat global. Admin tanpa PP tetap dapat memverifikasi/transaksi tetapi tidak membuat pengajuan.
- Simpan identitas pembuat dan setiap aktor meskipun akses didasarkan pada PP. Perubahan kepemilikan akun tidak boleh mengubah atribusi riwayat lama.
- Jika aturan satu akun per PP diterapkan sistem, penambahan/perubahan akun harus menolak duplikasi PP yang tidak diizinkan. Jangan mengubah validasi akun admin tanpa meninjau kebutuhan mereka.

## 5. Status dan aturan transisi

| Kode | Label | Makna |
|---|---|---|
| `draft` | Draft | Tersimpan, belum dikirim ke admin |
| `submitted` | Menunggu Verifikasi | Isi terkunci, masuk antrean verifikasi |
| `returned` | Dikembalikan untuk Perbaikan | Isi dapat diperbaiki; tampilkan remark terakhir dan riwayat |
| `verified` | Terverifikasi / Menunggu Transaksi | Lolos verifikasi; isi terkunci |
| `completed` | Berdasarkan jenis | Proses MONITA selesai |
| `cancelled` | Dibatalkan | Terminal; isi tidak dapat diedit atau disubmit |

Label `completed`: Impresfund = **Dicairkan**; Beban dan Honor = **Diajukan ke TUB**. Selesai Beban/Honor tidak menyatakan dana sudah cair di TUB.

Transisi yang diperbolehkan:
1. `draft → submitted`: pengguna, validasi lengkap dan cek saldo; buat nomor resmi.
2. `returned → submitted`: pengguna, hanya jika periode asal masih aktif, validasi lengkap dan cek saldo; nomor tetap, buat revisi baru. Returned lintas periode harus dibatalkan dan dibuat pengajuan baru.
3. `submitted → returned`: admin, remark wajib.
4. `submitted → verified`: admin, validasi dan pemeriksaan saldo ulang.
5. `verified → completed`: admin, tanggal proses wajib; tidak memerlukan nomor bukti/referensi eksternal pada semua jenis.
6. `draft/returned → cancelled`: pengguna, konfirmasi eksplisit.
7. `verified → returned`: admin dari antrean transaksi, remark wajib; keluarkan dari antrean transaksi dan wajib verifikasi ulang setelah pengguna submit revisi.
8. `submitted/returned → cancelled`: admin verifikator, remark wajib dan konfirmasi; termasuk pengembalian yang sudah melewati periode asal. Pengguna tidak perlu mengedit atau submit ulang untuk pembatalan ini. Pengajuan verified harus dikembalikan dahulu sebelum dibatalkan; completed tidak dapat dibatalkan.
9. `draft → cancelled`: admin sebagai pengaju, hanya draft PP sendiri, remark wajib. Transisi create/edit/submit pengguna juga berlaku untuk admin PP sendiri dengan seluruh validasi periode, saldo, dan konflik tetap sama.

Transisi lain ditolak server. `completed` dan `cancelled` terminal pada MVP. Pengembalian dari antrean transaksi membatalkan kelayakan verifikasi versi sebelumnya, tetapi tidak menghapus audit atau snapshot. Metadata verifikasi aktif direset; riwayat verifikasi sebelumnya tetap tersimpan. Pengajuan selesai belum dapat dibuka kembali.

## 6. Nomor pengajuan

- Pisahkan primary key internal dengan nomor resmi yang dibaca pengguna.
- Format: `{urutan minimum 6 digit}/{YYYY}/{DDMM}/{kode_pp}`. Contoh `000001/2026/3110/PP01`.
- Tahun dan DDMM berasal dari waktu server saat submit pertama dalam zona waktu aplikasi yang disepakati.
- Urutan global lintas jenis/PP, dimulai dari 1 setiap tahun; jika melebihi 999999, digit diperpanjang tanpa memotong angka.
- Nomor tidak berubah ketika dikembalikan, diedit, disubmit ulang, atau dibatalkan.
- Pengajuan draft menampilkan identitas seperti “Draft #123”, bukan nomor resmi.
- Nomor tidak digunakan ulang dan tidak diwajibkan tanpa celah. Nomor adalah identitas dokumen, bukan ukuran jumlah pengajuan aktif.
- Gunakan counter per tahun dengan unique key tahun dan update atomik/locking sesuai mesin database. Tangani balapan inisialisasi counter awal tahun.
- Alokasi nomor, perubahan status, snapshot, dan riwayat dilakukan dalam satu transaksi database. Terapkan unique constraint `nomor_pengajuan` dan pasangan `(tahun_nomor, nomor_urut)`.
- Jangan menggunakan `COUNT(*) + 1` atau `MAX(...) + 1` tanpa kontrol konkurensi.
- Jangan menaruh nomor dengan karakter `/` sebagai ID path; endpoint menggunakan ID internal atau public ID yang aman untuk URL.

## 7. Persyaratan fungsional

### FR-01 — Draft
- Simpan header dan semua baris berisi data, termasuk baris belum lengkap.
- Field belum lengkap dapat null; batas jumlah baris, ukuran teks, tipe nilai yang tersedia, dan akses PP tetap divalidasi.
- Baris kosong/default pajak nol saja tidak dianggap rincian aktif.
- Nilai numerik yang diisi tetapi formatnya salah ditolak dengan pesan spesifik; kelengkapan baru wajib pada submit.
- Simpan draft yang diedit tidak mengalokasikan nomor resmi baru atau membuat submit snapshot.
- Jenis form tidak dapat diganti setelah draft dibuat.
- Buka kembali draft mempertahankan urutan baris, nilai manual, identitas teks, dan pilihan DRK.
- Tampilkan peringatan perubahan belum tersimpan; autosave tidak termasuk MVP.
- Periode draft ditentukan server berdasarkan tahun/TW aktif. Setelah periode tersebut berlalu, draft tidak dapat disubmit; tampilkan “Draft sudah melewati triwulan aktif. Silakan buat pengajuan baru pada triwulan berjalan.” Draft lama tetap dapat dilihat/dibatalkan, tidak otomatis berganti periode atau dihapus. Validasi periode berlaku di backend, termasuk bila halaman dibuka sebelum pergantian TW lalu disubmit sesudahnya.

### FR-02 — Header dan DRK
- Header mencakup PP, periode, referensi DRK, akun, uraian pengajuan, dan total nominal.
- Server mengambil/memvalidasi DRK berdasarkan PP dan periode; tidak mempercayai field readonly dari browser.
- Simpan snapshot kode/nama PP, kode/nama akun, kode/uraian DRK, dan informasi anggaran saat submit.
- Identitas DRK harus stabil, bukan nomor baris spreadsheet yang berubah setelah sort/insert. Jika sumber belum memiliki ID stabil, tentukan kunci gabungan yang unik atau tambahkan ID sumber sebelum produksi.
- Pilihan DRK tidak aktif/hilang saat submit menyebabkan penolakan dengan alasan, draft tidak dihapus.
- Pengajuan resmi yang sudah submitted/verified sebelum pergantian TW/tahun tetap dapat diverifikasi/diselesaikan berdasarkan tahun, TW, dan DRK asal. Lookup saldo harus menunjuk periode asal, bukan otomatis periode aktif. Ketersediaan data tahun/TW historis wajib dipastikan pada sumber Google Sheets.
- Jika pengajuan dikembalikan sesudah periode asal berakhir, tolak submit ulang dan tampilkan instruksi menunggu pembatalan admin lalu membuat pengajuan baru pada TW aktif. Jangan otomatis mengganti DRK, periode, atau nomor pengajuan lama.

### FR-03 — Submit dan kontrol saldo
- Minimal satu rincian aktif lengkap, DRK valid, dan total bruto lebih dari nol.
- Basis pembanding saldo adalah **total bruto/nominal diajukan**, sesuai total yang sekarang ditampilkan form.
- Total bruto > saldo terkini TW DRK terpilih: tolak submit tanpa perubahan status/nomor, tampilkan nominal dan saldo serta alasan.
- Total bruto = saldo diperbolehkan.
- Cek data saldo terbaru dari sumber pada submit, bukan snapshot browser atau cache lama. Simpan waktu pengambilan saldo dan nilai yang dipakai.
- Fail-closed: gagal memperoleh data anggaran terkini dari Google Sheets → blokir submit; draft tetap tersimpan dan pengguna dapat mencoba lagi. Saldo tidak ada/tidak valid tidak boleh dianggap nol atau diganti snapshot/cache lama untuk meloloskan submit.
- Cek saldo ulang pada verifikasi. Jika kurang, admin tidak dapat menyetujui dan dapat mengembalikan dengan remark.
- Jika gagal memperoleh data sumber saat verifikasi, blokir persetujuan tanpa mengubah status. Pengembalian dan pembatalan yang diizinkan tidak memerlukan cek saldo sumber.
- Tanpa reservasi, beberapa pengajuan bisa masing-masing lolos terhadap saldo yang sama. MVP tidak menjamin total antrean <= saldo; tampilkan total antrean aktif DRK sebagai informasi admin, bukan saldo pengganti.
- Tidak ada update saldo otomatis ketika submit, verifikasi, atau selesai.

### FR-04 — Rincian dan perhitungan
- Impresfund: uraian, volume, tarif, subtotal. Volume > 0; subtotal otomatis = volume × tarif, dibulatkan ke 2 desimal; subtotal manual diperbolehkan.
- Simpan `subtotal_manual` nullable (null = otomatis) dan `subtotal_efektif`. Nilai manual nol berbeda dari kosong.
- Beban: nominal bruto, potongan pajak, neto manual, bank, nama rekening, nomor rekening.
- Honor: WNI/WNA, nominal honor, pajak, neto manual, nama penerima, nama rekening, bank, nomor rekening, KTP/paspor, NPWP opsional.
- Pajak tidak negatif dan tidak melebihi bruto; volume/tarif/nominal/neto tidak negatif. WNI/WNA dinormalisasi ke huruf besar.
- Neto Beban/Honor tidak dipaksa sama dengan bruto dikurangi pajak; tidak wajib alasan tambahan. Admin tetap dapat melihat bruto, pajak, dan neto untuk pemeriksaan.
- Total dihitung ulang dari rincian di server menggunakan decimal/fixed-point, bukan floating-point atau total payload.
- Maksimal 500 baris sesuai grid saat ini; batas per nilai dan batas total didokumentasikan serta diselaraskan frontend/backend sebelum implementasi.
- Rekening, identitas, NPWP, dan kode disimpan sebagai teks; nol di depan tidak hilang.

### FR-05 — Verifikasi
- Daftar hanya antrean `submitted`, mendukung filter PP/jenis/periode dan urutan waktu submit.
- Admin melihat rincian, total, snapshot anggaran, dan seluruh remark/revisi.
- Tombol verifikasi dan kembalikan meminta konfirmasi.
- Pengembalian wajib remark bermakna (bukan whitespace), ditampilkan ke pengguna.
- Admin sebagai verifikator tidak mengubah rincian PP lain; koreksi melalui pengembalian dan submit ulang. Sebagai pengaju PP sendiri, admin boleh edit draft/returned lalu submit, tetapi isi tetap terkunci pada submitted/verified/completed/cancelled.
- Verifikator memiliki aksi “Batalkan Pengajuan” untuk submitted/returned dengan konfirmasi dan remark wajib. Tersedia di antrean menunggu verifikasi dan daftar/detail returned, termasuk lintas periode. Pembatalan langsung tidak mensyaratkan pengguna mengedit form; nomor, snapshot, dan riwayat dipertahankan. Pengajuan keluar dari antrean aktif dan alasan pembatalan ditampilkan kepada PP.

### FR-06 — Transaksi dan penyelesaian
- Antrean per jenis hanya memuat `verified`; tab riwayat dapat memuat `completed`.
- Admin dapat mengembalikan pengajuan `verified` kepada PP dari antrean transaksi dengan remark wajib dan konfirmasi. Setelah diperbaiki, pengajuan kembali melalui `submitted → verified`, tidak langsung kembali ke antrean transaksi. Nomor resmi tetap sama dan snapshot revisi sebelumnya tidak berubah.
- Form selesai mewajibkan tanggal proses untuk semua jenis. Tidak ada field/validasi nomor bukti pencairan Impresfund maupun referensi TUB; pengguna cukup melihat status penyelesaian.
- Beban/Honor hanya mencatat tanggal diajukan ke TUB, admin pelaksana, dan waktu tindakan sistem; catatan serta lampiran opsional. Tidak ada field, validasi wajib, atau nomor otomatis referensi TUB.
- Status “Diajukan ke TUB” merupakan konfirmasi tindakan admin di MONITA, bukan hasil integrasi atau verifikasi dokumen dari TUB. Pengelolaan dokumen/referensi TUB sepenuhnya di luar sistem.
- Pisahkan tanggal bisnis (`completion_date`) dari waktu tindakan sistem (`completed_at`).
- Tanggal penyelesaian default hari ini dalam zona waktu aplikasi dan boleh backdate. Validasi server: tanggal verifikasi aktif <= completion_date <= hari ini; bandingkan tanggal kalender, bukan jam timestamp. Setelah pengembalian dan verifikasi ulang, batas bawah mengikuti verifikasi revisi terbaru. Backdate tidak mengubah completed_at atau waktu riwayat yang selalu berasal dari server.
- Tidak mendukung pembayaran/pengajuan ke TUB parsial pada MVP.
- Lampiran opsional disimpan private. Default usulan: PDF/JPEG/PNG, maksimal 10 MB per file, maksimal 5 file; perlu persetujuan.
- Validasi konten/MIME, nama penyimpanan acak, dan otorisasi download; tidak menyimpan dokumen sensitif pada folder publik.
- Koreksi pengajuan selesai tidak dilakukan dengan menghapus jejak atau mengganti nomor.

### FR-07 — Pemantauan dan riwayat
- Daftar pengguna mencakup draft, menunggu verifikasi, dikembalikan, menunggu transaksi, selesai, dibatalkan.
- Tampilkan nomor/draft ID, jenis, tanggal, uraian ringkas, total, status, dan waktu perubahan terakhir.
- Detail menampilkan timeline aktor/waktu/status/remark serta versi yang disubmit.
- Setelah dibatalkan, data dan riwayat tetap tersedia; tidak hard delete pengajuan dari UI.
- Paginasi server dan filter tidak boleh membuka akses lintas PP.

## 8. Model data usulan

Nama tabel dapat disesuaikan konvensi Laravel, tetapi relasi dan integritas berikut wajib dipertahankan.

### `pengajuans`
- `id`, opsional `public_id` UUID/ULID untuk URL.
- `jenis`, `status`, `kode_pp`, `nama_pp`, `created_by_actor_id`.
- `nomor_pengajuan` nullable unique, `nomor_urut` nullable, `tahun_nomor` nullable.
- `tahun_anggaran`, `triwulan`, `drk_source_id`, snapshot akun/DRK.
- `uraian_pengajuan`, `total_bruto`, `total_pajak`, `total_neto`.
- `saldo_awal_snapshot`, `anggaran_terpakai_snapshot`, `saldo_terkini_snapshot`, `saldo_checked_at`.
- `first_submitted_at`, `last_submitted_at`, `verified_at/by`, `completed_at/by`, `cancelled_at/by`.
- `revision_no`, `lock_version`, timestamps.
- Indeks untuk PP/status/periode, status/jenis, DRK/periode; foreign key aktor jika memakai tabel lokal.

### Rincian
- `pengajuan_impresfund_details`: `id`, `pengajuan_id`, `urutan`, `uraian`, `volume`, `tarif`, `subtotal_manual`, `subtotal_efektif`.
- `pengajuan_beban_details`: `id`, `pengajuan_id`, `urutan`, `nominal`, `pajak`, `neto`, `bank`, `nama_rekening`, `nomor_rekening`.
- `pengajuan_honor_details`: seperti Beban ditambah `kewarganegaraan`, `nama_penerima`, `nomor_identitas`, `npwp`.
- Unique `(pengajuan_id, urutan)` per tabel; foreign key ke induk. Jenis induk harus konsisten dengan tabel rincian melalui service dan pengujian.
- Nominal `DECIMAL(18,2)` sebagai usulan kapasitas penyimpanan, bukan izin menerima semua nilai sampai kapasitas DB; volume dapat `DECIMAL(18,2)` mengikuti UI sekarang.

### `pengajuan_revisions`
- `id`, `pengajuan_id`, `revision_no`, `submitted_by`, `submitted_at`, `snapshot` JSON, `schema_version`.
- Snapshot immutable berisi header, rincian, total, dan anggaran yang dipakai pada submit; unique `(pengajuan_id, revision_no)`.
- Tidak menyalin password atau seluruh isi session spreadsheet ke snapshot.

### `pengajuan_histories`
- `id`, `pengajuan_id`, `revision_no` nullable, `action`, `from_status`, `to_status`, `actor_id`, snapshot nama/role aktor, `remark`, `created_at`.
- Catat create/save draft, submit, return, verify, complete, cancel. Detail isi versi resmi ada pada revisions; audit draft dapat menyimpan metadata tanpa menyalin PII penuh ke log.
- Append-only melalui aplikasi; tidak ada endpoint edit/hapus riwayat.

### `pengajuan_completions` dan `pengajuan_attachments`
- Completion: `pengajuan_id` unique, `completion_date`, `notes` opsional, `actor_id`, timestamps. Tidak ada kolom nomor bukti pencairan atau referensi TUB pada semua jenis; payload referensi eksternal ditolak.
- Attachment: relasi completion, private disk/path, original filename, MIME, ukuran, uploaded_by, timestamps.
- Tidak diperlukan aturan keunikan/duplikasi nomor bukti eksternal karena tidak dicatat. Nomor resmi pengajuan MONITA tetap unik.

### `pengajuan_number_sequences`
- `year` unique, `last_number`; kenaikan atomik dalam transaksi submit.

### Identitas aktor lokal
- Akun spreadsheet dipetakan ke ID aktor lokal yang stabil, menggunakan ID sumber jika tersedia. Jangan FK ke `users.id` sebelum mapping autentikasi dibuat.
- Username sebagai kunci sementara hanya boleh jika dijamin unik/tidak berubah; jika tidak, tambahkan ID tetap pada sumber akun.
- Akses pengajuan tetap berdasarkan PP session tervalidasi, sedangkan audit berdasarkan identitas aktor.

## 9. Arsitektur dan kontrak operasi

- Controller menangani request/response; Form Request untuk validasi; Policy/gate atau middleware terintegrasi auth spreadsheet untuk akses; service domain untuk alur, saldo, nomor, perhitungan, dan snapshot.
- Gunakan enum/status terpusat dan mapping label selesai, bukan string bebas dari frontend.
- Setiap penyimpanan induk+rincian+audit dan setiap transisi dijalankan atomik.
- Ambil saldo eksternal sebelum transaksi database panjang, lalu validasi ulang status/versi di dalam transaksi; tidak menahan lock sambil menunggu jaringan. Karena sumber eksternal tidak ter-lock bersama DB, simpan timestamp dan jelaskan keterbatasannya.
- Gunakan `lock_version`/optimistic locking untuk edit dan transisi; respons konflik meminta muat ulang, bukan menimpa data baru.
- Submit/complete memakai idempotency key atau mekanisme setara. Retry identik mengembalikan hasil sebelumnya tanpa nomor/history ganda; payload berbeda dengan key sama ditolak.
- Double-click dari UI juga dicegah selama request berjalan, tetapi bukan pengganti kontrol backend.
- DB produksi harus mendukung transaksi dan unique constraints; strategi locking disesuaikan driver aktual, termasuk bila memakai SQLite.

Operasi endpoint usulan (path menggunakan ID internal/public ID):
- GET daftar/detail/form/edit pengajuan.
- POST buat draft; PATCH perbarui draft/dikembalikan.
- POST submit; POST cancel.
- GET antrean verifikasi; POST return; POST verify.
- GET antrean transaksi per jenis; POST complete.
- GET revisions/history dan download attachment terotorisasi.

Kode respons: 422 untuk input/saldo, 403 atau 404 konsisten untuk akses terlarang, 409 untuk konflik versi/transisi, 503 untuk ketidaktersediaan sumber saldo. Pesan error bahasa Indonesia dan tidak membocorkan stack trace/credentials.

## 10. Persyaratan nonfungsional

- CSRF untuk operasi session, validasi jenis form, batas payload, rate limit operasi tulis/upload yang wajar.
- Rekening, KTP/paspor, dan NPWP adalah data sensitif: akses hanya pihak berwenang, masking pada daftar bila ditampilkan, jangan log payload lengkap.
- Backup DB dan private storage, uji restore; jadwal retensi dan enkripsi ditetapkan sebelum produksi.
- Riwayat tidak boleh diubah pengguna/admin melalui UI; akses DB operasional dibatasi.
- Waktu server konsisten dan disimpan dengan konvensi yang jelas; tanggal bisnis ditampilkan dalam zona waktu aplikasi.
- Timeout panggilan Google Sheets dan pesan retry yang aman; kegagalan sumber tidak menghapus draft atau mengubah status.
- Daftar dipaginasi; hindari N+1. Target awal p95 operasi DB saja <= 2 detik pada beban uji yang disepakati; ukur dependensi Google Sheets terpisah.
- UI mempertahankan grid/paste Excel, menampilkan error per field/baris, dan label aksi/status yang konsisten.

## 11. Kriteria penerimaan dan pengujian

1. Ketiga form dapat menyimpan draft tidak lengkap dan dibuka kembali tanpa kehilangan nilai/urutan/nol awal.
2. Submit valid menghasilkan satu nomor resmi, satu revisi, dan status `submitted`.
3. Dua submit bersamaan tidak menghasilkan nomor ganda; submit ulang tidak mengubah nomor.
4. Urutan reset tahun baru; tanggal nomor mengikuti submit pertama, bukan tanggal draft.
5. PP A tidak dapat membaca/mengedit/download milik PP B lewat URL atau manipulasi payload.
6. User tidak dapat mengedit ketika submitted/verified/completed/cancelled.
7. Return tanpa remark ditolak; return valid membuka revisi dan mempertahankan snapshot sebelumnya.
8. Saldo kurang menolak submit, menampilkan nominal/saldo/alasan, tanpa nomor/status/history submit baru.
9. Saldo sama dengan total lolos; gagal memperoleh saldo terkini dari Google Sheets menolak submit tanpa menggunakan cache/snapshot lama sebagai pengganti.
10. DRK lintas PP, periode tidak valid, dan field readonly palsu ditolak/diabaikan server.
11. Subtotal manual/otomatis dan neto manual dipertahankan; total server tidak bergantung pada total browser.
12. Verifikasi gagal saat saldo tidak cukup; verifikasi valid menghasilkan antrean transaksi sesuai jenis.
13. Selesai tanpa tanggal ditolak untuk semua jenis; semua jenis dapat selesai tanpa nomor bukti/referensi eksternal dan tidak menampilkan/menerima field tersebut. Label Impresfund tetap Dicairkan, Beban/Honor Diajukan ke TUB.
14. Retry submit/complete tidak menggandakan nomor, completion, snapshot, atau riwayat.
15. Konflik edit/admin ditolak dengan 409; tidak terjadi lost update.
16. Pengguna hanya dapat membatalkan draft/returned; admin dapat membatalkan submitted/returned dengan remark tanpa edit pengguna. Admin tidak dapat membatalkan langsung verified/completed; nomor yang sudah ada tetap tercatat dan tidak digunakan ulang; status terminal tidak dapat dibatalkan lagi.
17. Lampiran invalid/terlalu besar ditolak; dokumen valid hanya dapat diakses pihak berwenang.
18. Kegagalan DB di tengah penyimpanan menyebabkan rollback induk/rincian/nomor/revisi/history secara konsisten.
19. Tidak ada perubahan saldo/realisasi Google Sheets dari seluruh alur MONITA.
20. Pengujian Laravel menggunakan mock Google Sheets dan database testing terpisah; uji konkurensi/integrasi juga dilakukan pada driver DB produksi. Uji JavaScript mencakup serialisasi, load draft, perhitungan, dan error submit.
21. Draft dari TW/tahun yang sudah lewat ditolak saat submit dengan alert alasan; riwayat/data draft tetap tersedia dan pembatalan tetap dapat dilakukan.
22. Return dari antrean transaksi tanpa remark ditolak; return valid mengeluarkan pengajuan dari antrean, mempertahankan nomor/audit, dan mengharuskan verifikasi ulang sebelum selesai.
23. Pengajuan submitted/verified lintas TW/tahun tetap memuat DRK/periode asal; cek saldo verifikasi menggunakan periode asal, tidak saldo periode baru.
24. Returned yang sudah melewati TW asal tidak dapat disubmit ulang; verifikator membatalkan dengan remark. Pengajuan baru pengguna memakai TW aktif dan mendapat nomor baru saat submit.
25. Kegagalan Google Sheets memblokir persetujuan verifikasi tetapi tidak memblokir return/cancel yang diizinkan.
26. Tanggal penyelesaian default hari ini; backdate sejak tanggal verifikasi aktif diperbolehkan, tanggal sebelum verifikasi atau setelah hari ini ditolak. completed_at dan waktu audit tetap merekam waktu tindakan sebenarnya.
27. Cancel admin tanpa remark/konfirmasi ditolak; cancel submitted valid mengeluarkan pengajuan dari antrean tanpa memerlukan tindakan edit pengguna dan menampilkan alasan kepada PP.
28. Tombol tambah muncul bagi admin yang memiliki PP; ketiga jenis dapat dibuat/edit/submit untuk PP sendiri. Admin tidak dapat edit/submit draft atau returned PP lain meskipun dapat melihat detailnya.
29. Admin dapat membatalkan draft PP sendiri dengan remark, tidak draft PP lain. Admin tanpa PP tidak dapat membuat pengajuan; verifikasi/transaksi tetap tersedia.

## 12. Tahapan implementasi

1. Fondasi: konfirmasi bagian 14, mapping aktor/DRK stabil, migrations, models, enum/status, policies, service perhitungan dan nomor.
2. Draft dan submit: endpoint, validasi, saldo fresh, idempotency, snapshot, aktivasi tombol/grid serialization dan load draft.
3. Pemantauan: daftar PP, detail, timeline/revisi, edit, cancel, filter/paginasi.
4. Verifikasi: antrean admin, return/verify, pengecekan saldo ulang dan konflik versi.
5. Transaksi: antrean per jenis, complete, tanggal proses, private attachment opsional; tanpa nomor bukti eksternal pada semua jenis.
6. Hardening: pengujian otomatis dan konkurensi, review keamanan/PII, backup/restore, UAT ketiga jenis, dokumentasi operasional.

Definition of done: seluruh kriteria penerimaan lulus, tidak ada regresi pengujian lama, migrasi teruji, UAT pengguna/admin selesai, serta aturan/asumsi produksi sudah disetujui. PRD ini tidak mengaktifkan backend atau menjalankan migrasi.

## 13. Risiko dan rekomendasi peningkatan

| Risiko | Mitigasi MVP / peningkatan |
|---|---|
| Banyak pengajuan memakai saldo sama | Tampilkan total antrean aktif per DRK dan cek ulang saat verifikasi; reservasi anggaran menjadi fase terpisah |
| Google Sheets stale/tidak tersedia | Fresh lookup pada submit/verifikasi, simpan timestamp; submit diblokir bila data sumber gagal diperoleh |
| Aktor/DRK berubah karena posisi baris | Gunakan ID sumber stabil, bukan nomor baris |
| Admin yang sama melakukan dua tahapan | Audit wajib; separation of duties opsional pada versi berikutnya |
| Neto manual tidak cocok dengan bruto-pajak | Tampilkan ketiga nilai secara jelas untuk review; tidak memaksa alasan sesuai keputusan pengguna |
| Salah setelah verifikasi/selesai | Dari antrean transaksi dapat dikembalikan dengan remark; koreksi setelah selesai masih memerlukan alur resmi, bukan edit DB |
| Lampiran dan identitas bocor | Private storage, policy download, log minimal, backup/enkripsi dan retensi |
| Tidak ada transaksi parsial | Admin hanya menandai selesai bila seluruh pengajuan telah diproses sesuai jenis |

## 14. Hal yang belum disepakati / default usulan

Ini bukan keputusan yang sudah dikonfirmasi pengguna. Default digunakan untuk rancangan, tetapi harus dipastikan sebelum produksi:

1. **Cakupan cancel admin — selesai diklarifikasi:** submitted/returned boleh dibatalkan verifikator dengan remark; verified harus dikembalikan dahulu, completed tetap terminal. Aturan ada pada bagian 4–5 dan kriteria penerimaan.
2. **Ketersediaan periode historis:** aturan antrean lintas periode sudah disepakati memakai DRK asal. Periksa bahwa Google Sheets menyediakan data saldo berdasarkan tahun dan TW asal, terutama saat berganti tahun; jika data gagal/tidak tersedia, persetujuan tetap diblokir, tanpa fallback saldo periode baru.
3. **Tanggal proses — selesai diklarifikasi:** default hari ini, backdate diperbolehkan dalam rentang tanggal verifikasi aktif sampai hari ini. Waktu tindakan server tetap tercatat terpisah.
4. **Zona waktu:** usulan Asia/Jakarta (WIB), tetapi perlu diselaraskan dengan konfigurasi aplikasi dan lokasi operasional.
5. **Bukti selesai — selesai diklarifikasi:** seluruh nomor bukti pencairan/referensi TUB tidak dicatat; batas tanggal proses sudah ditetapkan pada poin 3. Tidak ada klarifikasi nomor bukti tambahan.
6. **Lampiran:** usulan PDF/JPEG/PNG, 10 MB/file, 5 file; masa retensi dan kebutuhan pemindaian malware perlu ditentukan.
7. **Koreksi setelah selesai:** pengembalian dari antrean transaksi sudah disepakati masuk MVP. Pembukaan kembali/pembatalan transaksi completed belum disepakati dan tetap di luar MVP.
8. **Batas numerik:** grid saat ini maksimal 10 miliar per input dan 2 desimal; tentukan batas total bisnis, pembulatan, serta apakah baris nominal nol diperbolehkan selama total > 0 (usulan ya).
9. **Identitas akun/DRK:** periksa ketersediaan ID tetap pada spreadsheet dan aturan perubahan akun/PP sebelum menentukan schema actor mapping.
10. **Operasional produksi:** pilih driver DB, jadwal backup/retensi, penanggung jawab restore, ukuran beban uji, dan SOP koreksi dokumen selesai.

Persetujuan atas PRD tidak dianggap otomatis menyetujui seluruh default di bagian ini. Keputusan tambahan dicatat sebagai revisi PRD agar implementasi dan UAT memiliki sumber aturan yang sama.