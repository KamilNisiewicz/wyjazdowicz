<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Statystyki') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                @if ($stats === null)
                    <p class="text-gray-600">
                        {{ __('Nie masz jeszcze żadnych zapisanych meczów.') }}
                        <a href="{{ route('matches.create') }}" class="link link-primary">
                            {{ __('Dodaj swój pierwszy mecz') }}
                        </a>
                    </p>
                @else
                    <div x-data="{ tab: 'overall' }">
                        <div role="tablist" class="tabs tabs-bordered mb-6">
                            <button
                                type="button"
                                role="tab"
                                @click="tab = 'overall'"
                                :class="tab === 'overall' ? 'tab-active' : ''"
                                class="tab"
                            >{{ __('Ogółem') }}</button>
                            <button
                                type="button"
                                role="tab"
                                @click="tab = 'home'"
                                :class="tab === 'home' ? 'tab-active' : ''"
                                class="tab"
                            >{{ __('Dom') }}</button>
                            <button
                                type="button"
                                role="tab"
                                @click="tab = 'away'"
                                :class="tab === 'away' ? 'tab-active' : ''"
                                class="tab"
                            >{{ __('Wyjazd') }}</button>
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
    </div>
</x-app-layout>
