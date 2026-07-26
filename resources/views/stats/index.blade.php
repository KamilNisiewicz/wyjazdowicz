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
                    @php
                        $streakLetter = ['win' => 'W', 'draw' => 'R', 'loss' => 'P'][$stats['streak_result']];
                        $maxCount = max($stats['wins'], $stats['draws'], $stats['losses'], 1);
                        $bars = [
                            ['label' => __('Wygrane'), 'count' => $stats['wins'], 'color' => '#0ca30c'],
                            ['label' => __('Remisy'), 'count' => $stats['draws'], 'color' => '#898781'],
                            ['label' => __('Porażki'), 'count' => $stats['losses'], 'color' => '#d03b3b'],
                        ];
                    @endphp

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                        <div class="border rounded-lg p-4 text-center">
                            <div class="text-2xl font-semibold text-gray-900">{{ $stats['win_percentage'] }}%</div>
                            <div class="text-sm text-gray-500 mt-1">{{ __('% zwycięstw') }}</div>
                        </div>
                        <div class="border rounded-lg p-4 text-center">
                            <div class="text-2xl font-semibold text-gray-900">{{ $stats['streak_length'] }}× {{ $streakLetter }}</div>
                            <div class="text-sm text-gray-500 mt-1">{{ __('Aktualna passa') }}</div>
                        </div>
                        <div class="border rounded-lg p-4 text-center">
                            <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_distance_km'] }} km</div>
                            <div class="text-sm text-gray-500 mt-1">{{ __('Łączny dystans') }}</div>
                        </div>
                        @if ($stats['is_unlucky_fan'])
                            <div class="border border-red-200 rounded-lg p-4 text-center bg-red-50">
                                <div class="text-2xl font-semibold text-red-700">⚠</div>
                                <div class="text-sm text-red-700 mt-1">{{ __('Pechowy kibic') }}</div>
                            </div>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-3">{{ __('Bilans') }}</h3>
                        <div class="flex items-end gap-6" style="height: 104px;">
                            @foreach ($bars as $bar)
                                <div class="flex flex-col items-center gap-1" title="{{ $bar['label'] }}: {{ $bar['count'] }}">
                                    <span class="text-sm font-semibold text-gray-900">{{ $bar['count'] }}</span>
                                    <div
                                        class="w-6 rounded-t"
                                        style="height: {{ max((int) round($bar['count'] / $maxCount * 80), 4) }}px; background-color: {{ $bar['color'] }};"
                                    ></div>
                                    <span class="text-xs text-gray-500">{{ $bar['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
