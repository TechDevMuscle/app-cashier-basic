@extends('layouts.app')
@section('title', 'Kasir')
@section('content')
<div data-pos>
    <div class="mb-6"><p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Penjualan baru</p><h1 class="mt-2 text-3xl font-bold">Pilih produk, mulai belanja.</h1><p class="mt-2 text-slate-500">Sentuh kartu untuk menambahkan produk ke keranjang.</p></div>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <section aria-label="Pilihan produk">
            <label for="product-search" class="sr-only">Cari produk</label><input id="product-search" class="field mb-5" type="search" placeholder="Cari produk atau kode…" data-product-search>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach($products as $product)
                    <button type="button" data-product data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-code="{{ $product->code }}" data-price="{{ $product->price }}" data-final-price="{{ $product->finalPrice() }}" data-discount-percent="{{ $product->discount_percent }}" data-discount-amount="{{ $product->discountAmount() }}" data-stock="{{ $product->stock }}" class="product-card" @disabled($product->stock < 1)>
                        <span class="mb-4 grid size-12 place-items-center rounded-xl bg-teal-50 text-lg font-bold text-teal-700">{{ mb_substr($product->name, 0, 1) }}</span>
                        <span class="text-xs text-slate-500">{{ $product->code }}</span><span class="mt-1 block text-lg font-bold">{{ $product->name }}</span>
                        <span class="mt-4 block">@include('products.price')</span><span class="mt-1 block text-sm text-slate-500">{{ $product->stock > 0 ? 'Stok: '.$product->stock : 'Stok habis' }}</span>
                    </button>
                @endforeach
            </div>
            <p class="mt-5 text-slate-500" data-no-products hidden>Tidak ada produk yang cocok.</p>
            <noscript><p class="mt-4 rounded-xl bg-amber-50 p-4">Aktifkan JavaScript untuk menggunakan keranjang touchscreen.</p></noscript>
        </section>
        <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-6">
            <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Keranjang</h2><button class="min-h-12 px-3 text-sm font-semibold text-red-700" type="button" data-clear-cart>Kosongkan</button></div>
            <p class="my-8 text-center text-slate-400" data-cart-empty>Keranjang masih kosong.</p>
            <div class="mt-4 space-y-3" data-cart-lines></div>
            <p class="mt-3 text-sm text-amber-800" role="status" aria-live="polite" data-cart-message></p>
            <div class="mt-6 space-y-3 border-t border-slate-200 pt-5">
                <div data-cart-discount-summary hidden>
                    <div class="flex justify-between text-sm text-slate-500"><span>Sebelum diskon</span><span data-cart-original>Rp 0</span></div>
                    <div class="mt-2 flex justify-between text-sm font-semibold text-teal-700"><span>Hemat diskon</span><span data-cart-discount>Rp 0</span></div>
                </div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Total sementara</span><strong class="text-xl" data-cart-total>Rp 0</strong></div>
            </div>
            <form class="mt-5" method="POST" action="{{ route('sales.store') }}" data-checkout>
                @csrf
                <input type="hidden" name="checkout_token" value="{{ old('checkout_token', $checkoutToken) }}">
                <div data-cart-inputs></div>
                <button class="btn-primary w-full" disabled data-checkout-button>Simpan & lanjut bayar</button>
            </form>
            <p class="mt-3 text-xs leading-relaxed text-slate-500">Harga akhir dihitung server. Stok baru berkurang setelah pembayaran berhasil.</p>
        </aside>
    </div>
    {{-- JSON aman untuk pemulihan keranjang setelah validasi gagal; tanpa library JS. --}}
    <script type="application/json" data-old-items>@json(old('items', []))</script>
    <template data-cart-template>
        <div class="rounded-xl bg-slate-50 p-3">
            <div class="flex items-start justify-between gap-3"><strong class="text-sm" data-line-name></strong><button type="button" class="min-h-12 px-2 text-sm text-red-700" data-line-remove>Hapus</button></div>
            <p class="text-xs text-slate-500" data-line-price></p>
            <div class="mt-2 flex items-center justify-between"><div class="flex items-center gap-3"><button type="button" class="btn-quantity" data-line-minus>−</button><span class="min-w-5 text-center font-semibold" data-line-quantity></span><button type="button" class="btn-quantity" data-line-plus>+</button></div><span class="text-sm font-semibold" data-line-subtotal></span></div>
        </div>
    </template>
</div>
@endsection
