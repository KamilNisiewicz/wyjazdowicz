<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dodaj mecz') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body max-w-xl">
                    <form method="post" action="{{ route('matches.search') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="opponent" :value="__('Przeciwnik')" />
                            <x-text-input id="opponent" name="opponent" type="text" class="mt-1 block w-full" :value="old('opponent')" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('opponent')" />
                        </div>

                        <div>
                            <x-input-label for="played_on" :value="__('Data meczu')" />
                            <x-text-input id="played_on" name="played_on" type="date" class="mt-1 block w-full" :value="old('played_on')" max="{{ now()->toDateString() }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('played_on')" />
                        </div>

                        <div>
                            <x-input-label :value="__('Dom czy wyjazd')" />
                            <div class="mt-1 flex items-center gap-6">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="venue" value="home" class="radio radio-primary" @checked(old('venue') === 'home') required>
                                    {{ __('Dom') }}
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="venue" value="away" class="radio radio-primary" @checked(old('venue') === 'away')>
                                    {{ __('Wyjazd') }}
                                </label>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('venue')" />
                        </div>

                        <div>
                            <x-input-label for="city" :value="__('Miejscowość meczu')" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city')" />
                            <p class="mt-1 text-sm text-gray-500">{{ __('Wymagane tylko dla meczu wyjazdowego — dla meczu domowego miejscowość jest ustalana automatycznie z profilu drużyny.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('city')" />
                        </div>

                        <div class="flex gap-4">
                            <div class="flex-1">
                                <x-input-label for="goals_for" :value="__('Gole Twojej drużyny')" />
                                <x-text-input id="goals_for" name="goals_for" type="number" min="0" class="mt-1 block w-full" :value="old('goals_for')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('goals_for')" />
                            </div>
                            <div class="flex-1">
                                <x-input-label for="goals_against" :value="__('Gole przeciwnika')" />
                                <x-text-input id="goals_against" name="goals_against" type="number" min="0" class="mt-1 block w-full" :value="old('goals_against')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('goals_against')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Dalej') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
