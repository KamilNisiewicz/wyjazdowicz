<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Mecze') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'match-created')
                <div class="p-4 bg-green-50 text-green-700 rounded-lg">
                    {{ __('Mecz zapisany.') }}
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Twoje mecze') }}</h3>
                    <a href="{{ route('matches.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                        {{ __('Dodaj mecz') }}
                    </a>
                </div>

                @if ($matches->isEmpty())
                    <p class="text-gray-600">{{ __('Nie masz jeszcze żadnych zapisanych meczów. Dodaj pierwszy, żeby zacząć śledzić swoje wyjazdy.') }}</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-gray-500">
                                <th class="py-2 pr-4">{{ __('Data') }}</th>
                                <th class="py-2 pr-4">{{ __('Przeciwnik') }}</th>
                                <th class="py-2 pr-4">{{ __('Dom/Wyjazd') }}</th>
                                <th class="py-2 pr-4">{{ __('Wynik') }}</th>
                                <th class="py-2 pr-4">{{ __('Dystans') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matches as $match)
                                <tr class="border-b">
                                    <td class="py-2 pr-4">{{ $match->played_on->format('Y-m-d') }}</td>
                                    <td class="py-2 pr-4">{{ $match->opponent }}</td>
                                    <td class="py-2 pr-4">{{ $match->venue === 'home' ? __('Dom') : __('Wyjazd') }}</td>
                                    <td class="py-2 pr-4">{{ $match->goals_for }}:{{ $match->goals_against }}</td>
                                    <td class="py-2 pr-4">{{ $match->distance_km !== null ? $match->distance_km.' km' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
