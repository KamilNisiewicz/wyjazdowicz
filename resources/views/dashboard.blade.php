<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="text-base-content/70 mb-4">{{ __('Jesteś zalogowany!') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="{{ route('matches.index') }}" class="card bg-base-100 shadow hover:shadow-md hover:bg-primary/5 transition">
                    <div class="card-body flex-row items-center gap-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                        </svg>
                        <div>
                            <div class="font-medium text-base-content">{{ __('Mecze') }}</div>
                            <div class="text-sm text-base-content/60">{{ __('Zobacz i zarządzaj swoimi meczami') }}</div>
                        </div>
                    </div>
                </a>

                <a href="{{ route('stats.index') }}" class="card bg-base-100 shadow hover:shadow-md hover:bg-primary/5 transition">
                    <div class="card-body flex-row items-center gap-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        <div>
                            <div class="font-medium text-base-content">{{ __('Statystyki') }}</div>
                            <div class="text-sm text-base-content/60">{{ __('Zobacz swoje statystyki wygranych i przegranych') }}</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
