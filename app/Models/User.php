<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const CASHIER = 'cashier';

    public const PRODUCT_MANAGER = 'product_manager';

    public const SUPERVISOR = 'supervisor';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Memeriksa satu role. Role tidak masuk fillable agar form umum tidak bisa menaikkan hak akses. */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /** Satu kasir dapat membuat banyak transaksi (relasi one-to-many). */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Mengubah tipe atribut; hashed membuat password otomatis di-hash ketika disimpan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
