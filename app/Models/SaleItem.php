<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    // price tetap bermakna harga net per unit agar pembayaran dan riwayat tetap kompatibel.
    protected $fillable = ['product_id', 'product_name', 'original_price', 'discount_percent', 'discount_amount', 'price', 'quantity', 'subtotal'];

    /** Nominal dan jumlah menggunakan integer supaya perhitungan Rupiah tepat. */
    protected function casts(): array
    {
        return ['original_price' => 'integer', 'discount_percent' => 'integer', 'discount_amount' => 'integer', 'price' => 'integer', 'quantity' => 'integer', 'subtotal' => 'integer'];
    }

    /** Detail selalu dimiliki oleh satu transaksi. */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** withTrashed menjaga relasi tetap tersedia setelah produk diarsipkan. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
