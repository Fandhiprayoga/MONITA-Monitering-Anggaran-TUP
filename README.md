# MONITA - Monitoring Anggaran Telkom University Purwokerto

MONITA adalah aplikasi web yang dirancang untuk menggantikan proses manual pengelolaan dan monitoring anggaran menggunakan spreadsheet di lingkungan Telkom University Purwokerto. Sistem ini bertujuan menyediakan dashboard terpusat, akses multi-user berbasis peran, dan laporan real-time untuk 33 unit kerja di kampus.

## 📋 Daftar Isi

- [Tujuan Proyek](#tujuan-proyek)
- [Fitur Utama](#fitur-utama)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi dan Setup Lokal](#instalasi-dan-setup-lokal)
- [Konfigurasi Google Sheets API](#konfigurasi-google-sheets-api)
- [Keamanan dan Autentikasi](#keamanan-dan-autentikasi)
- [Fitur Khusus](#fitur-khusus)

## 🎯 Tujuan Proyek

Tujuan utama sistem MONITA adalah mencapai efisiensi, akurasi, dan transparansi dalam pengelolaan anggaran:

- **Mengatasi Inkonsistensi Data**: Mengeliminasi risiko duplikasi dan bentrok data yang terjadi saat menggunakan file Excel/Spreadsheet
- **Monitoring Terpusat**: Menyediakan dashboard interaktif untuk analisis keuangan real-time bagi pimpinan dan staf keuangan
- **Akses Terkendali**: Menerapkan sistem akses multi-user dengan tingkatan hak akses yang berbeda (Admin Keuangan vs. User Unit)

## ✨ Fitur Utama

- **Dashboard Interaktif**: Menampilkan ringkasan saldo, perbandingan anggaran vs. realisasi, dan status serapan per unit dalam bentuk grafik
- **Manajemen Anggaran (CRUD)**: Kemampuan untuk mengimpor data dari file Excel/CSV/Google Sheets serta mencatat transaksi keuangan
- **Manajemen Akun**: Admin memiliki akses penuh untuk menambah, mengubah, dan menghapus akun unit, serta mengelola peran pengguna
- **Pelaporan Otomatis**: Menghasilkan laporan keuangan terperinci dan ringkasan (Summary RKA, RKM, Pengembangan) dalam format PDF
- **Preview Laporan PDF**: Fitur review laporan sebelum dicetak untuk memastikan akurasi data
- **Keamanan Login**: Implementasi CAPTCHA untuk mencegah akses tidak sah
- **Pengaturan Tahun Anggaran**: Mengelola koneksi ke Google Sheets ID untuk tahun anggaran yang berbeda-beda

### Pratinjau form pencairan

Form Impresfund, Beban, dan Honor menggunakan header bersama dan rincian sesuai jenis.
Halaman ini belum menyimpan data ke backend; perubahan hilang saat halaman dimuat
ulang. Pilih DRK menampilkan hanya data unit (Kode PP) akun yang login untuk
triwulan kalender berjalan, dari `RAW Data TW I/II/III/IV!B2:N3000`.
DRK TUP yang sama dengan akun berbeda tetap menjadi opsi terpisah.
Kolom Cari DRK memfilter pilihan berdasarkan kode DRK, kode/nama akun, uraian,
atau tipe anggaran tanpa membedakan huruf besar/kecil. Pilihan aktif tetap
ditampilkan dan header tidak berubah saat pencarian; kosongkan pencarian untuk
menampilkan semua opsi kembali.
Kode Akun (di samping Nama Akun), Nama Akun, Kode PP, Nama PP, kode/uraian DRK
dan saldo terisi otomatis dan read-only. Kode Akun diambil dari kolom E, Nama Akun
dari F, Kode PP dari K, Nama PP dari L, dan Kode DRK SIMKUG dari N pada baris terpilih.
Filter akses unit tetap menggunakan kolom B sesuai unit akun login, bukan kolom K.
Nama PP tidak ditimpa dari session login. Dropdown memuat DRK TUP (D) dan SIMKUG (N)
agar keduanya bisa dicari; jika K/L/N kosong, field terkait tetap kosong tanpa fallback.
Saldo Awal memakai Anggaran, Anggaran Terpakai memakai Realisasi, dan Saldo Terkini
memakai Saldo dari sheet. Uraian DRK tetap memakai kolom G.
Kegagalan memuat data ditampilkan sebagai
pesan error; unit tanpa DRK mendapatkan pemberitahuan, bukan saldo nol buatan.
Tombol Simpan sebagai Draft dan Submit Pengajuan ditampilkan pada semua form,
tetapi masih dinonaktifkan sampai penyimpanan backend tersedia.

- Impresfund: subtotal = volume x tarif; total pengajuan = jumlah subtotal.
- Beban: potongan pajak dalam rupiah; total pengajuan memakai bruto, sedangkan
  Nominal Setelah Pajak diisi manual dalam rupiah, tanpa perhitungan otomatis.
- Potongan pajak pada baris baru Honor dan Beban memiliki nilai awal 0 rupiah,
  tetap dapat diedit. Baris yang hanya berisi pajak default 0 tetap diabaikan.
- Honor: Total Diterima diisi manual dalam rupiah, tanpa perhitungan otomatis;
  total pengajuan memakai bruto. Kewarganegaraan diisi WNI atau WNA; NPWP boleh kosong jika belum
  tersedia. Kolom lain wajib diisi pada baris yang digunakan.
- Ringkasan total hanya ditampilkan sebagai Nominal Diajukan di header; tidak ada
  total pengajuan, total pajak, atau total transfer di bawah tabel.
- Paste Excel dimulai dari sel aktif, hanya kolom input (tanpa header, nomor,
  atau hasil hitung). Impresfund: Uraian, Volume, Tarif. Beban: Nominal, Pot Pajak, Nominal Setelah Pajak,
  Nama Bank, Nama Rekening, No Rekening.
  Honor: WNI / WNA, Nominal Honor, Potongan Pajak, Total Diterima, Nama Penerima, Nama Rekening,
  Nama Bank, Nomor Rekening, No KTP / Paspor, No NPWP.
- Rincian tampil sebagai sel spreadsheet dengan header kolom A, B, dst., nomor
  baris dan header yang tetap terlihat saat scroll, serta penanda rentang aktif.
  Klik/drag, Shift+klik atau Shift+panah memilih rentang. Panah, Tab/Shift+Tab,
  dan Enter/Shift+Enter berpindah sel. Ketik untuk mengganti isi; klik dua kali
  atau F2 untuk mengedit teks, Escape untuk selesai. Delete/Backspace mengosongkan
  kolom input dalam rentang tanpa menghapus baris atau mengubah hasil hitung.
- Ctrl+C/Ctrl+V (Mac: Cmd+C/Cmd+V) menyalin/menempel kolom input dalam rentang.
  Tombol Salin Semua ke Excel juga hanya menyalin kolom input, tanpa nomor,
  hasil hitung, atau header, sehingga hasilnya dapat ditempel kembali ke sel
  input pertama tanpa menghapus kolom di Excel. Paste pada rentang dimulai
  dari sudut kiri atas; baris baru ditambahkan otomatis bila diperlukan.
  Saat mengedit teks di dalam sel, copy/paste satu teks tetap bekerja secara native.
- Angka menggunakan format Indonesia, maksimal dua desimal. No Rekening harus
  berupa teks di Excel untuk mempertahankan nol di depan. Maksimal 500 baris.
  No KTP / Paspor dan No NPWP juga diperlakukan sebagai teks.
- Baris kosong diabaikan; rincian tidak lengkap atau tidak valid ditandai dan
  total ditampilkan sebagai "Belum valid", bukan total parsial.

Validasi fitur: `php artisan test --filter="PencairanFormTest|UnitDrkOptionsTest"` dan
`node --test tests\js\pencairan-grid.test.js`.

## 🛠️ Teknologi yang Digunakan

### Backend
- **PHP** 8.4.12
- **Laravel Framework** 12.28.1
- **Composer** 2.8.11

### Frontend
- **HTML5**, **CSS3**, **JavaScript ES6**
- **Bootstrap** 5.3.8
- **Vite** 7.0.4 (Build Tool)
- **Tailwind CSS** 4.0.0
- **Axios** 1.11.0 (HTTP Client)

### Integrasi Data
- **Google Sheets API v4** (sebagai primary database)
- **Google API Client** 2.18.3
- **Laravel Google Sheets** 7.1.4

### Package Pendukung
- **Laravel DomPDF** 3.1.1 - untuk generate laporan PDF dengan preview
- **Carbon** 3.10.3 - untuk manipulasi waktu dan tanggal
- **GuzzleHTTP** 7.10.0 - untuk HTTP client
- **Firebase JWT** 6.11.1 - untuk autentikasi token
- **PHP CORS** 1.3.0 - untuk pengaturan CORS

## 💻 Persyaratan Sistem

### Minimum Requirements
- **PHP**: 8.2 atau lebih tinggi
- **Composer**: 2.0 atau lebih tinggi
- **Node.js**: 14.x atau lebih tinggi (untuk asset compilation)
- **Ekstensi PHP**: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

### Browser yang Didukung
- Google Chrome (rekomendasi)
- Mozilla Firefox
- Microsoft Edge
- Apple Safari

## 🚀 Instalasi dan Setup Lokal

### 1. Clone Repository
```bash
git clone https://github.com/alfianmutaqin01/MONITA-Monitering-Anggaran-TUP.git monita
cd monita
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies dan build assets
npm install
npm run build
```

### 3. Konfigurasi Environment
```bash
# Duplikasi file environment
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Konfigurasi Database & Cache
Sistem menggunakan SQLite sebagai database default untuk session dan cache. Pastikan konfigurasi di `.env`:

```env
DB_CONNECTION=sqlite
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

Setup tabel cache dan session:
```bash
php artisan migrate
```

### 5. Menjalankan Aplikasi
```bash
# Development mode dengan hot reload
npm run dev

# Atau menggunakan Laravel development server
php artisan serve

# Akses aplikasi di browser
# http://localhost:8000
```

## 🔧 Konfigurasi Google Sheets API

### 1. Setup Service Account
- Buat Service Account di [Google Cloud Console](https://console.cloud.google.com/)
- Download file JSON credentials
- Simpan file credentials di: `storage/app/credentials/service-account.json`

**File Service Account Example:**
```json
{

}
```

### 2. Konfigurasi Google Sheets di Environment
Tambahkan konfigurasi berikut di file `.env`:

```env
# ID Google Sheet yang sedang aktif
GOOGLE_SPREADSHEET_ID="1fENIzciA4gJZryj9FQPHKcQqXrbSBgViiRLxqQhU18M"
ACTIVE_YEAR=2025

# Untuk penyimpanan historis
GOOGLE_SPREADSHEET_ID_YEAR_2025="1fENIzciA4gJZryj9FQPHKcQqXrbSBgViiRLxqQhU18M"


# Konfigurasi Tanda Tangan Laporan

```

### 3. Izin Akses Google Sheets
Pastikan Service Account (`keuangan-tup@monita-471208.iam.gserviceaccount.com`) memiliki izin **Editor** pada Google Sheets target.

## 🔒 Keamanan dan Autentikasi

Sistem menerapkan mekanisme keamanan yang kuat:

### Password Security
- **Salting Kustom**: Setiap password dienkripsi dengan salt unik berdasarkan username pengguna menggunakan `Hash::make({password} . {username})`
- **Rainbow Table Protection**: Teknik salting kustom mencegah serangan rainbow table
- **Dual Authentication**: Support untuk password hashed dan plain text (migrasi bertahap)

### Autentikasi
- **Session-based Authentication** untuk menjaga keamanan akses
- **Role-based Access Control (RBAC)** dengan dua level akses:
  - **Admin Keuangan**: Akses penuh ke semua fitur
  - **User Unit**: Akses terbatas hanya untuk data unit masing-masing
- **CAPTCHA Protection**: Implementasi CAPTCHA pada halaman login untuk mencegah brute force attacks

### Validasi Data
- Validasi keunikan untuk **Kode PP** dan **Username**
- Pembatasan akses data antar unit berdasarkan otorisasi
- Audit trail untuk memantau perubahan data

## 🌟 Fitur Khusus

### 1. Preview Laporan PDF
- **Review sebelum cetak**: Fitur preview laporan PDF sebelum proses pencetakan
- **Validasi data**: Memastikan akurasi dan kelengkapan data sebelum generate laporan final
- **Custom template**: Template laporan yang profesional dengan header dan footer institusi

### 2. Sistem CAPTCHA Login
- **Keamanan tambahan**: Mencegah akses otomatis dan brute force attacks
- **User-friendly**: Implementasi yang mudah digunakan namun efektif
- **Customizable**: Dapat disesuaikan dengan kebutuhan keamanan institusi

### 3. Integrasi Real-time dengan Google Sheets
- **Data terpusat**: Semua data tersimpan terpusat di Google Sheets
- **Sinkronisasi otomatis**: Perubahan data langsung tersinkronisasi
- **Backup otomatis**: Data tersimpan aman di cloud Google

## 📊 Kebutuhan Non-Fungsional

| Parameter | Spesifikasi |
|-----------|-------------|
| **Availability** | Sistem beroperasi 24/7 dengan downtime maksimal 2 jam/bulan |
| **Reliability** | Import data Excel dengan tingkat kegagalan < 1% per bulan |
| **Performance** | Menampilkan data transaksi dalam < 3 detik |
| **Scalability** | Mendukung minimal 100 pengguna aktif bersamaan |
| **Security** | HTTPS encryption, autentikasi login, CAPTCHA, dan role-based access control |

## 📞 Support

Untuk pertanyaan atau bantuan teknis, silakan hubungi tim pengembang:
- **Alfian Mutakim** 
- **Hamid Khaeruman** 

---

**MONITA** - Sistem Monitoring Anggaran Telkom University Purwokerto  
*Efisiensi, Akurasi, dan Transparansi dalam Pengelolaan Anggaran*
