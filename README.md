# Kasir Kita — latihan dasar Laravel

Aplikasi kasir toko kecil dengan Laravel 13, Blade, Eloquent, Tailwind CSS 4, dan JavaScript biasa. Tidak ada library tambahan untuk authentication, authorization, keranjang, atau UI. Tailwind/Vite dan Pest/Pint menggunakan dependency yang sudah tersedia pada source awal.

## Diskon produk

Pengelola Produk dapat mengatur diskon persentase bulat 0–100% di form tambah/edit. Katalog, kasir, keranjang, dan struk menampilkan harga setelah diskon serta penghematan. Diskon dibulatkan ke bawah per unit dalam Rupiah, lalu dikalikan jumlah. Kasir dan supervisor tidak dapat mengubah diskon.

Harga/diskon disimpan sebagai snapshot saat pending dibuat. Pembayaran dan refund menggunakan total setelah diskon. Transaksi dengan diskon 100% tetap diselesaikan kasir dengan pembayaran Rp 0 agar stok dan riwayat tercatat. Transaksi lama dianggap berdiskon 0% dan tidak dihitung ulang.

Untuk memperbarui salinan aplikasi versi sebelumnya:

```powershell
php artisan migrate
npm run build
php artisan view:clear
```

Pada folder kerja ini migration diskon sudah dijalankan. Penjelasan rumus, struktur kolom, contoh latihan, dan urutan membaca kode ada di [Panduan diskon](docs/DISKON.md).

## Mulai menjalankan

Kebutuhan: PHP yang kompatibel dengan composer.lock (runtime yang diuji: PHP 8.4), Composer, Node.js/npm, ekstensi PDO SQLite. Jalankan perintah dari folder LaravelTraningBasic.

Untuk salinan proyek baru:

```powershell
composer install
npm ci
# Hanya salin jika .env belum ada; jangan timpa konfigurasi yang sudah dipakai.
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
# Hanya buat jika database belum ada, jangan kosongkan database lama.
if (!(Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File }
php artisan migrate --seed
npm run build
php artisan serve
```

Pastikan `.env` menggunakan `APP_ENV=local`, `DB_CONNECTION=sqlite`, dan tidak memiliki `DB_DATABASE` yang menunjuk ke database lain. Dengan DB_DATABASE tidak diisi, Laravel menggunakan database/database.sqlite. Buka http://127.0.0.1:8000. `APP_KEY` dibuat satu kali saat setup awal; jangan regenerasi pada aplikasi yang sudah digunakan.

Pada folder kerja ini dependency sudah tersedia, migration/seeder sudah dijalankan, dan asset sudah dibuild. Untuk mulai cukup `php artisan serve`. Jika port 8000 sudah dipakai, gunakan `php artisan serve --port=8001`.

Saat mengubah CSS/JS, jalankan `npm run dev` di terminal kedua, atau jalankan kembali `npm run build`. Untuk perubahan PHP/Blade, refresh halaman. Jika template lama masih tampil, jalankan `php artisan view:clear`.

## Akun latihan

| Role | Email | Password |
| --- | --- | --- |
| Kasir | kasir@example.com | password |
| Pengelola Produk | produk@example.com | password |
| Supervisor | supervisor@example.com | password |

Seeder hanya berjalan pada lingkungan local/testing. Menjalankan seeder kembali tidak mereset produk, stok, password, atau role akun yang sudah ada. Akun demo digunakan untuk latihan lokal.

## Hak akses

Setiap akun mempunyai tepat satu role. Supervisor bukan super-admin; role ini khusus memeriksa dan membatalkan transaksi.

| Tindakan | Kasir | Pengelola Produk | Supervisor |
| --- | --- | --- | --- |
| Login, logout, katalog aktif | Ya | Ya | Ya |
| Tambah, edit, arsipkan produk | Tidak | Ya | Tidak |
| Buat transaksi dan terima pembayaran | Ya | Tidak | Tidak |
| Riwayat/detail/struk | Transaksi sendiri | Tidak | Semua transaksi |
| Batalkan transaksi | Tidak | Tidak | Ya |

Tiga tempat pemeriksaan authorization:

1. `routes/web.php` dan `EnsureRole::handle()` membatasi kelompok halaman berdasarkan role.
2. `ProductPolicy` dan `SalePolicy` memeriksa tindakan serta pemilik/status transaksi. Controller memanggil `Gate::authorize()`, sehingga URL atau request langsung tetap diperiksa.
3. Blade `@can` menampilkan tombol sesuai izin. Ini hanya untuk kenyamanan UI, bukan pengganti pemeriksaan server.

