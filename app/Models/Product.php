<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'price', 'stock', 'discount_percent'];

    // Default juga berlaku untuk objek baru di form, sebelum tersimpan ke database.
    protected $attributes = ['discount_percent' => 0];

    /** Cast membuat nominal dan stok selalu dibaca sebagai integer. */
    protected function casts(): array
    {
        return ['price' => 'integer', 'stock' => 'integer', 'discount_percent' => 'integer'];
    }

    /** Potongan per unit dibulatkan ke bawah memakai intdiv, sehingga tidak ada float Rupiah. */
    public function discountAmount(): int
    {
        return intdiv($this->price * $this->discount_percent, 100);
    }

    /** Harga net per unit; diskon 100% menghasilkan nol dan masih boleh dijual. */
    public function finalPrice(): int
    {
        return $this->price - $this->discountAmount();
    }

    /** Produk dapat muncul di banyak detail transaksi. */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
