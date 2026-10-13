@extends('layouts.app')
@section('title', 'Kelola Produk')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><h1 class="text-3xl font-bold">Kelola Produk</h1><p class="mt-2 text-slate-500">Atur katalog, harga, dan stok toko.</p></div>
    @can('create', \App\Models\Product::class)<a class="btn-primary" href="{{ route('products.create') }}">+ Tambah produk</a>@endcan
</div>
<form class="mb-6 flex gap-2" method="GET" action="{{ route('products.index') }}">
    <label class="sr-only" for="search">Cari produk</label><input id="search" name="search" value="{{ $search }}" class="field max-w-md" placeholder="Cari nama atau kode produk"><button class="btn-secondary">Cari</button>
</form>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left"><thead class="bg-slate-100 text-sm text-slate-600"><tr><th class="p-4">Produk</th><th class="p-4">Harga</th><th class="p-4">Stok</th><th class="p-4">Aksi</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($products as $product)
        <tr><td class="p-4"><div class="font-semibold">{{ $product->name }}</div><div class="text-sm text-slate-500">{{ $product->code }}</div></td><td class="whitespace-nowrap p-4">@include('products.price')</td><td class="p-4">{{ $product->stock }}</td><td class="p-4"><div class="flex flex-wrap gap-2">
            @can('update', $product)<a class="btn-secondary" href="{{ route('products.edit', $product) }}">Edit</a>@endcan
            @can('delete', $product)<form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Arsipkan produk ini? Produk tidak akan muncul di kasir.">@csrf @method('DELETE')<button class="btn-danger">Arsipkan</button></form>@endcan
        </div></td></tr>
        @empty<tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada produk yang cocok.</td></tr>@endforelse
    </tbody></table>
</div>
<div class="mt-6">{{ $products->links() }}</div>
@endsection
