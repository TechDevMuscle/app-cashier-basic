<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /** Riwayat hanya tersedia bagi kasir dan supervisor. */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::CASHIER, User::SUPERVISOR], true);
    }

    /** Kasir dibatasi ke transaksi sendiri; supervisor dapat membaca semuanya. */
    public function view(User $user, Sale $sale): bool
    {
        return $user->hasRole(User::SUPERVISOR)
            || ($user->hasRole(User::CASHIER) && $sale->user_id === $user->id);
    }

    /** Hanya kasir membuat transaksi baru. */
    public function create(User $user): bool
    {
        return $user->hasRole(User::CASHIER);
    }

    /** Kasir hanya boleh menerima pembayaran transaksi miliknya yang masih pending. */
    public function pay(User $user, Sale $sale): bool
    {
        return $user->hasRole(User::CASHIER) && $sale->user_id === $user->id && $sale->status === Sale::PENDING;
    }

    /** Hanya supervisor membatalkan; transaksi yang sudah batal tidak boleh dibatalkan lagi. */
    public function cancel(User $user, Sale $sale): bool
    {
        return $user->hasRole(User::SUPERVISOR) && in_array($sale->status, [Sale::PENDING, Sale::PAID], true);
    }
}
