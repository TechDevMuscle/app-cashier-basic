@extends('layouts.app')
@section('title', 'Transaksi')
@section('content')
<h1 class="text-3xl font-bold">Riwayat Transaksi</h1><p class="mt-2 text-slate-500">{{ auth()->user()->hasRole('supervisor') ? 'Semua transaksi toko. Pilih transaksi untuk memeriksa atau membatalkan.' : 'Transaksi Anda. Buka transaksi pending untuk melanjutkan pembayaran.' }}</p>
<form method="GET" class="my-6 flex flex-wrap items-end gap-3">
    <div><label for="status" class="field-label">Status</label><select id="status" name="status" class="field"><option value="">Semua status</option>@foreach(['pending' => 'Menunggu pembayaran', 'paid' => 'Dibayar', 'cancelled' => 'Dibatalkan'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div><button class="btn-secondary">Terapkan</button>
</form>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"><table class="w-full text-left"><thead class="bg-slate-100 text-sm text-slate-600"><tr><th class="p-4">Transaksi</th><th class="p-4">Kasir</th><th class="p-4">Total</th><th class="p-4">Status</th><th class="p-4">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($sales as $sale)
<tr><td class="p-4"><strong>#{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</strong><div class="text-xs text-slate-500">{{ $sale->created_at->format('d/m/Y H:i') }}</div></td><td class="p-4">{{ $sale->user->name }}</td><td class="whitespace-nowrap p-4">Rp {{ number_format($sale->total, 0, ',', '.') }}</td><td class="p-4"><span class="status-badge status-{{ $sale->status }}">{{ $sale->statusLabel() }}</span></td><td class="p-4"><a class="btn-secondary" href="{{ route('sales.show', $sale) }}">Detail</a></td></tr>
@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada transaksi yang cocok.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $sales->links() }}</div>
@endsection
