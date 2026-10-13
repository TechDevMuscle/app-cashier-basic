<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** Semua role terdaftar boleh membaca katalog tanpa hak mengubah data. */
    public function catalog(): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('products.catalog', ['products' => Product::orderBy('name')->paginate(12)]);
    }

    /** READ: pencarian dan pagination membatasi jumlah data yang ditampilkan. */
    public function index(Request $request): View
    {
        Gate::authorize('manage', Product::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';
        $products = Product::query()->when($search !== '', function ($query) use ($search): void {
            // Grup kondisi OR supaya pencarian tetap aman jika nanti ditambah filter lain.
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            });
        })->orderBy('name')->paginate(12)->withQueryString();

        return view('products.index', compact('products', 'search'));
    }

    /** Membuka form kosong untuk penambahan produk. */
    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('products.form', ['product' => new Product]);
    }

    /** CREATE: hanya data yang lolos validasi diberikan ke Eloquent. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);
        Product::create($this->validatedData($request));

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    /** Route model binding mengambil produk berdasarkan ID pada URL. */
    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('products.form', compact('product'));
    }

    /** UPDATE: lock mencegah penulisan stok bersamaan dengan pembayaran. */
    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        $data = $this->validatedData($request, $product);
        DB::transaction(function () use ($product, $data): void {
            Product::lockForUpdate()->findOrFail($product->id)->update($data);
        }, 5);

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    /** DELETE: SoftDeletes mengisi deleted_at sehingga relasi transaksi tetap tersedia. */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);
        DB::transaction(function () use ($product): void {
            Product::lockForUpdate()->findOrFail($product->id)->delete();
        }, 5);

        return redirect()->route('products.index')->with('success', 'Produk diarsipkan dan tidak bisa dijual lagi.');
    }

    /** Satu set aturan untuk tambah/edit; kode arsip tetap tidak boleh digunakan ulang. */
    private function validatedData(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'discount_percent' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
        ]);

        // Request versi lama boleh tanpa field diskon: create memakai 0%, edit menjaga nilai lama.
        $data['discount_percent'] ??= $product?->discount_percent ?? 0;

        return $data;
    }
}
