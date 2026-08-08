<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="wyjazdowicz">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Wyjazdowicz') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="alternate icon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-base-200">
        <div class="min-h-screen flex flex-col items-center justify-center px-6 py-12 text-center">
            <x-application-logo class="text-4xl sm:text-5xl mb-4" />

            <p class="max-w-md text-base-content/70 text-lg mb-8">
                {{ __('Śledź swoje mecze wyjazdowe — wyniki, dystans i statystyki drużyny w jednym miejscu.') }}
            </p>

            <div class="flex flex-col sm:flex-row gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-wide">
                        {{ __('Przejdź do panelu') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-wide">
                        {{ __('Zaloguj się') }}
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-outline btn-wide">
                            {{ __('Zarejestruj się') }}
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </body>
</html>
