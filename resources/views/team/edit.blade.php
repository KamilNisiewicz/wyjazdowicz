<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Drużyna') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">
                            {{ __('Ulubiona drużyna') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Miasto, w którym drużyna rozgrywa mecze domowe, jest używane jako Twoja lokalizacja "dom" do liczenia dystansu na mecze wyjazdowe.') }}
                        </p>
                    </header>

                    <form method="post" action="{{ route('team.search') }}" class="mt-6 space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="name" :value="__('Nazwa drużyny')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $team?->name)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="city" :value="__('Miasto meczów domowych')" />
                            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $team ? str($team->home_city)->before(',')->toString() : null)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('city')" />
                            @if ($team && ! $errors->has('city'))
                                <p class="mt-1 text-sm text-gray-500">{{ __('Aktualnie ustawione:') }} {{ $team->home_city }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Szukaj miasta') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
