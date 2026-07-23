<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-gray-100 flex flex-col">

        <header class="max-w-5xl w-full mx-auto px-6 py-8 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-indigo-600 text-white">
                    <x-application-logo class="w-5 h-5 fill-current" />
                </span>
                <span class="font-semibold text-gray-800 text-lg">{{ config('app.name') }}</span>
            </div>

            <nav class="flex items-center gap-4 text-sm font-medium">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-indigo-600 transition">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-indigo-600 transition">{{ __('Masuk') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition">{{ __('Daftar') }}</a>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="flex-1 flex items-center">
            <div class="max-w-5xl w-full mx-auto px-6 py-16 text-center">
                <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-200 mb-6">
                    <x-application-logo class="w-8 h-8 fill-current" />
                </span>

                <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 tracking-tight">
                    {{ config('app.name') }}
                </h1>
                <p class="mt-4 text-gray-600 max-w-xl mx-auto">
                    {{ __('Cari dan kelola data manifest penumpang penerbangan dengan cepat — berdasarkan nama, maskapai, atau tanggal.') }}
                </p>

                <div class="mt-8 flex items-center justify-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="px-6 py-3 rounded-md bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">
                            {{ __('Buka Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-6 py-3 rounded-md bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">
                            {{ __('Masuk') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-6 py-3 rounded-md bg-white text-gray-700 font-semibold border border-gray-300 hover:bg-gray-50 transition">
                                {{ __('Daftar Akun') }}
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </main>

        <footer class="max-w-5xl w-full mx-auto px-6 py-8 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </footer>
    </div>
</body>
</html>
