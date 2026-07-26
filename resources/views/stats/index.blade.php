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
                    <div x-data="{ tab: 'overall' }">
                        <div class="border-b border-gray-200 mb-6">
                            <nav class="-mb-px flex gap-6">
                                <button
                                    type="button"
                                    @click="tab = 'overall'"
                                    :class="tab === 'overall' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="border-b-2 py-2 px-1 text-sm font-medium"
                                >{{ __('Ogółem') }}</button>
                                <button
                                    type="button"
                                    @click="tab = 'home'"
                                    :class="tab === 'home' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="border-b-2 py-2 px-1 text-sm font-medium"
                                >{{ __('Dom') }}</button>
                                <button
                                    type="button"
                                    @click="tab = 'away'"
                                    :class="tab === 'away' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="border-b-2 py-2 px-1 text-sm font-medium"
                                >{{ __('Wyjazd') }}</button>
                            </nav>
                        </div>

                        <div x-show="tab === 'overall'">
                            @include('stats.partials.stats-block', ['stats' => $stats, 'showDistance' => true, 'emptyMessage' => ''])
                        </div>
                        <div x-show="tab === 'home'" style="display: none;">
                            @include('stats.partials.stats-block', ['stats' => $homeStats, 'showDistance' => false, 'emptyMessage' => __('Brak zapisanych meczów domowych.')])
                        </div>
                        <div x-show="tab === 'away'" style="display: none;">
                            @include('stats.partials.stats-block', ['stats' => $awayStats, 'showDistance' => true, 'emptyMessage' => __('Brak zapisanych meczów wyjazdowych.')])
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
