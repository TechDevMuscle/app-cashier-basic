@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="mx-auto mt-8 max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Selamat datang</p>
    <h1 class="mt-2 text-3xl font-bold">Masuk ke kasir</h1>
    <p class="mt-3 text-slate-500">Gunakan akun sesuai tugas Anda.</p>
    <form class="mt-8 space-y-5" action="{{ route('login.store') }}" method="POST">
        {{-- CSRF memastikan form dikirim dari session aplikasi ini. --}}
        @csrf
        <div><label for="email" class="field-label">Email</label><input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></div>
        <div><label for="password" class="field-label">Password</label><input id="password" class="field" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn-primary w-full" type="submit">Masuk</button>
    </form>
</div>
@endsection
