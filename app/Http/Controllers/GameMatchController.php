<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameMatch\SearchCityRequest;
use App\Http\Requests\GameMatch\StoreRequest;
use App\Services\DistanceCalculator;
use App\Services\NominatimGeocoder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameMatchController extends Controller
{
    public function index(Request $request): View
    {
        return view('matches.index', [
            'matches' => $request->user()->gameMatches()->latest('played_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('matches.create');
    }

    public function search(SearchCityRequest $request, NominatimGeocoder $geocoder): View|RedirectResponse
    {
        $validated = $request->validated();
        $team = $request->user()->team;

        if ($validated['venue'] === 'home') {
            $request->user()->gameMatches()->create([
                'opponent' => $validated['opponent'],
                'played_on' => $validated['played_on'],
                'venue' => 'home',
                'city' => $team->home_city,
                'lat' => $team->home_lat,
                'lng' => $team->home_lng,
                'distance_km' => null,
                'goals_for' => $validated['goals_for'],
                'goals_against' => $validated['goals_against'],
            ]);

            return redirect()->route('matches.index')->with('status', 'match-created');
        }

        $candidates = $geocoder->search($validated['city']);

        if ($candidates === []) {
            return back()->withInput()->withErrors([
                'city' => __('Nie znaleziono miasta o podanej nazwie. Spróbuj dokładniejszej nazwy, np. z krajem.'),
            ]);
        }

        return view('matches.candidates', [
            'opponent' => $validated['opponent'],
            'played_on' => $validated['played_on'],
            'goals_for' => $validated['goals_for'],
            'goals_against' => $validated['goals_against'],
            'candidates' => $candidates,
        ]);
    }

    public function store(StoreRequest $request, DistanceCalculator $calculator): RedirectResponse
    {
        $validated = $request->validated();
        $candidate = $validated['candidates'][$validated['candidate']];
        $team = $request->user()->team;

        $request->user()->gameMatches()->create([
            'opponent' => $validated['opponent'],
            'played_on' => $validated['played_on'],
            'venue' => 'away',
            'city' => $candidate['display_name'],
            'lat' => $candidate['lat'],
            'lng' => $candidate['lon'],
            'distance_km' => $calculator->kilometersBetween(
                (float) $team->home_lat,
                (float) $team->home_lng,
                $candidate['lat'],
                $candidate['lon'],
            ),
            'goals_for' => $validated['goals_for'],
            'goals_against' => $validated['goals_against'],
        ]);

        return redirect()->route('matches.index')->with('status', 'match-created');
    }
}
