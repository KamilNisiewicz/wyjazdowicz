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
                            {{ __('Potwierdź miasto') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Wybierz właściwe miasto z listy znalezionych przez wyszukiwarkę OpenStreetMap.') }}
                        </p>
                    </header>

                    <form method="post" action="{{ route('team.store') }}" class="mt-6 space-y-6">
                        @csrf

                        <input type="hidden" name="name" value="{{ $name }}">

                        <div class="space-y-3">
                            @foreach ($candidates as $index => $candidate)
                                <label class="flex items-start gap-3">
                                    <input type="radio" name="candidate" value="{{ $index }}" class="mt-1" required>
                                    <span>{{ $candidate['display_name'] }}</span>
                                    <input type="hidden" name="candidates[{{ $index }}][display_name]" value="{{ $candidate['display_name'] }}">
                                    <input type="hidden" name="candidates[{{ $index }}][lat]" value="{{ $candidate['lat'] }}">
                                    <input type="hidden" name="candidates[{{ $index }}][lon]" value="{{ $candidate['lon'] }}">
                                </label>
                            @endforeach
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('candidate')" />

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Zapisz drużynę') }}</x-primary-button>
                            <a href="{{ route('team.edit') }}" class="text-sm text-gray-600 underline">{{ __('Wróć i popraw nazwę') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
