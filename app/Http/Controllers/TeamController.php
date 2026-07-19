<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\SearchCityRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Services\NominatimGeocoder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function edit(Request $request): View
    {
        return view('team.edit', [
            'team' => $request->user()->team,
        ]);
    }

    public function search(SearchCityRequest $request, NominatimGeocoder $geocoder): View|RedirectResponse
    {
        $candidates = $geocoder->search($request->validated('city'));

        if ($candidates === []) {
            return back()->withInput()->withErrors([
                'city' => __('Nie znaleziono miasta o podanej nazwie. Spróbuj dokładniejszej nazwy, np. z krajem.'),
            ]);
        }

        return view('team.candidates', [
            'name' => $request->validated('name'),
            'candidates' => $candidates,
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $candidate = $validated['candidates'][$validated['candidate']];

        $request->user()->team()->updateOrCreate(
            [],
            [
                'name' => $validated['name'],
                'home_city' => $candidate['display_name'],
                'home_lat' => $candidate['lat'],
                'home_lng' => $candidate['lon'],
            ]
        );

        return redirect()->route('dashboard')->with('status', 'team-updated');
    }
}
