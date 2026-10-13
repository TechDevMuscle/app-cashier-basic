<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /** Semua role terdaftar boleh membaca daftar produk aktif. */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::CASHIER, User::PRODUCT_MANAGER, User::SUPERVISOR], true);
    }

    /** Hanya pengelola produk yang boleh membuka daftar pengelolaan. */
    public function manage(User $user): bool
    {
        return $user->hasRole(User::PRODUCT_MANAGER);
    }

    /** Hak menambah produk terpisah dari hak menjual barang. */
    public function create(User $user): bool
    {
        return $user->hasRole(User::PRODUCT_MANAGER);
    }

    /** Mengubah harga/stok hanya boleh dilakukan pengelola produk. */
    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(User::PRODUCT_MANAGER);
    }

    /** Penghapusan menggunakan soft delete agar riwayat transaksi tetap utuh. */
    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(User::PRODUCT_MANAGER);
    }
}
