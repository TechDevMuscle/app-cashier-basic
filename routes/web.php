<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

// Guest hanya untuk pengguna belum login; throttle membatasi percobaan password.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

// Session login wajib. Semua form mutasi memakai CSRF bawaan Laravel.
Route::middleware('auth')->group(function (): void {
    Route::get('/', [AuthController::class, 'home'])->name('home');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/catalog', [ProductController::class, 'catalog'])->name('catalog');
    // Pengelolaan produk dibatasi di middleware dan policy controller.
    Route::middleware('role:product_manager')->group(function (): void {
        Route::resource('products', ProductController::class)->except('show');
    });
    // Kasir membuat dan membayar transaksi sendiri.
    Route::middleware('role:cashier')->group(function (): void {
        Route::get('/pos', [SaleController::class, 'pos'])->name('pos.index');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::post('/sales/{sale}/pay', [SaleController::class, 'pay'])->name('sales.pay');
    });
    // Policy show memeriksa kepemilikan, bukan sekadar role.
    Route::middleware('role:cashier,supervisor')->group(function (): void {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    });
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->middleware('role:supervisor')->name('sales.cancel');
});
