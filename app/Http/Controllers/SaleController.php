<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    /** Mengambil katalog aktif dan token unik untuk satu keranjang touchscreen. */
    public function pos(): View
    {
        Gate::authorize('create', Sale::class);

        return view('pos.index', ['products' => Product::orderBy('name')->get(), 'checkoutToken' => (string) Str::uuid()]);
    }

    /** Filter pemilik diterapkan di database; with('user') menghindari query per baris. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sale::class);
        $filters = $request->validate(['status' => ['nullable', Rule::in([Sale::PENDING, Sale::PAID, Sale::CANCELLED])]]);
        $sales = Sale::with('user')
            ->when($request->user()->hasRole(User::CASHIER), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(15)->withQueryString();

        return view('sales.index', compact('sales'));
    }

    /** Menyimpan pending dari ID/jumlah; harga dan total browser tidak pernah dipercaya. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Sale::class);
        $data = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);
        $sale = DB::transaction(function () use ($request, $data): Sale {
            // Lock pengguna menserialisasi double submit keranjang milik kasir yang sama.
            User::lockForUpdate()->findOrFail($request->user()->id);
            $existing = Sale::where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                Gate::authorize('view', $existing);

                return $existing;
            }
            $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $items = [];
            $total = 0;
            $originalTotal = 0;
            $discountTotal = 0;
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Produk tidak tersedia atau stok tidak cukup. Muat ulang katalog.']);
                }
                // Snapshot saat pending: perubahan diskon katalog tidak mengubah kesepakatan belanja.
                $discount = $product->discountAmount();
                $price = $product->finalPrice();
                $subtotal = $price * $item['quantity'];
                $items[] = [
                    'product_id' => $product->id, 'product_name' => $product->name,
                    'original_price' => $product->price, 'discount_percent' => $product->discount_percent,
                    'discount_amount' => $discount, 'price' => $price,
                    'quantity' => $item['quantity'], 'subtotal' => $subtotal,
                ];
                $originalTotal += $product->price * $item['quantity'];
                // Dibulatkan per unit terlebih dahulu, baru dikalikan jumlah barang.
                $discountTotal += $discount * $item['quantity'];
                $total += $subtotal;
            }
            $sale = $request->user()->sales()->create([
                'checkout_token' => $data['checkout_token'], 'status' => Sale::PENDING,
                'original_total' => $originalTotal, 'discount_total' => $discountTotal, 'total' => $total,
            ]);
            // Relasi otomatis mengisi sale_id. Pending belum mengurangi atau mereservasi stok.
            $sale->items()->createMany($items);

            return $sale;
        }, 5);

        return redirect()->route('sales.show', $sale)->with('success', 'Transaksi tersimpan. Lanjutkan pembayaran atau hubungi supervisor untuk membatalkan.');
    }

    /** Memeriksa kepemilikan lalu memuat detail/struk beserta identitas pembatal. */
    public function show(Sale $sale): View
    {
        Gate::authorize('view', $sale);
        $sale->load(['items', 'user', 'cancelledBy']);

        return view('sales.show', compact('sale'));
    }

    /** Pembayaran atomik: cek status terbaru dan stok, lalu simpan uang/kembalian. */
    public function pay(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('pay', $sale);
        // Nol valid untuk transaksi gratis (diskon 100%); pemeriksaan total tetap dilakukan setelah lock.
        $data = $request->validate(['payment' => ['required', 'integer', 'min:0', 'max:1000000000000000']]);
        DB::transaction(function () use ($sale, $data): void {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            // Periksa ulang setelah lock karena status mungkin berubah sejak binding URL.
            Gate::authorize('pay', $sale);
            if ($data['payment'] < $sale->total) {
                throw ValidationException::withMessages(['payment' => 'Uang pembayaran kurang dari total belanja.']);
            }
            $items = $sale->items()->orderBy('product_id')->get();
            $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                if (! $product || $product->stock < $item->quantity) {
                    throw ValidationException::withMessages(['payment' => 'Stok '.$item->product_name.' tidak cukup atau produk sudah diarsipkan. Hubungi supervisor jika perlu pembatalan.']);
                }
                $product->decrement('stock', $item->quantity);
            }
            $sale->update(['status' => Sale::PAID, 'payment' => $data['payment'], 'change' => $data['payment'] - $sale->total, 'paid_at' => now()]);
            // Exception me-rollback semua perubahan, termasuk stok yang sudah berkurang.
        }, 5);

        return redirect()->route('sales.show', $sale)->with('success', 'Pembayaran berhasil. Struk siap dicetak.');
    }

    /** Pembatalan dengan audit; stok transaksi paid dikembalikan tepat satu kali. */
    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('cancel', $sale);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($request, $sale, $data): void {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            Gate::authorize('cancel', $sale);
            $wasPaid = $sale->status === Sale::PAID;
            if ($wasPaid) {
                $items = $sale->items()->orderBy('product_id')->get();
                $products = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($items as $item) {
                    // Produk arsip juga menerima pengembalian stok.
                    $products->get($item->product_id)->increment('stock', $item->quantity);
                }
            }
            $sale->update([
                'status' => Sale::CANCELLED, 'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(), 'cancellation_reason' => $data['cancellation_reason'],
                // Kembalian sudah diberikan; refund memakai total net setelah diskon, bukan harga normal.
                'refund_amount' => $wasPaid ? $sale->total : 0,
            ]);
        }, 5);

        return redirect()->route('sales.show', $sale)->with('success', 'Transaksi dibatalkan. Jika sudah dibayar, serahkan refund tunai sesuai nominal tercatat.');
    }
}
