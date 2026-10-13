# Panduan diskon produk untuk pemula

Fitur memakai model Eloquent, validasi/controller Laravel, Blade, Tailwind, dan JavaScript biasa. Tidak ada library baru yang ditambahkan.

## Cara menggunakan

1. Login sebagai `produk@example.com` dengan password `password`.
2. Buka Tambah Produk atau Edit. Masukkan harga normal dan persentase diskon bulat 0–100.
3. Preview menampilkan harga setelah diskon dan potongan per unit. Simpan produk.
4. Login sebagai `kasir@example.com`. Kartu produk menampilkan harga normal dicoret, persentase, dan harga akhir.
5. Tambahkan produk ke keranjang. Periksa subtotal, total sebelum diskon, penghematan, dan total akhir.
6. Simpan pending dan selesaikan pembayaran. Struk memakai snapshot diskon yang tersimpan.
7. Untuk pembatalan, login sebagai supervisor. Refund transaksi paid memakai total setelah diskon.

Kasir dan supervisor hanya membaca diskon. Role pengelola produk yang boleh menambah atau mengubahnya; pembatasan berlaku juga untuk request langsung.

## Rumus dan pembulatan

```text
potonganPerUnit = floor(hargaNormal × diskonPersen / 100)
hargaNetPerUnit = hargaNormal − potonganPerUnit
subtotalNet = hargaNetPerUnit × jumlah
totalNormal = jumlah semua (hargaNormal × jumlah)
totalDiskon = jumlah semua (potonganPerUnit × jumlah)
totalAkhir = totalNormal − totalDiskon
```

PHP menggunakan `intdiv()` untuk pembagian integer. Dengan harga/persen non-negatif, hasilnya dibulatkan ke bawah. Perhitungan Rupiah tidak memakai float, sehingga pembulatan ditentukan secara jelas.

Contoh harga normal Rp 19.999, diskon 15%, jumlah 2:

| Komponen | Perhitungan | Hasil |
| --- | --- | --- |
| Potongan per unit | floor(19.999 × 15 / 100) | Rp 2.999 |
| Harga net per unit | 19.999 − 2.999 | Rp 17.000 |
| Total normal | 19.999 × 2 | Rp 39.998 |
| Total diskon | 2.999 × 2 | Rp 5.998 |
| Total akhir | 17.000 × 2 | Rp 34.000 |

Pembulatan dilakukan sebelum perkalian jumlah. Membulatkan 15% dari total normal akan menghasilkan potongan berbeda (Rp 5.999); pendekatan itu tidak digunakan di aplikasi ini.

## Alur kode

1. `ProductController::validatedData()` memvalidasi diskon bulat 0–100. Nilai kosong, pecahan, negatif, dan di atas 100 ditolak. Untuk request lama tanpa field diskon, create memakai 0 dan edit mempertahankan diskon yang sudah ada.
2. `Product::discountAmount()` menghitung potongan per unit. `Product::finalPrice()` mengembalikan harga normal dikurangi potongan.
3. `products/price.blade.php` dipakai bersama oleh katalog, daftar produk, dan layar kasir supaya tampilan konsisten.
4. `setupDiscountPreview()` di app.js menghitung preview form. `renderCart()` memakai harga net/potongan yang diberikan Blade untuk memperkirakan keranjang.
5. Browser mengirim ID produk dan jumlah saja. Walaupun pengguna menyisipkan harga atau persentase palsu ke request, `SaleController::store()` mengabaikannya dan membaca ulang data produk dari database.
6. Controller menyimpan snapshot lewat `$sale->items()->createMany()`, beserta total normal, diskon, dan net pada transaksi.
7. `pay()` menggunakan total snapshot, memeriksa stok terbaru, lalu mengurangi stok dalam database transaction.
8. `cancel()` memulihkan stok transaksi paid satu kali dan mencatat refund sebesar total net snapshot.

## Makna kolom database

