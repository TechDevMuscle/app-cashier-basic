<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kasir Kita') · Kasir Kita</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="no-print border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 font-bold text-xl">
                <span class="grid size-11 place-items-center rounded-xl bg-teal-700 text-white">K</span> Kasir Kita
            </a>
            @auth
                <nav aria-label="Menu utama" class="flex flex-wrap items-center gap-2 text-sm">
                    {{-- @can hanya mengatur UI; controller tetap memeriksa izin server. --}}
                    @can('create', \App\Models\Sale::class)
                        <a class="nav-link {{ request()->routeIs('pos.*') ? 'nav-active' : '' }}" href="{{ route('pos.index') }}">Kasir</a>
                    @endcan
                    @can('manage', \App\Models\Product::class)
                        <a class="nav-link {{ request()->routeIs('products.*') ? 'nav-active' : '' }}" href="{{ route('products.index') }}">Kelola Produk</a>
                    @endcan
                    <a class="nav-link {{ request()->routeIs('catalog') ? 'nav-active' : '' }}" href="{{ route('catalog') }}">Katalog</a>
                    @can('viewAny', \App\Models\Sale::class)
                        <a class="nav-link {{ request()->routeIs('sales.*') ? 'nav-active' : '' }}" href="{{ route('sales.index') }}">Transaksi</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-secondary">Keluar</button></form>
                </nav>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        @auth
            <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500">
                <span>{{ auth()->user()->name }}</span>
                <span class="rounded-full bg-slate-200 px-3 py-1 font-medium text-slate-700">{{ ['cashier' => 'Kasir', 'product_manager' => 'Pengelola Produk', 'supervisor' => 'Supervisor'][auth()->user()->role] ?? 'Pengguna' }}</span>
            </div>
        @endauth
        @if(session('success'))
            <div role="status" class="no-print mb-6 rounded-xl border border-teal-200 bg-teal-50 p-4 text-teal-900">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="no-print mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
                <p class="font-semibold">Periksa kembali input berikut:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
