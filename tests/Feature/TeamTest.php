<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/team');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_redirects_to_team_form_when_team_is_not_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('team.edit'));
    }

    public function test_dashboard_is_accessible_when_team_is_set(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_search_shows_candidates_from_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'Warszawa, Polska', 'lat' => '52.23', 'lon' => '21.01'],
                ['display_name' => 'Warszawa, Ohio, USA', 'lat' => '39.5', 'lon' => '-84.0'],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/team/search', [
            'name' => 'Legia Warszawa',
            'city' => 'Warszawa',
        ]);

        $response->assertOk();
        $response->assertSee('Warszawa, Polska');
        $response->assertSee('Warszawa, Ohio, USA');
    }

    public function test_search_shows_validation_error_when_no_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/team/search', [
            'name' => 'Legia Warszawa',
            'city' => 'asdkjaslkdjaslkdj',
        ]);

        $response->assertSessionHasErrors('city');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_search_shows_validation_error_when_nominatim_fails(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(null, 500),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/team/search', [
            'name' => 'Legia Warszawa',
            'city' => 'Warszawa',
        ]);

        $response->assertSessionHasErrors('city');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_store_creates_team_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/team', [
            'name' => 'Legia Warszawa',
            'candidate' => 0,
            'candidates' => [
                ['display_name' => 'Warszawa, Polska', 'lat' => 52.23, 'lon' => 21.01],
            ],
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('teams', [
            'user_id' => $user->id,
            'name' => 'Legia Warszawa',
            'home_city' => 'Warszawa, Polska',
        ]);
    }

    public function test_store_rejects_candidate_index_outside_submitted_candidates(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/team', [
            'name' => 'Legia Warszawa',
            'candidate' => 1,
            'candidates' => [
                ['display_name' => 'Warszawa, Polska', 'lat' => 52.23, 'lon' => 21.01],
            ],
        ]);

        $response->assertSessionHasErrors('candidate');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_store_updates_existing_team_instead_of_creating_new_one(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create(['name' => 'Old Team']);

        $this->actingAs($user)->post('/team', [
            'name' => 'Nowa Nazwa',
            'candidate' => 0,
            'candidates' => [
                ['display_name' => 'Kraków, Polska', 'lat' => 50.06, 'lon' => 19.94],
            ],
        ]);

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('teams', [
            'user_id' => $user->id,
            'name' => 'Nowa Nazwa',
            'home_city' => 'Kraków, Polska',
        ]);
    }
}
