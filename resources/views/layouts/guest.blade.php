<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="wyjazdowicz">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Wyjazdowicz') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="alternate icon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-base-content antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-base-200">
            <div>
                <a href="/">
                    <x-application-logo class="text-3xl" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
