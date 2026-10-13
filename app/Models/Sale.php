<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['user_id', 'checkout_token', 'status', 'original_total', 'discount_total', 'total', 'payment', 'change', 'paid_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'refund_amount'];

    /** Cast tanggal menjadi Carbon dan nominal menjadi integer agar mudah dipakai di Blade. */
    protected function casts(): array
    {
        return ['original_total' => 'integer', 'discount_total' => 'integer', 'total' => 'integer', 'payment' => 'integer', 'change' => 'integer', 'refund_amount' => 'integer', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** Kasir pemilik transaksi; user_id menjadi foreign key secara otomatis. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Menentukan supervisor pembatal dengan foreign key khusus cancelled_by. */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Mengambil semua baris produk dalam satu transaksi. */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Label Indonesia memudahkan pengguna memahami status internal database. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Menunggu pembayaran',
            self::PAID => 'Dibayar',
            self::CANCELLED => 'Dibatalkan',
            default => $this->status,
        };
    }
}
