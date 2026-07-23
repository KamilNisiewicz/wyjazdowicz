<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/matches')->assertRedirect('/login');
        $this->get('/matches/create')->assertRedirect('/login');
    }

    public function test_matches_create_redirects_to_team_form_when_team_is_not_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/matches/create');

        $response->assertRedirect(route('team.edit'));
    }

    public function test_home_match_is_created_immediately_without_calling_nominatim(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create([
            'home_city' => 'Warszawa, Polska',
            'home_lat' => 52.2297,
            'home_lng' => 21.0122,
        ]);

        $response = $this->actingAs($user)->post('/matches/search', [
            'opponent' => 'Legia Warszawa',
            'played_on' => now()->toDateString(),
            'venue' => 'home',
            'goals_for' => 2,
            'goals_against' => 1,
        ]);

        $response->assertRedirect(route('matches.index'));
        $this->assertDatabaseHas('game_matches', [
            'user_id' => $user->id,
            'venue' => 'home',
            'city' => 'Warszawa, Polska',
            'distance_km' => null,
        ]);
    }

    public function test_away_match_search_shows_candidates_from_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'Kraków, Polska', 'lat' => '50.0647', 'lon' => '19.9450'],
            ], 200),
        ]);

        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/matches/search', [
            'opponent' => 'Wisła Kraków',
            'played_on' => now()->toDateString(),
            'venue' => 'away',
            'city' => 'Kraków',
            'goals_for' => 1,
            'goals_against' => 1,
        ]);

        $response->assertOk();
        $response->assertSee('Kraków, Polska');
    }

    public function test_away_match_store_creates_match_with_calculated_distance(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create([
            'home_lat' => 52.2297,
            'home_lng' => 21.0122,
        ]);

        $response = $this->actingAs($user)->post('/matches', [
            'opponent' => 'Wisła Kraków',
            'played_on' => now()->toDateString(),
            'goals_for' => 1,
            'goals_against' => 1,
            'candidate' => 0,
            'candidates' => [
                ['display_name' => 'Kraków, Polska', 'lat' => 50.0647, 'lon' => 19.9450],
            ],
        ]);

        $response->assertRedirect(route('matches.index'));
        $this->assertDatabaseHas('game_matches', [
            'user_id' => $user->id,
            'venue' => 'away',
            'city' => 'Kraków, Polska',
        ]);

        $match = $user->gameMatches()->first();
        $this->assertGreaterThanOrEqual(240, $match->distance_km);
        $this->assertLessThanOrEqual(265, $match->distance_km);
    }

    public function test_away_match_search_shows_validation_error_when_city_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/matches/search', [
            'opponent' => 'Nieznany Klub',
            'played_on' => now()->toDateString(),
            'venue' => 'away',
            'city' => 'asdkjaslkdjaslkdj',
            'goals_for' => 0,
            'goals_against' => 0,
        ]);

        $response->assertSessionHasErrors('city');
        $this->assertDatabaseCount('game_matches', 0);
    }

    public function test_store_rejects_candidate_index_outside_submitted_candidates(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/matches', [
            'opponent' => 'Wisła Kraków',
            'played_on' => now()->toDateString(),
            'goals_for' => 1,
            'goals_against' => 1,
            'candidate' => 1,
            'candidates' => [
                ['display_name' => 'Kraków, Polska', 'lat' => 50.0647, 'lon' => 19.9450],
            ],
        ]);

        $response->assertSessionHasErrors('candidate');
        $this->assertDatabaseCount('game_matches', 0);
    }

    public function test_played_on_in_future_is_rejected(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/matches/search', [
            'opponent' => 'Legia Warszawa',
            'played_on' => now()->addDay()->toDateString(),
            'venue' => 'home',
            'goals_for' => 1,
            'goals_against' => 0,
        ]);

        $response->assertSessionHasErrors('played_on');
        $this->assertDatabaseCount('game_matches', 0);
    }
}
