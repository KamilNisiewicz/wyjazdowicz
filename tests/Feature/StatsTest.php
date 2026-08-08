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

    public function test_stats_page_shows_three_tabs_with_independent_balances(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        // Home: 2 wins, 1 loss -> 67%
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 2, 'goals_against' => 0]);
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 3, 'goals_against' => 1]);
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 0, 'goals_against' => 1]);

        // Away: 1 win, 2 losses -> 33%
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 100, 'goals_for' => 2, 'goals_against' => 0]);
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 50, 'goals_for' => 0, 'goals_against' => 1]);
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 75, 'goals_for' => 0, 'goals_against' => 2]);

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $response->assertSee(__('Ogółem'));
        $response->assertSee(__('Dom'));
        $response->assertSee(__('Wyjazd'));
        $response->assertSee('67%'); // home
        $response->assertSee('33%'); // away
        $response->assertSee('50%'); // overall (3 wins, 3 losses)
    }

    public function test_home_tab_omits_distance_tile_while_overall_and_away_show_it(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null]);
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 200]);

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), __('Łączny dystans')));
    }

    public function test_home_tab_shows_empty_message_when_user_has_no_home_matches(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 150]);

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $response->assertSee(__('Brak zapisanych meczów domowych.'));
    }

    public function test_away_tab_shows_empty_message_when_user_has_no_away_matches(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null]);

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $response->assertSee(__('Brak zapisanych meczów wyjazdowych.'));
    }

    public function test_unlucky_fan_tile_is_counted_independently_per_tab(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        // Home: 3 losses -> unlucky
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 0, 'goals_against' => 1]);
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 0, 'goals_against' => 1]);
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 0, 'goals_against' => 1]);

        // Away: 3 wins -> not unlucky
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 10, 'goals_for' => 1, 'goals_against' => 0]);
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 10, 'goals_for' => 1, 'goals_against' => 0]);
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 10, 'goals_for' => 1, 'goals_against' => 0]);

        // Overall: 3 wins, 3 losses -> losses not strictly greater than wins -> not unlucky

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'border-error'));
    }

    public function test_another_users_home_and_away_matches_do_not_affect_my_tabs(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();
        GameMatch::factory()->for($user)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 1, 'goals_against' => 0]);
        GameMatch::factory()->for($user)->create(['venue' => 'away', 'distance_km' => 100, 'goals_for' => 1, 'goals_against' => 0]);

        $other = User::factory()->create();
        Team::factory()->for($other)->create();
        GameMatch::factory()->for($other)->create(['venue' => 'home', 'distance_km' => null, 'goals_for' => 0, 'goals_against' => 5]);
        GameMatch::factory()->for($other)->create(['venue' => 'away', 'distance_km' => 999, 'goals_for' => 0, 'goals_against' => 5]);

        $response = $this->actingAs($user)->get('/stats');

        $response->assertOk();
        // Both my venues have exactly one win each -> 100% in every tab; the
        // other user's losses and 999km would drag these numbers down if leaked.
        $this->assertSame(3, substr_count($response->getContent(), '100%'));
        $response->assertDontSee('999 km');
    }

    public function test_editing_match_result_updates_balance_and_unlucky_fan_across_tabs(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create([
            'venue' => 'home',
            'distance_km' => null,
            'goals_for' => 2,
            'goals_against' => 0, // win
        ]);
        $awayMatch = GameMatch::factory()->for($user)->create([
            'venue' => 'away',
            'distance_km' => 100,
            'goals_for' => 2,
            'goals_against' => 0, // win
        ]);

        // Baseline: both matches are wins -> 100% on overall, home, and away tabs; nobody unlucky.
        $before = $this->actingAs($user)->get('/stats');
        $before->assertOk();
        $this->assertSame(3, substr_count($before->getContent(), '>100%<'));
        $before->assertDontSee('border-error');

        $response = $this->actingAs($user)->patch("/matches/{$awayMatch->id}", [
            'opponent' => $awayMatch->opponent,
            'played_on' => $awayMatch->played_on->toDateString(),
            'goals_for' => 0,
            'goals_against' => 1, // away match flips to a loss
        ]);
        $response->assertRedirect(route('matches.index'));

        // After edit: overall = 1 win + 1 loss -> 50%, tied, not unlucky.
        // Home is untouched -> still 100%. Away = 0 wins, 1 loss -> 0%, unlucky
        // (losses > wins && losses > draws).
        $after = $this->actingAs($user)->get('/stats');
        $after->assertOk();
        $this->assertSame(1, substr_count($after->getContent(), '>50%<'));
        $this->assertSame(1, substr_count($after->getContent(), '>100%<'));
        $this->assertSame(1, substr_count($after->getContent(), '>0%<'));
        $this->assertSame(1, substr_count($after->getContent(), 'border-error'));
    }

    public function test_editing_played_on_reorders_streak_across_tabs(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create([
            'venue' => 'home',
            'distance_km' => null,
            'played_on' => '2026-01-10',
            'goals_for' => 2,
            'goals_against' => 0, // H1: win, newest overall+home before the edit
        ]);
        $h2 = GameMatch::factory()->for($user)->create([
            'venue' => 'home',
            'distance_km' => null,
            'played_on' => '2026-01-05',
            'goals_for' => 0,
            'goals_against' => 1, // H2: loss, oldest home match before the edit
        ]);
        GameMatch::factory()->for($user)->create([
            'venue' => 'away',
            'distance_km' => 50,
            'played_on' => '2026-01-08',
            'goals_for' => 0,
            'goals_against' => 1, // A1: loss, newest away
        ]);
        GameMatch::factory()->for($user)->create([
            'venue' => 'away',
            'distance_km' => 80,
            'played_on' => '2026-01-03',
            'goals_for' => 2,
            'goals_against' => 0, // A2: win, oldest away
        ]);

        // Baseline order (desc by played_on): overall H1,A1,H2,A2; home H1,H2; away A1,A2.
        // Streaks: overall "1× W" (H1 breaks on A1), home "1× W" (H1 breaks on H2),
        // away "1× P" (A1 breaks on A2).
        $before = $this->actingAs($user)->get('/stats');
        $before->assertOk();
        $this->assertSame(2, substr_count($before->getContent(), '>1× W<'));
        $this->assertSame(1, substr_count($before->getContent(), '>1× P<'));

        // Move H2 to be the newest match overall and within the home tab, without
        // changing its result (still a loss).
        $response = $this->actingAs($user)->patch("/matches/{$h2->id}", [
            'opponent' => $h2->opponent,
            'played_on' => '2026-01-12',
            'goals_for' => 0,
            'goals_against' => 1,
        ]);
        $response->assertRedirect(route('matches.index'));

        // New order: overall H2,H1,A1,A2; home H2,H1; away unchanged (A1,A2).
        // Streaks: overall "1× P" (H2 breaks on H1), home "1× P" (H2 breaks on H1),
        // away still "1× P" (untouched by this edit -> isolation held).
        $after = $this->actingAs($user)->get('/stats');
        $after->assertOk();
        $this->assertSame(0, substr_count($after->getContent(), '>1× W<'));
        $this->assertSame(3, substr_count($after->getContent(), '>1× P<'));
    }

    public function test_deleting_match_updates_balance_and_unlucky_fan_on_next_stats_view(): void
    {
        $user = User::factory()->create();
        Team::factory()->for($user)->create();

        GameMatch::factory()->for($user)->create([
            'venue' => 'away',
            'distance_km' => 10,
            'goals_for' => 2,
            'goals_against' => 0, // win
        ]);
        $loss1 = GameMatch::factory()->for($user)->create([
            'venue' => 'away',
            'distance_km' => 20,
            'goals_for' => 0,
            'goals_against' => 1, // loss
        ]);
        GameMatch::factory()->for($user)->create([
            'venue' => 'away',
            'distance_km' => 30,
            'goals_for' => 0,
            'goals_against' => 1, // loss
        ]);

        // All matches are away, so overall and away tabs share the same numbers by
        // construction (home has none, so it contributes no percentage to collide with).
        // Baseline: 1 win, 2 losses, total 3 -> 33%; losses(2) > wins(1) && losses(2) >
        // draws(0) -> unlucky on both overall and away.
        $before = $this->actingAs($user)->get('/stats');
        $before->assertOk();
        $this->assertSame(2, substr_count($before->getContent(), '>33%<'));
        $this->assertSame(2, substr_count($before->getContent(), 'border-error'));

        $response = $this->actingAs($user)->delete("/matches/{$loss1->id}");
        $response->assertRedirect(route('matches.index'));

        // After delete: 1 win, 1 loss, total 2 -> 50%, tied -> not unlucky.
        $after = $this->actingAs($user)->get('/stats');
        $after->assertOk();
        $this->assertSame(2, substr_count($after->getContent(), '>50%<'));
        $after->assertDontSee('border-error');
    }

    public function test_streak_result_depends_entirely_on_caller_supplied_order(): void
    {
        // StatsCalculator::forMatches() does not sort its input — it trusts the
        // caller (StatsController) to supply matches newest-first. This test makes
        // that contract explicit: the same two matches produce an opposite
        // streak_result depending purely on the order they're passed in.
        $win = new GameMatch(['goals_for' => 2, 'goals_against' => 0]);
        $loss = new GameMatch(['goals_for' => 0, 'goals_against' => 1]);

        $newestFirst = (new StatsCalculator)->forMatches(collect([$win, $loss]));
        $oldestFirst = (new StatsCalculator)->forMatches(collect([$loss, $win]));

        $this->assertSame('win', $newestFirst['streak_result']);
        $this->assertSame('loss', $oldestFirst['streak_result']);
    }
}
