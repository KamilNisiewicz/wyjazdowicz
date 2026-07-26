<?php

namespace Tests\Feature;

use App\Models\GameMatch;
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
        Http::fake();

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
        Http::assertNothingSent();
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

    public function test_away_match_search_shows_validation_error_when_nominatim_fails(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(null, 500),
        ]);

        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/matches/search', [
            'opponent' => 'Wisła Kraków',
            'played_on' => now()->toDateString(),
            'venue' => 'away',
            'city' => 'Kraków',
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

    public function test_index_lists_matches_sorted_by_played_on_descending(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        GameMatch::factory()->for($user)->create(['opponent' => 'Starszy mecz', 'played_on' => '2026-01-01']);
        GameMatch::factory()->for($user)->create(['opponent' => 'Nowszy mecz', 'played_on' => '2026-06-01']);

        $response = $this->actingAs($user)->get('/matches');

        $response->assertOk();
        $response->assertSeeInOrder(['Nowszy mecz', 'Starszy mecz']);
    }

    public function test_index_shows_empty_state_for_fresh_account(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->get('/matches');

        $response->assertOk();
        $response->assertSee('Nie masz jeszcze żadnych zapisanych meczów');
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

    public function test_edit_form_shows_prefilled_match_data(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        $match = GameMatch::factory()->for($user)->create(['opponent' => 'Śląsk Wrocław']);

        $response = $this->actingAs($user)->get("/matches/{$match->id}/edit");

        $response->assertOk();
        $response->assertSee('Śląsk Wrocław');
    }

    public function test_index_shows_edit_link_and_delete_form_per_match(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        $match = GameMatch::factory()->for($user)->create();

        $response = $this->actingAs($user)->get('/matches');

        $response->assertOk();
        $response->assertSee(route('matches.edit', $match), false);
        $response->assertSee(route('matches.destroy', $match), false);
    }

    public function test_update_changes_opponent_date_and_score(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        $match = GameMatch::factory()->for($user)->create([
            'opponent' => 'Stary przeciwnik',
            'goals_for' => 0,
            'goals_against' => 0,
        ]);

        $response = $this->actingAs($user)->patch("/matches/{$match->id}", [
            'opponent' => 'Nowy przeciwnik',
            'played_on' => now()->toDateString(),
            'goals_for' => 3,
            'goals_against' => 1,
        ]);

        $response->assertRedirect(route('matches.index'));
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'opponent' => 'Nowy przeciwnik',
            'goals_for' => 3,
            'goals_against' => 1,
        ]);
    }

    public function test_update_rejects_negative_score_and_leaves_match_unchanged(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        $match = GameMatch::factory()->for($user)->create(['goals_for' => 2, 'goals_against' => 2]);

        $response = $this->actingAs($user)->patch("/matches/{$match->id}", [
            'opponent' => $match->opponent,
            'played_on' => $match->played_on->toDateString(),
            'goals_for' => -1,
            'goals_against' => 2,
        ]);

        $response->assertSessionHasErrors('goals_for');
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'goals_for' => 2,
            'goals_against' => 2,
        ]);
    }

    public function test_update_rejects_played_on_in_future(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        $match = GameMatch::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch("/matches/{$match->id}", [
            'opponent' => $match->opponent,
            'played_on' => now()->addDay()->toDateString(),
            'goals_for' => $match->goals_for,
            'goals_against' => $match->goals_against,
        ]);

        $response->assertSessionHasErrors('played_on');
    }

    public function test_destroy_removes_match(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        $match = GameMatch::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/matches/{$match->id}");

        $response->assertRedirect(route('matches.index'));
        $this->assertDatabaseMissing('game_matches', ['id' => $match->id]);
    }

    public function test_user_cannot_edit_view_or_delete_another_users_match(): void
    {
        $owner = User::factory()->create();
        Team::factory()->for($owner)->create();
        $match = GameMatch::factory()->for($owner)->create();

        $intruder = User::factory()->create();
        Team::factory()->for($intruder)->create();

        $this->actingAs($intruder)->get("/matches/{$match->id}/edit")->assertNotFound();

        $this->actingAs($intruder)->patch("/matches/{$match->id}", [
            'opponent' => 'Podmieniony przeciwnik',
            'played_on' => now()->toDateString(),
            'goals_for' => 9,
            'goals_against' => 9,
        ])->assertNotFound();

        $this->actingAs($intruder)->delete("/matches/{$match->id}")->assertNotFound();

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'opponent' => $match->opponent,
        ]);
    }

    public function test_guest_is_redirected_to_login_for_edit_update_destroy(): void
    {
        $owner = User::factory()->create();
        Team::factory()->for($owner)->create();
        $match = GameMatch::factory()->for($owner)->create();

        $this->get("/matches/{$match->id}/edit")->assertRedirect('/login');
        $this->patch("/matches/{$match->id}", [])->assertRedirect('/login');
        $this->delete("/matches/{$match->id}")->assertRedirect('/login');
    }
}
