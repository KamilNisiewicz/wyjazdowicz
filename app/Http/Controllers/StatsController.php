<?php

namespace App\Http\Controllers;

use App\Services\StatsCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(Request $request, StatsCalculator $calculator): View
    {
        $matches = $request->user()->gameMatches()
            ->orderByDesc('played_on')
            ->orderByDesc('id')
            ->get();

        if ($matches->isEmpty()) {
            return view('stats.index', ['stats' => null]);
        }

        $homeMatches = $matches->where('venue', 'home');
        $awayMatches = $matches->where('venue', 'away');

        return view('stats.index', [
            'stats' => $calculator->forMatches($matches),
            'homeStats' => $homeMatches->isEmpty() ? null : $calculator->forMatches($homeMatches),
            'awayStats' => $awayMatches->isEmpty() ? null : $calculator->forMatches($awayMatches),
        ]);
    }
}
