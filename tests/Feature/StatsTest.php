<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Services\StatsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/stats')->assertRedirect('/login');
    }

    public function test_stats_redirects_to_team_form_when_team_is_not_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/stats');

        $response->assertRedirect(route('team.edit'));
    }

    public function test_index_shows_empty_state_for_fresh_account(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $response->assertSee('Dodaj swój pierwszy mecz');
    }

    public function test_index_does_not_show_empty_state_when_matches_exist(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        GameMatch::factory()->for($user)->create();

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $response->assertDontSee('Dodaj swój pierwszy mecz');
    }

    public function test_calculator_counts_results_and_win_percentage(): void
    {
        $matches = collect([
            new GameMatch(['goals_for' => 2, 'goals_against' => 0]), // win
            new GameMatch(['goals_for' => 1, 'goals_against' => 0]), // win
            new GameMatch(['goals_for' => 3, 'goals_against' => 1]), // win
            new GameMatch(['goals_for' => 1, 'goals_against' => 1]), // draw
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss
            new GameMatch(['goals_for' => 0, 'goals_against' => 2]), // loss
        ]);

        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertSame(3, $stats['wins']);
        $this->assertSame(1, $stats['draws']);
        $this->assertSame(2, $stats['losses']);
        $this->assertSame(6, $stats['total']);
        $this->assertSame(50, $stats['win_percentage']);
    }

    public function test_streak_counts_consecutive_identical_results_from_most_recent(): void
    {
        $matches = collect([
            new GameMatch(['goals_for' => 2, 'goals_against' => 0]), // win, most recent
            new GameMatch(['goals_for' => 1, 'goals_against' => 0]), // win
            new GameMatch(['goals_for' => 3, 'goals_against' => 1]), // win
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss, oldest
        ]);

        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertSame(3, $stats['streak_length']);
        $this->assertSame('win', $stats['streak_result']);
    }

    public function test_streak_is_one_when_most_recent_result_breaks_previous_streak(): void
    {
        $matches = collect([
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss, most recent
            new GameMatch(['goals_for' => 2, 'goals_against' => 0]), // win
            new GameMatch(['goals_for' => 1, 'goals_against' => 0]), // win, oldest
        ]);

        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertSame(1, $stats['streak_length']);
        $this->assertSame('loss', $stats['streak_result']);
    }

    public function test_matches_with_same_played_on_date_are_ordered_deterministically_for_streak(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create([
            'played_on' => '2026-05-01',
            'goals_for' => 2,
            'goals_against' => 0, // win, created first (lower id)
        ]);
        GameMatch::factory()->for($user)->create([
            'played_on' => '2026-05-01',
            'goals_for' => 0,
            'goals_against' => 1, // loss, created second (higher id) -> must count as "most recent"
        ]);

        $matches = $user->gameMatches()->orderByDesc('played_on')->orderByDesc('id')->get();
        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertSame('loss', $stats['streak_result']);
        $this->assertSame(1, $stats['streak_length']);
    }

    public function test_is_unlucky_fan_true_when_losses_are_most_common(): void
    {
        $matches = collect([
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss
            new GameMatch(['goals_for' => 1, 'goals_against' => 0]), // win
            new GameMatch(['goals_for' => 1, 'goals_against' => 1]), // draw
        ]);

        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertTrue($stats['is_unlucky_fan']);
    }

    public function test_is_unlucky_fan_false_when_losses_tie_with_wins(): void
    {
        $matches = collect([
            new GameMatch(['goals_for' => 0, 'goals_against' => 1]), // loss
            new GameMatch(['goals_for' => 1, 'goals_against' => 0]), // win
        ]);

        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertFalse($stats['is_unlucky_fan']);
    }

    public function test_total_distance_sums_away_matches_and_ignores_null_home_distance(): void
    {
        $matches = collect([
            new GameMatch(['goals_for' => 1, 'goals_against' => 0, 'venue' => 'away', 'distance_km' => 250]),
            new GameMatch(['goals_for' => 0, 'goals_against' => 1, 'venue' => 'away', 'distance_km' => 120]),
            new GameMatch(['goals_for' => 2, 'goals_against' => 2, 'venue' => 'home', 'distance_km' => null]),
        ]);

        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertSame(370, $stats['total_distance_km']);
    }

    public function test_another_users_matches_do_not_affect_stats(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        GameMatch::factory()->for($user)->create(['goals_for' => 1, 'goals_against' => 0]);

        $other = User::factory()->create();
        Team::factory()->for($other)->create();
        GameMatch::factory()->for($other)->create(['goals_for' => 0, 'goals_against' => 5]);
        GameMatch::factory()->for($other)->create(['goals_for' => 0, 'goals_against' => 5]);
        GameMatch::factory()->for($other)->create(['goals_for' => 0, 'goals_against' => 5]);

        $matches = $user->gameMatches()->orderByDesc('played_on')->orderByDesc('id')->get();
        $stats = (new StatsCalculator)->forMatches($matches);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['wins']);
        $this->assertFalse($stats['is_unlucky_fan']);
    }
}
