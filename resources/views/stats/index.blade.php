<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Statystyki') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                @if ($stats === null)
                    <p class="text-gray-600">
                        {{ __('Nie masz jeszcze żadnych zapisanych meczów.') }}
                        <a href="{{ route('matches.create') }}" class="text-indigo-600 underline">
                            {{ __('Dodaj swój pierwszy mecz') }}
                        </a>
                    </p>
                @else
                    {{-- Tymczasowy szkic z Fazy 1 — prawdziwy panel z kafelkami i wykresem W/D/L przychodzi w Fazie 2. --}}
                    <pre>{{ print_r($stats, true) }}</pre>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