| Tabel / kolom | Makna |
| --- | --- |
| products.price | Harga normal produk saat ini |
| products.discount_percent | Persentase produk saat ini, default 0 |
| sale_items.original_price | Snapshot harga normal per unit |
| sale_items.discount_percent | Snapshot persentase diskon |
| sale_items.discount_amount | Snapshot potongan Rupiah per unit, bukan seluruh baris |
| sale_items.price | Snapshot harga net per unit |
| sale_items.quantity | Jumlah barang |
| sale_items.subtotal | Harga net per unit × jumlah |
| sales.original_total | Total belanja sebelum diskon |
| sales.discount_total | Total potongan seluruh unit barang |
| sales.total | Total akhir setelah diskon; dipakai bayar, kembalian, dan refund |

Catatan penting: `Product.price` berarti harga normal, sedangkan `SaleItem.price` berarti harga net yang disepakati. Selalu perhatikan model/tabelnya ketika membaca kode.

## Mengapa perlu snapshot?

Contoh: Kopi Rp 18.000 dengan diskon 10% dibuat pending untuk 2 unit. Totalnya Rp 32.400. Setelah itu pengelola mengubah harga ke Rp 50.000 dan diskon ke 50%. Pending tadi tetap Rp 32.400, bukan dihitung ulang menjadi Rp 50.000. Struk dan refund juga tetap memakai Rp 32.400.

Snapshot dibuat saat pending, bukan saat keranjang di browser diisi. Jika katalog berubah sebelum pengguna menekan Simpan, server memakai harga/diskon terbaru dan menampilkannya pada detail pending sebelum pembayaran. Harga keranjang adalah perkiraan.

## Diskon 100% dan transaksi gratis

Jika semua barang didiskon 100%, total menjadi Rp 0. Input pembayaran otomatis berisi 0 dan tombol menjadi Selesaikan transaksi gratis. Kasir tetap menekan tombol agar status berubah dari pending ke paid, stok berkurang, dan struk tersedia.

Validasi pembayaran mengizinkan nilai minimal 0. Pemeriksaan `$payment < $sale->total` setelah lock memastikan pembayaran nol ditolak untuk transaksi dengan total positif. Jika pelanggan menyerahkan uang pada transaksi gratis, seluruh nominal itu menjadi kembalian.

Pembatalan transaksi gratis yang sudah selesai mengembalikan stok dan mencatat refund Rp 0. Transaksi pending tetap tidak mengubah stok ketika dibatalkan.

## Upgrade transaksi lama

Migration `2026_10_13_000002_add_product_discounts.php` menambah kolom dengan diskon default 0, menyalin `sale_items.price` ke `original_price`, dan menyalin `sales.total` ke `original_total`. Harga produk saat ini tidak dipakai untuk backfill; harga historis bisa berbeda dari katalog.

Transaksi pending, paid, dan cancelled lama mempertahankan total, pembayaran, kembalian, refund, serta statusnya. Gunakan `php artisan migrate` untuk upgrade. `migrate:fresh` tidak diperlukan karena menghapus tabel dan data.

## Membaca pengujian

`tests/Feature/DiscountTest.php` menguji:

- Batas 0%, 100%, pembulatan pecahan, dan harga kecil/besar.
- Hak pengelola produk serta penolakan role lain.
- Keranjang campuran, nilai palsu dari browser, dan submit ulang.
- Harga/diskon/nama katalog berubah setelah pending.
- Pembayaran nol, pembayaran negatif atau kurang, dan pengurangan stok.
- Refund memakai total net dan pembatalan hanya satu kali.
- Migration pada transaksi lama yang sudah dibayar dengan harga katalog yang telah berubah.

Jalankan `php artisan test` dari folder aplikasi. Test memakai database SQLite in-memory, bukan database latihan.

Diskon berlaku langsung per produk. Tidak ada jadwal promo, diskon manual kasir, atau penggabungan beberapa diskon dalam fitur ini.
