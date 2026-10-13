<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Membuka form; middleware guest membatasi ke pengguna yang belum login. */
    public function create(): View
    {
        return view('auth.login');
    }

    /** Memvalidasi kredensial lalu mengganti ID session untuk mencegah session fixation. */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Email atau password tidak cocok.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /** Mengarahkan pengguna ke halaman yang sesuai tugasnya. */
    public function home(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            User::CASHIER => redirect()->route('pos.index'),
            User::PRODUCT_MANAGER => redirect()->route('products.index'),
            User::SUPERVISOR => redirect()->route('sales.index'),
            default => abort(403),
        };
    }

    /** Menghapus session lama serta mengganti token CSRF saat logout. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
