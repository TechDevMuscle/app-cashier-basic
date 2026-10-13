<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Produk berdiskon untuk pengujian; kode pendek tetap sesuai batas input produk. */
function discountProduct(int $price = 18000, int $percent = 10): Product
{
    return Product::create([
        'code' => Str::random(10), 'name' => 'Kopi diskon', 'price' => $price,
        'stock' => 10, 'discount_percent' => $percent,
    ]);
}

/** Membuat pending via endpoint sebenarnya agar snapshot dan authorization ikut diuji. */
function discountSale($test, Product $product, int $quantity = 2): Sale
{
    $test->actingAs(User::factory()->create(['role' => User::CASHIER]))
        ->post('/sales', ['checkout_token' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => $quantity]]])
        ->assertSessionHasNoErrors()->assertRedirect();

    return Sale::latest('id')->firstOrFail();
}

// Kasus pecahan membuktikan potongan dibulatkan per unit, termasuk nilai kecil/batas harga.
test('discount uses whole rupiah rounded down per unit', function ($price, $percent, $discount, $net) {
    $product = discountProduct($price, $percent);
    expect($product->discountAmount())->toBe($discount)->and($product->finalPrice())->toBe($net);
})->with([
    [18000, 0, 0, 18000], [18000, 10, 1800, 16200], [18000, 100, 18000, 0],
    [19999, 15, 2999, 17000], [1, 1, 0, 1], [1000000000, 100, 1000000000, 0],
]);

test('manager can add and edit product discounts with preview form', function () {
    $this->actingAs(User::factory()->create(['role' => User::PRODUCT_MANAGER]));
    $this->get('/products/create')->assertOk()->assertSee('Diskon (%)');
    $this->post('/products', ['code' => 'PROMO', 'name' => 'Kopi', 'price' => 18000, 'stock' => 10, 'discount_percent' => 10])->assertSessionHasNoErrors();
    $product = Product::firstOrFail();
    expect($product->discount_percent)->toBe(10)->and($product->finalPrice())->toBe(16200);
    $this->put(route('products.update', $product), ['code' => 'PROMO', 'name' => 'Kopi', 'price' => 18000, 'stock' => 10, 'discount_percent' => 100])->assertSessionHasNoErrors();
    expect($product->fresh()->finalPrice())->toBe(0);
    $this->get('/products')->assertOk()->assertSee('Diskon 100%');
    $this->get('/catalog')->assertOk()->assertSee('Diskon 100%');
});

test('manager rejects discounts outside integer range', function ($invalid) {
    $this->actingAs(User::factory()->create(['role' => User::PRODUCT_MANAGER]))
        ->post('/products', ['code' => 'INVALID', 'name' => 'Invalid', 'price' => 10000, 'stock' => 10, 'discount_percent' => $invalid])
        ->assertSessionHasErrors('discount_percent');
    expect(Product::count())->toBe(0);
})->with([-1, 101, 10.5, 'abc', '']);

test('cashier and supervisor cannot change discounts', function ($role) {
    $product = discountProduct();
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->put(route('products.update', $product), ['code' => $product->code, 'name' => $product->name, 'price' => 18000, 'stock' => 10, 'discount_percent' => 100])
        ->assertForbidden();
    expect($product->fresh()->discount_percent)->toBe(10);
})->with([User::CASHIER, User::SUPERVISOR]);

test('mixed cart ignores client prices and discounts and rounds before multiplying', function () {
    $discounted = discountProduct(19999, 15);
    $normal = discountProduct(5000, 0);
    $this->actingAs(User::factory()->create(['role' => User::CASHIER]));
    $token = (string) Str::uuid();
    $payload = ['checkout_token' => $token, 'total' => 0, 'discount_total' => 999999, 'items' => [
        ['product_id' => $discounted->id, 'quantity' => 2, 'price' => 0, 'discount_percent' => 100, 'discount_amount' => 19999],
        ['product_id' => $normal->id, 'quantity' => 1],
    ]];
    $this->post('/sales', $payload)->assertSessionHasNoErrors();
    $this->post('/sales', $payload)->assertSessionHasNoErrors();
    expect(Sale::count())->toBe(1);
    $sale = Sale::firstOrFail();
    $item = $sale->items()->where('product_id', $discounted->id)->firstOrFail();
    expect($sale->original_total)->toBe(44998)->and($sale->discount_total)->toBe(5998)->and($sale->total)->toBe(39000)
        ->and($item->original_price)->toBe(19999)->and($item->discount_amount)->toBe(2999)->and($item->price)->toBe(17000)->and($item->subtotal)->toBe(34000);
    $this->get('/pos')->assertOk()->assertSee('Diskon 15%');
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Total diskon')->assertSee('5.998');
});

