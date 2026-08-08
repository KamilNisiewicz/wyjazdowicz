@if ($stats === null)
    <p class="text-gray-600">{{ $emptyMessage }}</p>
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

    <div class="stats stats-vertical sm:stats-horizontal shadow w-full mb-8">
        <div class="stat place-items-center">
            <div class="stat-value text-primary">{{ $stats['win_percentage'] }}%</div>
            <div class="stat-title">{{ __('% zwycięstw') }}</div>
        </div>
        <div class="stat place-items-center">
            <div class="stat-value">{{ $stats['streak_length'] }}× {{ $streakLetter }}</div>
            <div class="stat-title">{{ __('Aktualna passa') }}</div>
        </div>
        @if ($showDistance)
            <div class="stat place-items-center">
                <div class="stat-value">{{ $stats['total_distance_km'] }} km</div>
                <div class="stat-title">{{ __('Łączny dystans') }}</div>
            </div>
        @endif
        @if ($stats['is_unlucky_fan'])
            <div class="stat place-items-center border-error">
                <div class="stat-value text-error">⚠</div>
                <div class="stat-title text-error">{{ __('Pechowy kibic') }}</div>
            </div>
        @endif
    </div>

    <div class="mt-4">
        <h3 class="text-base font-medium text-gray-700 mb-4">{{ __('Bilans') }}</h3>
        <div class="flex items-end gap-8" style="height: 140px;">
            @foreach ($bars as $bar)
                <div class="flex flex-col items-center gap-2" title="{{ $bar['label'] }}: {{ $bar['count'] }}">
                    <span class="text-base font-semibold text-gray-900">{{ $bar['count'] }}</span>
                    <div
                        class="w-6 rounded-t"
                        style="height: {{ max((int) round($bar['count'] / $maxCount * 80), 4) }}px; background-color: {{ $bar['color'] }};"
                    ></div>
                    <span class="text-sm text-gray-500">{{ $bar['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
