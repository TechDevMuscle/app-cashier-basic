@extends('layouts.app')
@section('title', $product->exists ? 'Edit Produk' : 'Tambah Produk')
@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="mb-6 text-3xl font-bold">{{ $product->exists ? 'Edit Produk' : 'Tambah Produk' }}</h1>
    <form class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6" method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" data-discount-form>
        @csrf
        {{-- HTML hanya mendukung GET/POST; @method memberi Laravel method PUT untuk update. --}}
        @if($product->exists) @method('PUT') @endif
        <div><label for="code" class="field-label">Kode produk</label><input id="code" class="field" name="code" maxlength="30" value="{{ old('code', $product->code) }}" required></div>
        <div><label for="name" class="field-label">Nama produk</label><input id="name" class="field" name="name" maxlength="120" value="{{ old('name', $product->name) }}" required></div>
        <div><label for="price" class="field-label">Harga normal (Rp)</label><input id="price" class="field" name="price" type="number" inputmode="numeric" min="1" max="1000000000" step="1" value="{{ old('price', $product->price) }}" required data-original-price></div>
        {{-- Diskon hanya dapat disimpan oleh pengelola produk; policy tetap diperiksa server. --}}
        <div>
            <label for="discount_percent" class="field-label">Diskon (%)</label>
            <input id="discount_percent" class="field" name="discount_percent" type="number" inputmode="numeric" min="0" max="100" step="1" value="{{ old('discount_percent', $product->discount_percent) }}" required data-discount-percent>
            <p class="mt-2 text-sm text-slate-500">Isi 0–100, tanpa pecahan. Nilai 0 berarti tanpa diskon.</p>
        </div>
        <div class="rounded-xl bg-teal-50 p-4 text-sm" aria-live="polite">
            <p data-discount-preview>Isi harga normal untuk melihat harga setelah diskon.</p>
            <p class="mt-2 text-slate-500">Potongan dibulatkan ke bawah per unit ke Rupiah bulat.</p>
        </div>
        <div><label for="stock" class="field-label">Stok tersedia</label><input id="stock" class="field" name="stock" type="number" inputmode="numeric" min="0" max="1000000" step="1" value="{{ old('stock', $product->stock ?? 0) }}" required><p class="mt-2 text-sm text-slate-500">Isi jumlah stok akhir hasil pengecekan fisik.</p></div>
        <div class="flex gap-3"><button class="btn-primary">Simpan produk</button><a class="btn-secondary" href="{{ route('products.index') }}">Kembali</a></div>
    </form>
</div>
@endsection
