<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Factory bawaan Laravel boleh mengisi role untuk menyiapkan akun pengujian. */
function posUser(string $role = User::CASHIER): User
{
    return User::factory()->create(['role' => $role]);
}

/** Membuat produk uji yang berbeda kode agar tidak melanggar unique constraint. */
function posProduct(int $stock = 10, int $price = 12000): Product
{
    return Product::create(['code' => (string) Str::uuid(), 'name' => 'Produk uji', 'price' => $price, 'stock' => $stock]);
}

/** Transaksi pending disiapkan lewat endpoint asli agar alur Eloquent ikut diuji. */
function pendingSale($test, User $cashier, array $items): Sale
{
    $test->actingAs($cashier)->post(route('sales.store'), ['checkout_token' => (string) Str::uuid(), 'items' => $items])->assertSessionHasNoErrors()->assertRedirect();

    return Sale::latest('id')->firstOrFail();
}

// Kredensial benar menghasilkan login; password salah tidak membuat session login.
test('login and logout use session authentication', function () {
    $user = User::factory()->create(['password' => 'password']);
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

// Tujuan setelah login serta akses halaman mengikuti tiga role yang disetujui.
test('each role has its own landing page', function ($role, $path) {
    $this->actingAs(posUser($role))->get('/')->assertRedirect($path);
})->with([[User::CASHIER, '/pos'], [User::PRODUCT_MANAGER, '/products'], [User::SUPERVISOR, '/sales']]);

test('each role can read the active catalog', function ($role) {
    posProduct();
    $this->actingAs(posUser($role))->get('/catalog')->assertOk()->assertSee('Produk uji');
})->with([User::CASHIER, User::PRODUCT_MANAGER, User::SUPERVISOR]);

test('product manager can create update and archive products', function () {
    $this->actingAs(posUser(User::PRODUCT_MANAGER));
    $this->get('/products/create')->assertOk();
    $this->post('/products', ['code' => 'TEST', 'name' => 'Kopi', 'price' => 18000, 'stock' => 20])->assertRedirect('/products');
    $product = Product::firstOrFail();
    $this->get(route('products.edit', $product))->assertOk();
    $this->put(route('products.update', $product), ['code' => 'TEST', 'name' => 'Kopi baru', 'price' => 19000, 'stock' => 15])->assertRedirect('/products');
    $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 19000, 'stock' => 15]);
    $this->delete(route('products.destroy', $product))->assertRedirect('/products');
    $this->assertSoftDeleted($product);
});

test('other roles cannot access any product mutation endpoint', function ($role) {
    $product = posProduct();
    $this->actingAs(posUser($role));
    $this->get('/products')->assertForbidden();
    $this->get('/products/create')->assertForbidden();
    $this->get(route('products.edit', $product))->assertForbidden();
    $this->post('/products', [])->assertForbidden();
    $this->put(route('products.update', $product), [])->assertForbidden();
    $this->delete(route('products.destroy', $product))->assertForbidden();
    expect($product->fresh()->stock)->toBe(10);
})->with([User::CASHIER, User::SUPERVISOR]);

test('product input rejects negative values and duplicate codes', function () {
    $product = posProduct();
    $this->actingAs(posUser(User::PRODUCT_MANAGER))->post('/products', ['code' => $product->code, 'name' => 'Invalid', 'price' => -1, 'stock' => -1])
        ->assertSessionHasErrors(['code', 'price', 'stock']);
    expect(Product::count())->toBe(1);
});

test('non cashiers cannot create or pay sales', function ($role) {
    $product = posProduct();
    $sale = pendingSale($this, posUser(), [['product_id' => $product->id, 'quantity' => 1]]);
    $this->actingAs(posUser($role))->get('/pos')->assertForbidden();
    $this->post('/sales', [])->assertForbidden();
    $this->post(route('sales.pay', $sale), ['payment' => 20000])->assertForbidden();
})->with([User::PRODUCT_MANAGER, User::SUPERVISOR]);

