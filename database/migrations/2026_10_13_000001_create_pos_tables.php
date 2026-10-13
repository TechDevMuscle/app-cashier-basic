<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Membuat struktur kasir. Semua nominal menggunakan bilangan bulat Rupiah, bukan float. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Akun baru secara default hanya memiliki hak kasir.
            $table->string('role')->default('cashier');
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            // Token unik mencegah satu keranjang tersimpan dua kali karena double tap.
            $table->uuid('checkout_token')->unique();
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('payment')->nullable();
            $table->unsignedBigInteger('change')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Snapshot: perubahan nama/harga produk tidak mengubah struk lama.
            $table->string('product_name');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    /** Menghapus tabel dari anak ke induk agar foreign key tidak melanggar relasi. */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