Role tidak ada di daftar fillable model User, sehingga tidak dapat dinaikkan melalui mass assignment form umum. Akun dibuat melalui seeder; registrasi dan CRUD akun tidak termasuk latihan ini.

## Latihan dari awal sampai akhir

### A. Pengelola menambah produk

1. Login sebagai produk@example.com.
2. Buka Kelola Produk, lalu Tambah produk.
3. Isi kode unik, nama, harga normal Rupiah bulat, diskon 0–100%, dan stok. Contoh: COKELAT, Cokelat Hangat, 15000, diskon 10%, stok 10. Harga akhirnya Rp 13.500.
4. Coba edit produk dan cari berdasarkan nama/kode.
5. Tombol Arsipkan menggunakan soft delete: produk hilang dari katalog penjualan, tetapi tetap ada di database.

Stok pada form edit adalah jumlah akhir hasil pengecekan fisik, bukan penambahan stok. Muat ulang data sebelum melakukan koreksi stok.

### B. Kasir menjual produk

1. Logout, lalu login sebagai kasir@example.com.
2. Sentuh kartu produk. Sentuhan berikutnya menambah jumlah.
3. Gunakan tombol +, −, Hapus, atau Kosongkan. Pencarian hanya memfilter katalog dan tidak mengubah keranjang.
4. Tekan Simpan & lanjut bayar. Transaksi dibuat sebagai pending.
5. Pilih Uang pas atau isi uang diterima, lalu Selesaikan pembayaran.
6. Status menjadi paid. Stok berkurang dan struk bisa dicetak memakai dialog cetak browser.

Jika pembayaran belum selesai, transaksi dapat dibuka kembali dari Riwayat. Keranjang yang belum disimpan hanya hidup pada halaman browser dan hilang ketika halaman direfresh; ini sengaja dibuat sederhana.

### C. Supervisor membatalkan karena gagal bayar

1. Kasir membuat pending, tetapi pelanggan gagal membayar atau batal membeli.
2. Catat nomor transaksi. Kasir tidak memiliki tombol atau akses pembatalan.
3. Login sebagai supervisor@example.com, buka Transaksi lalu Detail.
4. Isi alasan minimal 5 karakter dan tekan Batalkan seluruh transaksi.
5. Status menjadi cancelled. Nama supervisor, waktu, dan alasan disimpan. Stok pending tidak berubah.

### D. Pembatalan setelah sudah dibayar

1. Kasir menyelesaikan pembayaran.
2. Supervisor membatalkan transaksi setelah memastikan barang belum dibawa atau sudah dikembalikan.
3. Stok semua barang dikembalikan satu kali, termasuk produk yang sudah diarsipkan.
4. Refund tunai yang tercatat adalah total belanja, karena kembalian sebelumnya sudah diberikan.
5. Petugas menyerahkan uang refund secara manual. Aplikasi mencatat nominal yang harus dikembalikan, tidak mengirim uang ataupun mencatat konfirmasi penyerahannya.

Pembatalan berlaku untuk seluruh transaksi. Pembatalan parsial dan proses refund elektronik tidak termasuk latihan ini.

## Status dan konsistensi data

| Status | Arti | Perlakuan stok |
| --- | --- | --- |
| pending | Belanja tersimpan, belum dibayar | Belum dikurangi atau direservasi |
| paid | Pembayaran berhasil | Berkurang sesuai jumlah barang |
| cancelled | Dibatalkan supervisor | Stok paid dipulihkan; pending tidak berubah |

Transisi yang diizinkan: pending → paid, pending → cancelled, paid → cancelled. Transaksi cancelled tidak bisa dibayar atau dibatalkan kembali. Transaksi bukan CRUD bebas: hasil penjualan dan audit pembatalan dipertahankan.

Nama, harga normal, persentase/potongan diskon, serta harga setelah diskon disalin ke sale_items pada saat pending dibuat. Pembayaran tetap menggunakan harga yang sudah disepakati tersebut, meskipun harga/diskon katalog berubah. Jika produk diarsipkan atau stok tidak cukup sebelum bayar, pembayaran ditolak.

Server menghitung harga, subtotal, total, dan kembalian. Harga/total yang dikirim browser diabaikan. Setiap keranjang memiliki checkout_token unik, sehingga submit ulang tidak membuat transaksi kedua.

