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

        return view('stats.index', [
            'stats' => $matches->isEmpty() ? null : $calculator->forMatches($matches),
        ]);
    }
}