test('snapshot stays unchanged when catalog price discount and name change', function () {
    $product = discountProduct();
    $sale = discountSale($this, $product);
    $product->update(['name' => 'Harga baru', 'price' => 50000, 'discount_percent' => 50]);
    $this->post(route('sales.pay', $sale), ['payment' => 40000])->assertSessionHasNoErrors();
    $sale->refresh();
    expect($sale->total)->toBe(32400)->and($sale->discount_total)->toBe(3600)->and($sale->change)->toBe(7600)
        ->and($sale->items->first()->discount_percent)->toBe(10)->and($sale->items->first()->price)->toBe(16200)->and($product->fresh()->stock)->toBe(8);
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Diskon 10%')->assertSee('Kopi diskon')->assertDontSee('Harga baru');
});

test('zero payment is only accepted for zero total and still reduces stock', function () {
    $product = discountProduct(18000, 100);
    $sale = discountSale($this, $product);
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Selesaikan transaksi gratis');
    $this->post(route('sales.pay', $sale), ['payment' => 0])->assertSessionHasNoErrors();
    expect($sale->fresh()->status)->toBe(Sale::PAID)->and($sale->fresh()->payment)->toBe(0)->and($sale->fresh()->change)->toBe(0)->and($product->fresh()->stock)->toBe(8);
    $this->post(route('sales.pay', $sale), ['payment' => 0])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => User::SUPERVISOR]))
        ->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Pelanggan batal mengambil barang'])->assertSessionHasNoErrors();
    expect($sale->fresh()->refund_amount)->toBe(0)->and($product->fresh()->stock)->toBe(10);

    $normal = discountProduct(18000, 0);
    $normalSale = discountSale($this, $normal);
    $this->post(route('sales.pay', $normalSale), ['payment' => 0])->assertSessionHasErrors('payment');
    expect($normalSale->fresh()->status)->toBe(Sale::PENDING)->and($normal->fresh()->stock)->toBe(10);
});

test('discounted payment rejects negative money and insufficient discounted total', function () {
    $product = discountProduct();
    $sale = discountSale($this, $product);
    foreach ([-1, 32000] as $payment) {
        $this->post(route('sales.pay', $sale), ['payment' => $payment])->assertSessionHasErrors('payment');
    }
    expect($sale->fresh()->status)->toBe(Sale::PENDING)->and($product->fresh()->stock)->toBe(10);
});

test('discounted refund uses net total and restores stock exactly once', function () {
    $product = discountProduct();
    $sale = discountSale($this, $product);
    $this->post(route('sales.pay', $sale), ['payment' => 40000])->assertSessionHasNoErrors();
    $this->actingAs(User::factory()->create(['role' => User::SUPERVISOR]))
        ->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Pelanggan mengembalikan barang'])->assertSessionHasNoErrors();
    expect($sale->fresh()->refund_amount)->toBe(32400)->and($product->fresh()->stock)->toBe(10);
    $this->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Pembatalan ulang'])->assertForbidden();
    expect($product->fresh()->stock)->toBe(10);
});

test('upgrade backfills old historical prices without repricing transactions', function () {
    $product = discountProduct(18000, 0);
    $sale = discountSale($this, $product);
    $this->post(route('sales.pay', $sale), ['payment' => 40000])->assertSessionHasNoErrors();
    $product->update(['price' => 50000]);
    // Simulasi database versi sebelumnya: hapus kolom baru lalu jalankan migration upgrade.
    $migration = require database_path('migrations/2026_10_13_000002_add_product_discounts.php');
    $migration->down();
    $migration->up();
    $sale->refresh();
    $item = $sale->items()->firstOrFail();
    expect($sale->original_total)->toBe(36000)->and($sale->discount_total)->toBe(0)->and($sale->total)->toBe(36000)->and($sale->change)->toBe(4000)
        ->and($item->original_price)->toBe(18000)->and($item->price)->toBe(18000)->and($item->discount_percent)->toBe(0)->and($item->discount_amount)->toBe(0);
    $this->get(route('sales.show', $sale))->assertOk()->assertDontSee('Total diskon');
});
