<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Mecze') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'match-created')
                <div class="alert alert-success">
                    {{ __('Mecz zapisany.') }}
                </div>
            @elseif (session('status') === 'match-updated')
                <div class="alert alert-success">
                    {{ __('Mecz zaktualizowany.') }}
                </div>
            @elseif (session('status') === 'match-deleted')
                <div class="alert alert-success">
                    {{ __('Mecz usunięty.') }}
                </div>
            @endif

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Twoje mecze') }}</h3>
                    <a href="{{ route('matches.create') }}" class="btn btn-primary">
                        {{ __('Dodaj mecz') }}
                    </a>
                </div>

                @if ($matches->isEmpty())
                    <p class="text-gray-600">{{ __('Nie masz jeszcze żadnych zapisanych meczów. Dodaj pierwszy, żeby zacząć śledzić swoje wyjazdy.') }}</p>
                @else
                    {{-- Widok mobilny: karty, żeby akcje nigdy nie wymagały przewijania w poziomie --}}
                    <div class="sm:hidden space-y-3">
                        @foreach ($matches as $match)
                            <div class="border rounded-lg p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-medium text-gray-900">{{ $match->opponent }}</span>
                                    <span class="text-sm text-gray-500 whitespace-nowrap">{{ $match->played_on->format('Y-m-d') }}</span>
                                </div>
                                <div class="mt-1 text-sm text-gray-600 flex flex-wrap items-center gap-x-3">
                                    <span class="badge badge-outline {{ $match->venue === 'home' ? 'badge-primary' : 'badge-accent' }}">{{ $match->venue === 'home' ? __('Dom') : __('Wyjazd') }}</span>
                                    <span>{{ $match->goals_for }}:{{ $match->goals_against }}</span>
                                    <span>{{ $match->distance_km !== null ? $match->distance_km.' km' : '—' }}</span>
                                </div>
                                <div class="mt-3 flex items-center gap-4">
                                    <a href="{{ route('matches.edit', $match) }}" class="btn btn-sm btn-outline btn-primary">{{ __('Edytuj') }}</a>
                                    <x-danger-button
                                        type="button"
                                        class="btn-sm"
                                        x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'confirm-match-deletion-{{ $match->id }}')"
                                    >{{ __('Usuń') }}</x-danger-button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Widok od sm w górę: pełna tabela --}}
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b text-gray-500">
                                    <th class="py-2 pr-4">{{ __('Data') }}</th>
                                    <th class="py-2 pr-4">{{ __('Przeciwnik') }}</th>
                                    <th class="py-2 pr-4">{{ __('Dom/Wyjazd') }}</th>
                                    <th class="py-2 pr-4">{{ __('Wynik') }}</th>
                                    <th class="py-2 pr-4">{{ __('Dystans') }}</th>
                                    <th class="py-2 pr-4">{{ __('Akcje') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($matches as $match)
                                    <tr class="border-b">
                                        <td class="py-2 pr-4">{{ $match->played_on->format('Y-m-d') }}</td>
                                        <td class="py-2 pr-4">{{ $match->opponent }}</td>
                                        <td class="py-2 pr-4"><span class="badge badge-outline {{ $match->venue === 'home' ? 'badge-primary' : 'badge-accent' }}">{{ $match->venue === 'home' ? __('Dom') : __('Wyjazd') }}</span></td>
                                        <td class="py-2 pr-4">{{ $match->goals_for }}:{{ $match->goals_against }}</td>
                                        <td class="py-2 pr-4">{{ $match->distance_km !== null ? $match->distance_km.' km' : '—' }}</td>
                                        <td class="py-2 pr-4">
                                            <div class="flex items-center gap-3">
                                                <a href="{{ route('matches.edit', $match) }}" class="btn btn-sm btn-outline btn-primary">{{ __('Edytuj') }}</a>
                                                <x-danger-button
                                                    type="button"
                                                    class="btn-sm"
                                                    x-data=""
                                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-match-deletion-{{ $match->id }}')"
                                                >{{ __('Usuń') }}</x-danger-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Modale usuwania: wspólne dla obu widoków, poza kontenerami hidden/sm:hidden żeby zawsze się renderowały --}}
                    @foreach ($matches as $match)
                        <x-modal :name="'confirm-match-deletion-'.$match->id" focusable>
                            <form method="post" action="{{ route('matches.destroy', $match) }}" class="p-6">
                                @csrf
                                @method('delete')

                                <h2 class="text-lg font-medium text-gray-900">
                                    {{ __('Czy na pewno chcesz usunąć ten mecz?') }}
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    {{ $match->opponent }}, {{ $match->played_on->format('Y-m-d') }} — {{ __('tej operacji nie można cofnąć.') }}
                                </p>

                                <div class="mt-6 flex justify-end">
                                    <x-secondary-button x-on:click="$dispatch('close')">
                                        {{ __('Anuluj') }}
                                    </x-secondary-button>

                                    <x-danger-button class="ms-3">
                                        {{ __('Usuń mecz') }}
                                    </x-danger-button>
                                </div>
                            </form>
                        </x-modal>
                    @endforeach
                @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
