<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Menambah diskon dan snapshot tanpa menghitung ulang harga transaksi lama. */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedTinyInteger('discount_percent')->default(0);
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
        });

        // Transaksi lama memiliki diskon 0%. Salin harga/total historis, bukan harga katalog terkini.
        DB::table('sale_items')->update(['original_price' => DB::raw('price')]);
        DB::table('sales')->update(['original_total' => DB::raw('total')]);
    }

    /** Rollback schema tidak mengubah nominal net yang telah tercatat di price/total. */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['original_total', 'discount_total']);
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['original_price', 'discount_percent', 'discount_amount']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('discount_percent');
        });
    }
};
