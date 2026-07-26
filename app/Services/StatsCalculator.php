<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatsCalculator
{
    /**
     * @param  Collection<int, \App\Models\GameMatch>  $matches  Must be non-empty and sorted from most recent match to oldest.
     * @return array{wins: int, draws: int, losses: int, total: int, win_percentage: int, streak_length: int, streak_result: string, total_distance_km: int, is_unlucky_fan: bool}
     */
    public function forMatches(Collection $matches): array
    {
        $wins = $matches->filter(fn ($match) => $match->result === 'win')->count();
        $draws = $matches->filter(fn ($match) => $match->result === 'draw')->count();
        $losses = $matches->filter(fn ($match) => $match->result === 'loss')->count();
        $total = $wins + $draws + $losses;

        $streakResult = $matches->first()->result;
        $streakLength = $matches->takeWhile(fn ($match) => $match->result === $streakResult)->count();

        return [
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'total' => $total,
            'win_percentage' => (int) round($wins / $total * 100),
            'streak_length' => $streakLength,
            'streak_result' => $streakResult,
            'total_distance_km' => (int) $matches->sum('distance_km'),
            'is_unlucky_fan' => $losses > $wins && $losses > $draws,
        ];
    }
}
