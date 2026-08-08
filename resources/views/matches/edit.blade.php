<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edytuj mecz') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body max-w-xl">
                    <p class="text-sm text-gray-500 mb-6">
                        <span class="badge badge-outline {{ $match->venue === 'home' ? 'badge-primary' : 'badge-accent' }}">{{ $match->venue === 'home' ? __('Dom') : __('Wyjazd') }}</span> · {{ $match->city }}
                        <span class="block text-xs mt-1">{{ __('Dom/wyjazd i miejscowość nie można zmienić — usuń mecz i dodaj go od nowa, jeśli to pole jest błędne.') }}</span>
                    </p>

                    <form method="post" action="{{ route('matches.update', $match) }}" class="space-y-6">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label for="opponent" :value="__('Przeciwnik')" />
                            <x-text-input id="opponent" name="opponent" type="text" class="mt-1 block w-full" :value="old('opponent', $match->opponent)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('opponent')" />
                        </div>

                        <div>
                            <x-input-label for="played_on" :value="__('Data meczu')" />
                            <x-text-input id="played_on" name="played_on" type="date" class="mt-1 block w-full" :value="old('played_on', $match->played_on->toDateString())" max="{{ now()->toDateString() }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('played_on')" />
                        </div>

                        <div class="flex gap-4">
                            <div class="flex-1">
                                <x-input-label for="goals_for" :value="__('Gole Twojej drużyny')" />
                                <x-text-input id="goals_for" name="goals_for" type="number" min="0" class="mt-1 block w-full" :value="old('goals_for', $match->goals_for)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('goals_for')" />
                            </div>
                            <div class="flex-1">
                                <x-input-label for="goals_against" :value="__('Gole przeciwnika')" />
                                <x-text-input id="goals_against" name="goals_against" type="number" min="0" class="mt-1 block w-full" :value="old('goals_against', $match->goals_against)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('goals_against')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Zapisz zmiany') }}</x-primary-button>
                            <a href="{{ route('matches.index') }}" class="link text-sm">{{ __('Anuluj') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