Pembayaran dan pembatalan memakai `DB::transaction()`. Jika satu produk gagal, semua perubahan stok di-rollback. `lockForUpdate()` dan pemeriksaan ulang policy setelah lock melindungi status serta stok pada database dengan row locking. SQLite menggunakan locking pada database, bukan row lock; dipilih untuk latihan lokal. Pengujian otomatis memverifikasi alur dan rollback, bukan simulasi beban beberapa terminal kasir serentak.

## Urutan membaca source untuk pemula

1. `routes/web.php`: URL, method HTTP, auth, role, dan controller tujuan.
2. `app/Http/Controllers/AuthController.php`: validasi login, Auth::attempt, session, logout.
3. `app/Http/Middleware/EnsureRole.php`: menolak role yang tidak sesuai.
4. `app/Policies/ProductPolicy.php` dan `SalePolicy.php`: izin per tindakan dan kepemilikan.
5. `app/Providers/AppServiceProvider.php`: registrasi hubungan model dengan policy.
6. `database/migrations/2026_10_13_000001_create_pos_tables.php`: tabel, foreign key, dan snapshot transaksi.
7. `app/Models`: fillable, casts, relasi, soft delete.
8. `app/Http/Controllers/ProductController.php`: CRUD Eloquent, pencarian, pagination.
9. `app/Http/Controllers/SaleController.php`: pending, bayar, batal, transaction, dan lock.
10. `resources/views`: Blade, CSRF, method PUT/DELETE, @can, Tailwind.
11. `resources/js/app.js`: Map keranjang, DOM, hidden input, dan perkiraan kembalian.
12. `database/seeders/DatabaseSeeder.php`: akun/data demo.
13. `tests/Feature/PosTest.php`: pengujian aturan dan alur bisnis.

Setiap method aplikasi yang ditambahkan memiliki komentar bahasa Indonesia. Closure kecil diberi catatan pada alur di sekitarnya.

## Contoh Eloquent yang ada di aplikasi

```php
// CREATE: hanya field dalam fillable yang boleh diisi sekaligus.
Product::create($data);

// READ: SoftDeletes otomatis menyembunyikan produk arsip.
Product::orderBy('name')->paginate(12);

// UPDATE dan DELETE: model mewakili satu baris database.
$product->update($data);
$product->delete(); // Mengisi deleted_at.

// RELASI: sale_id diisi oleh relasi, bukan dari input pengguna.
$sale->items()->createMany($items);

// EAGER LOADING: memuat kasir bersama daftar transaksi untuk menghindari N+1 query.
Sale::with('user')->latest('id')->paginate(15);
```

User hasMany Sale, Sale belongsTo User, Sale hasMany SaleItem, SaleItem belongsTo Product. Sale juga belongsTo User melalui cancelled_by untuk jejak supervisor.

Validasi memeriksa bentuk/nilai input; authentication memeriksa siapa pengguna; authorization memeriksa tindakan apa yang boleh dilakukan. Ketiganya memiliki peran berbeda dan digunakan bersama.

## Verifikasi

```powershell
php artisan test
npm run build
php vendor/bin/pint --test app bootstrap/app.php config/app.php routes/web.php database/migrations/2026_10_13_000001_create_pos_tables.php database/migrations/2026_10_13_000002_add_product_discounts.php database/seeders/DatabaseSeeder.php tests/Feature
```

Pengujian mencakup login/logout, halaman sesuai role, CRUD/validasi produk, akses URL langsung, kepemilikan transaksi, harga server, submit ulang, pembayaran kurang, pembayaran ganda, rollback stok, produk arsip, snapshot harga, alasan pembatalan, refund, dan pembatalan berulang. Database test memakai SQLite in-memory, terpisah dari database latihan.

`tests/Feature/DiscountTest.php` menambah kasus validasi diskon, pembulatan per unit, transaksi campuran, manipulasi harga/diskon dari browser, snapshot diskon, pembayaran nol, refund net, dan upgrade transaksi lama. Total saat penambahan fitur diskon: 43 pengujian dengan 266 assertion.

Referensi belajar resmi: [Authentication](https://laravel.com/docs/13.x/authentication), [Authorization](https://laravel.com/framework/docs/13.x/authorization), [Eloquent](https://laravel.com/docs/13.x/eloquent), dan [Database transactions](https://laravel.com/docs/13.x/database).