test('pending uses database prices and idempotent token without decreasing stock', function () {
    $cashier = posUser();
    $product = posProduct();
    $data = ['checkout_token' => (string) Str::uuid(), 'total' => 1, 'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1]]];
    $this->actingAs($cashier)->post('/sales', $data)->assertRedirect();
    $this->post('/sales', $data)->assertRedirect();
    expect(Sale::count())->toBe(1);
    $sale = Sale::firstOrFail();
    expect($sale->total)->toBe(24000)->and($sale->status)->toBe(Sale::PENDING)->and($sale->items()->count())->toBe(1)->and($product->fresh()->stock)->toBe(10);
});

test('empty duplicate negative missing and excessive items are rejected', function () {
    $product = posProduct();
    $this->actingAs(posUser());
    foreach ([[], [['product_id' => $product->id, 'quantity' => -1]], [['product_id' => $product->id, 'quantity' => 11]], [['product_id' => 999999, 'quantity' => 1]], [['product_id' => $product->id, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => 1]]] as $items) {
        $this->post('/sales', ['checkout_token' => (string) Str::uuid(), 'items' => $items])->assertSessionHasErrors();
    }
    expect(Sale::count())->toBe(0)->and($product->fresh()->stock)->toBe(10);
});

test('cashier cannot read pay or cancel another cashiers sale', function () {
    $product = posProduct();
    $owner = posUser();
    $sale = pendingSale($this, $owner, [['product_id' => $product->id, 'quantity' => 2]]);
    $this->actingAs(posUser())->get(route('sales.show', $sale))->assertForbidden();
    $this->post(route('sales.pay', $sale), ['payment' => 30000])->assertForbidden();
    $this->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Gagal bayar'])->assertForbidden();
    $this->get('/sales')->assertOk()->assertDontSee('#000001');
    $this->post('/sales', ['checkout_token' => $sale->checkout_token, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertForbidden();
    $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk();
    $this->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Gagal bayar'])->assertForbidden();
    $this->actingAs(posUser(User::PRODUCT_MANAGER))->get(route('sales.show', $sale))->assertForbidden();
});

test('payment rejects insufficient money then reduces stock exactly once', function () {
    $cashier = posUser();
    $product = posProduct();
    $sale = pendingSale($this, $cashier, [['product_id' => $product->id, 'quantity' => 2]]);
    $this->post(route('sales.pay', $sale), ['payment' => 20000])->assertSessionHasErrors('payment');
    expect($product->fresh()->stock)->toBe(10)->and($sale->fresh()->status)->toBe(Sale::PENDING);
    $this->post(route('sales.pay', $sale), ['payment' => 30000])->assertSessionHasNoErrors()->assertRedirect();
    expect($product->fresh()->stock)->toBe(8)->and($sale->fresh()->change)->toBe(6000)->and($sale->fresh()->status)->toBe(Sale::PAID);
    $this->post(route('sales.pay', $sale), ['payment' => 30000])->assertForbidden();
    expect($product->fresh()->stock)->toBe(8);
});

test('stock failure rolls back all items during payment', function () {
    $first = posProduct();
    $second = posProduct();
    $sale = pendingSale($this, posUser(), [['product_id' => $first->id, 'quantity' => 2], ['product_id' => $second->id, 'quantity' => 2]]);
    $second->update(['stock' => 0]);
    $this->post(route('sales.pay', $sale), ['payment' => 50000])->assertSessionHasErrors('payment');
    expect($first->fresh()->stock)->toBe(10)->and($second->fresh()->stock)->toBe(0)->and($sale->fresh()->status)->toBe(Sale::PENDING);
});

test('archived products cannot be paid and historical names prices remain unchanged', function () {
    $product = posProduct();
    $sale = pendingSale($this, posUser(), [['product_id' => $product->id, 'quantity' => 2]]);
    $product->update(['name' => 'Nama baru', 'price' => 99999]);
    $product->delete();
    $this->post(route('sales.pay', $sale), ['payment' => 30000])->assertSessionHasErrors('payment');
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Produk uji')->assertDontSee('Nama baru');
    expect($sale->items->first()->price)->toBe(12000)->and($sale->items->first()->product->id)->toBe($product->id);
});

test('pending cancellation requires reason and leaves stock unchanged', function () {
    $cashier = posUser();
    $product = posProduct();
    $sale = pendingSale($this, $cashier, [['product_id' => $product->id, 'quantity' => 2]]);
    $supervisor = posUser(User::SUPERVISOR);
    $this->actingAs($supervisor)->get('/sales')->assertOk()->assertSee('#000001');
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Batalkan transaksi');
    $this->post(route('sales.cancel', $sale), [])->assertSessionHasErrors('cancellation_reason');
    $this->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Pelanggan gagal membayar'])->assertSessionHasNoErrors()->assertRedirect();
    expect($sale->fresh()->status)->toBe(Sale::CANCELLED)->and($sale->fresh()->cancelled_by)->toBe($supervisor->id)->and($sale->fresh()->refund_amount)->toBe(0)->and($product->fresh()->stock)->toBe(10);
    $this->actingAs($cashier)->post(route('sales.pay', $sale), ['payment' => 30000])->assertForbidden();
});

test('paid cancellation restores archived stock once and records refund audit', function () {
    $product = posProduct();
    $sale = pendingSale($this, posUser(), [['product_id' => $product->id, 'quantity' => 2]]);
    $this->post(route('sales.pay', $sale), ['payment' => 30000])->assertSessionHasNoErrors();
    $product->delete();
    $supervisor = posUser(User::SUPERVISOR);
    $this->actingAs($supervisor)->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Pelanggan mengembalikan barang'])->assertSessionHasNoErrors()->assertRedirect();
    $sale->refresh();
    expect($product->fresh()->stock)->toBe(10)->and($sale->refund_amount)->toBe(24000)->and($sale->cancelled_by)->toBe($supervisor->id)->and($sale->paid_at)->not->toBeNull()->and($sale->cancelled_at)->not->toBeNull();
    $this->post(route('sales.cancel', $sale), ['cancellation_reason' => 'Pembatalan ulang'])->assertForbidden();
    expect($product->fresh()->stock)->toBe(10);
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Refund tunai');
});
