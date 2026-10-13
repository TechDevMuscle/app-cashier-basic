@extends('layouts.app')
@section('title', 'Transaksi #'.$sale->id)
@section('content')
<div class="mx-auto max-w-3xl">
    <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3"><a class="btn-secondary" href="{{ route('sales.index') }}">← Riwayat</a>@if($sale->paid_at)<button type="button" class="btn-secondary" data-print>Cetak struk</button>@endif</div>
    <article class="receipt rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold text-teal-700">KASIR KITA</p><h1 class="mt-2 text-2xl font-bold">Transaksi #{{ str_pad($sale->id, 6, '0', STR_PAD_LEFT) }}</h1></div><span class="status-badge status-{{ $sale->status }}">{{ $sale->statusLabel() }}</span></div>
        <p class="mt-4 text-sm text-slate-500">Kasir: {{ $sale->user->name }}<br>Dibuat: {{ $sale->created_at->format('d/m/Y H:i') }} WIB @if($sale->paid_at)<br>Dibayar: {{ $sale->paid_at->format('d/m/Y H:i') }} WIB @endif</p>
        <div class="my-6 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-y border-slate-200"><tr><th class="py-3">Produk</th><th class="py-3 text-center">Qty</th><th class="py-3 text-right">Subtotal</th></tr></thead><tbody>
        @foreach($sale->items as $item)
            <tr>
                <td class="py-4 pr-3">
                    <strong>{{ $item->product_name }}</strong>
                    {{-- Struk membaca snapshot sale_items, bukan harga/diskon produk saat ini. --}}
                    @if($item->discount_percent > 0)
                        <div class="text-xs text-slate-400 line-through">Rp {{ number_format($item->original_price, 0, ',', '.') }} / item</div>
                        <div class="text-xs text-teal-700">Diskon {{ $item->discount_percent }}%: −Rp {{ number_format($item->discount_amount, 0, ',', '.') }} / item</div>
                    @endif
                    <div class="text-xs text-slate-500">Rp {{ number_format($item->price, 0, ',', '.') }} / item</div>
                </td>
                <td class="py-4 text-center">{{ $item->quantity }}</td>
                <td class="whitespace-nowrap py-4 text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody></table></div>
        <div class="space-y-3 border-t border-slate-200 pt-5">
            @if($sale->discount_total > 0)
                <div class="flex justify-between text-sm"><span>Sebelum diskon</span><span>Rp {{ number_format($sale->original_total, 0, ',', '.') }}</span></div>
                <div class="flex justify-between text-sm text-teal-700"><span>Total diskon</span><span>−Rp {{ number_format($sale->discount_total, 0, ',', '.') }}</span></div>
            @endif
            <div class="flex justify-between text-xl font-bold"><span>Total</span><span>Rp {{ number_format($sale->total, 0, ',', '.') }}</span></div>
            @if($sale->paid_at)
                <div class="flex justify-between text-sm"><span>Tunai</span><span>Rp {{ number_format($sale->payment, 0, ',', '.') }}</span></div>
                <div class="flex justify-between text-sm"><span>Kembalian</span><span>Rp {{ number_format($sale->change, 0, ',', '.') }}</span></div>
            @endif
        </div>
        @if($sale->status === 'cancelled')
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><strong>Transaksi dibatalkan</strong><p class="mt-2">Oleh: {{ $sale->cancelledBy->name }} · {{ $sale->cancelled_at->format('d/m/Y H:i') }} WIB</p><p class="mt-2 whitespace-pre-line">Alasan: {{ $sale->cancellation_reason }}</p><p class="mt-3 font-bold">Refund tunai: Rp {{ number_format($sale->refund_amount, 0, ',', '.') }}</p><p class="mt-1">{{ $sale->paid_at ? 'Stok dikembalikan. Pengembalian uang dilakukan oleh petugas.' : 'Belum dibayar. Stok tidak berubah.' }}</p></div>
        @endif
    </article>
    @can('pay', $sale)
        <form class="no-print mt-6 rounded-2xl border border-teal-200 bg-teal-50 p-6" method="POST" action="{{ route('sales.pay', $sale) }}" data-payment-form data-total="{{ $sale->total }}">
            @csrf
            <h2 class="text-xl font-bold">{{ $sale->total === 0 ? 'Selesaikan transaksi gratis' : 'Pembayaran tunai' }}</h2>
            @if($sale->total === 0)
                <p class="mt-2 text-sm text-slate-600">Total Rp 0. Tetap selesaikan transaksi agar stok berkurang dan riwayat tercatat.</p>
            @endif
            <label for="payment" class="field-label mt-4">Uang diterima (Rp)</label>
            <input id="payment" name="payment" class="field" type="number" inputmode="numeric" min="{{ $sale->total }}" max="1000000000000000" step="1" value="{{ old('payment', $sale->total === 0 ? 0 : null) }}" required data-payment>
            <div class="mt-3 flex flex-wrap gap-2">
                <button class="btn-secondary" type="button" data-cash="{{ $sale->total }}">Uang pas</button>
                @foreach([20000, 50000, 100000] as $cash)
                    @if($cash >= $sale->total)
                        <button class="btn-secondary" type="button" data-cash="{{ $cash }}">{{ number_format($cash, 0, ',', '.') }}</button>
                    @endif
                @endforeach
            </div>
            <p class="my-4 font-semibold" aria-live="polite" data-change>Kembalian: Rp 0</p><button class="btn-primary w-full">{{ $sale->total === 0 ? 'Selesaikan transaksi gratis' : 'Selesaikan pembayaran' }}</button>
            <p class="mt-3 text-sm text-slate-600">Pelanggan batal atau gagal membayar? Hubungi supervisor dengan nomor transaksi di atas.</p>
        </form>
    @endcan
    @can('cancel', $sale)
        <form class="no-print mt-6 rounded-2xl border border-red-200 bg-white p-6" method="POST" action="{{ route('sales.cancel', $sale) }}" data-confirm="Batalkan seluruh transaksi ini? Pastikan barang belum dibawa atau sudah dikembalikan.">
            @csrf<h2 class="text-xl font-bold text-red-800">Batalkan transaksi</h2><p class="mt-2 text-sm text-slate-500">{{ $sale->paid_at ? 'Stok akan dikembalikan. Refund tunai sebesar Rp '.number_format($sale->total, 0, ',', '.').' harus diserahkan kepada pelanggan.' : 'Belum ada pembayaran; stok tidak berubah.' }}</p>
            <label for="cancellation_reason" class="field-label mt-4">Alasan pembatalan</label><textarea id="cancellation_reason" class="field" name="cancellation_reason" minlength="5" maxlength="1000" rows="3" required placeholder="Contoh: pelanggan gagal membayar">{{ old('cancellation_reason') }}</textarea><button class="btn-danger mt-4">Batalkan seluruh transaksi</button>
        </form>
    @endcan
</div>
@endsection
