{{-- Satu partial dipakai daftar, katalog, dan kasir agar harga selalu tampil konsisten. --}}
@if($product->discount_percent > 0)
    <span class="block text-sm text-slate-400 line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
    <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Diskon {{ $product->discount_percent }}%</span>
@endif
<span class="mt-1 block font-semibold text-teal-700">Rp {{ number_format($product->finalPrice(), 0, ',', '.') }}</span>
