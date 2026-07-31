<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnershipContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One entry per GameMatch/Team route that accepts a client-supplied
     * resource ID. Add a new entry here when a future route accepts an
     * owned model's ID from the client — this array is the whole contract.
     *
     * @return array<string, array{method: string, uriTemplate: string, mutating: bool}>
     */
    public static function idTakingRoutes(): array
    {
        return [
            'matches.edit (GET)' => ['method' => 'GET', 'uriTemplate' => '/matches/{id}/edit', 'mutating' => false],
            'matches.update (PATCH)' => ['method' => 'PATCH', 'uriTemplate' => '/matches/{id}', 'mutating' => true],
            'matches.destroy (DELETE)' => ['method' => 'DELETE', 'uriTemplate' => '/matches/{id}', 'mutating' => true],
        ];
    }

    #[DataProvider('idTakingRoutes')]
    public function test_intruder_cannot_access_another_users_match_by_id(string $method, string $uriTemplate, bool $mutating): void
    {
        $owner = User::factory()->create();
        Team::factory()->for($owner)->create();
        $match = GameMatch::factory()->for($owner)->create();

        $intruder = User::factory()->create();
        Team::factory()->for($intruder)->create();

        $uri = str_replace('{id}', (string) $match->id, $uriTemplate);

        // Valid-shaped payload for mutating routes so the request would
        // succeed as a redirect *if* scoping failed — proving the 404 is a
        // real ownership check, not validation rejecting the request first.
        $payload = $mutating ? [
            'opponent' => 'Podmieniony przeciwnik',
            'played_on' => now()->toDateString(),
            'goals_for' => 9,
            'goals_against' => 9,
        ] : [];

        $this->actingAs($intruder)->call($method, $uri, $payload)->assertNotFound();

        if ($mutating) {
            $this->assertDatabaseHas('game_matches', [
                'id' => $match->id,
                'opponent' => $match->opponent,
            ]);
        }
    }
}
