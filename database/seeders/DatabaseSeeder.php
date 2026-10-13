<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data demo hanya local/testing; seeder ulang tidak mereset password dan stok.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Seeder demo hanya boleh dijalankan di local/testing.');

            return;
        }
        foreach ([
            ['Kasir Demo', 'kasir@example.com', User::CASHIER],
            ['Pengelola Produk', 'produk@example.com', User::PRODUCT_MANAGER],
            ['Supervisor Demo', 'supervisor@example.com', User::SUPERVISOR],
        ] as [$name, $email, $role]) {
            if (! User::where('email', $email)->exists()) {
                $user = new User(['name' => $name, 'email' => $email, 'password' => 'password']);
                // Role ditetapkan kode tepercaya, tidak berasal dari input form pengguna.
                $user->role = $role;
                $user->save();
            }
        }
        foreach ([
            ['KOPI', 'Kopi Susu', 18000, 30], ['TEH', 'Es Teh', 8000, 50],
            ['ROTI', 'Roti Cokelat', 12000, 25], ['AIR', 'Air Mineral', 5000, 40],
            ['NASI', 'Nasi Goreng', 25000, 20], ['MIE', 'Mie Goreng', 22000, 20],
            ['JUS', 'Jus Jeruk', 15000, 15], ['DONAT', 'Donat Gula', 10000, 24],
        ] as [$code, $name, $price, $stock]) {
            Product::withTrashed()->firstOrCreate(['code' => $code], compact('name', 'price', 'stock'));
        }
    }
}
