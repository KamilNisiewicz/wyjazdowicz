<?php

namespace Tests\Unit;

use App\Models\GameMatch;
use Tests\TestCase;

class GameMatchTest extends TestCase
{
    public function test_result_is_win_when_goals_for_exceeds_goals_against(): void
    {
        $match = new GameMatch(['goals_for' => 2, 'goals_against' => 1]);

        $this->assertSame('win', $match->result);
    }

    public function test_result_is_loss_when_goals_against_exceeds_goals_for(): void
    {
        $match = new GameMatch(['goals_for' => 1, 'goals_against' => 2]);

        $this->assertSame('loss', $match->result);
    }

    public function test_result_is_draw_when_goals_are_equal(): void
    {
        $match = new GameMatch(['goals_for' => 1, 'goals_against' => 1]);

        $this->assertSame('draw', $match->result);
    }
}
