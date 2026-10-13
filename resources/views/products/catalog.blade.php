@extends('layouts.app')
@section('title', 'Katalog')
@section('content')
<h1 class="text-3xl font-bold">Katalog Produk</h1><p class="mb-6 mt-2 text-slate-500">Produk aktif dan stok terkini.</p>
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
@forelse($products as $product)
    <article class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs font-medium text-teal-700">{{ $product->code }}</p><h2 class="mt-3 text-lg font-bold">{{ $product->name }}</h2><div class="mt-4">@include('products.price')</div><p class="mt-2 text-sm text-slate-500">Stok: {{ $product->stock }}</p></article>
@empty<p class="col-span-full rounded-xl bg-white p-8 text-center text-slate-500">Belum ada produk aktif.</p>@endforelse
</div><div class="mt-6">{{ $products->links() }}</div>
@endsection
