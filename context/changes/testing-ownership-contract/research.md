---
date: 2026-07-31T00:00:00+02:00
researcher: Claude
git_commit: 5d489a7add641808b33af0af490e88048bbcf111
branch: master
repository: wyjazdowicz
topic: "Ownership contract across GameMatch/Team routes (test-plan.md §3 Phase 3, Risk #5)"
tags: [research, codebase, authorization, idor, game-match, team, stats]
status: complete
last_updated: 2026-07-31
last_updated_by: Claude
---

# Research: Ownership contract across GameMatch/Team routes

**Date**: 2026-07-31
**Researcher**: Claude
**Git Commit**: 5d489a7add641808b33af0af490e88048bbcf111
**Branch**: master
**Repository**: wyjazdowicz

## Research Question

Ground rollout Phase 3 of `test-plan.md` (Risk #5 — no centralized authorization
policy; a future endpoint touching `GameMatch`/`Team` could skip user-scoping
and expose another user's match). Enumerate every controller/route touching
`GameMatch`/`Team`, verify the scoping pattern is consistent, find existing
owner-isolation tests, and identify the cheapest useful test layer.

## Summary

The project's claim ("authorization is query scoping, not a policy") is
**confirmed** — no `app/Policies`, no `Gate::` calls, no `AuthServiceProvider`,
and `{match}` is a bare `int` route parameter (not Eloquent route-model
binding), so nothing implicit can bypass scoping. Every method that resolves a
`GameMatch`/`Team` record does so exclusively through `$request->user()`
relation chains (`hasMany gameMatches`, `hasOne team`), which is enforced at
the SQL level (`user_id` in the `WHERE` clause).

**Correction to §2 Risk Response Guidance**: only **3 routes** are actually
attackable by an ID swap — `matches.edit` (GET), `matches.update` (PATCH),
`matches.destroy` (DELETE) — the only ones that accept a resource ID from the
client. Every other match/stats/team route (`matches.index`, `matches.search`,
`matches.store`, `stats.index`, `team.edit`, `team.search`, `team.store`) is
structurally immune: it takes no resource ID at all, so there is nothing for
an attacker to swap in. The plan's phrasing "one contract test across all
match/stats routes" should be read as "the 3 ID-taking routes," not literally
every route in §3's table — iterating the ID-less routes would be assertions
with no attack surface behind them (an anti-pattern: coverage theater).

**A test already exists** for exactly those 3 routes:
`tests/Feature/GameMatchTest.php:423-447`,
`test_user_cannot_edit_view_or_delete_another_users_match`. It is correct and
passing today. The residual gap is not "untested," it is **"tested via one
big method with 3 copy-pasted assertions, not a reusable contract"** — exactly
the anti-pattern named in §2: "a new endpoint written without awareness of the
pattern could use `GameMatch::find($id)` directly and quietly break isolation,"
and nothing forces a future author to extend this specific method. A
PHPUnit data-provider-driven version turns "remember to add a test method"
into "add one line to an array," which is the actual, buildable version of
"one shared pattern, not per-endpoint memory."

## Detailed Findings

### GameMatch/Team/Stats routes and their scoping

| Route name | Controller@method | Record resolution | Route takes ID? | User-scoped |
|---|---|---|---|---|
| matches.index | `index` | `$request->user()->gameMatches()->latest('played_on')->get()` — `app/Http/Controllers/GameMatchController.php:19` | No | Yes |
| matches.create | `create` | no record fetched | No | N/A |
| matches.search | `search` | reads `$request->user()->team` for coords — `GameMatchController.php:31,34` | No | Yes |
| matches.store | `store` | `$request->user()->gameMatches()->create([...])` — `GameMatchController.php:72` | No | Yes |
| **matches.edit** | `edit` | `$request->user()->gameMatches()->findOrFail($match)` — `GameMatchController.php:95` | **Yes** | Yes |
| **matches.update** | `update` | `$request->user()->gameMatches()->findOrFail($match)` — `GameMatchController.php:101` | **Yes** | Yes |
| **matches.destroy** | `destroy` | `$request->user()->gameMatches()->findOrFail($match)` — `GameMatchController.php:109` | **Yes** | Yes |
| team.edit | `edit` | `$request->user()->team` — `app/Http/Controllers/TeamController.php:17` | No | Yes |
| team.search | `search` | no record fetch, geocoder only — `TeamController.php:23` | No | N/A |
| team.store | `store` | `$request->user()->team()->updateOrCreate([], [...])` — `TeamController.php:42` | No | Yes |
| stats.index | `index` | `$request->user()->gameMatches()->orderByDesc(...)->get()` — `app/Http/Controllers/StatsController.php:13` | No | Yes |

`{match}` is typed `int $match` in `GameMatchController.php:92,99,107` (not
`GameMatch $match`), confirmed against `routes/web.php:34-36` — Laravel's
implicit route-model binding never engages, so it cannot silently bypass the
`$request->user()->gameMatches()` scope.

**No `app/Policies` directory, no `Gate::` calls anywhere in `app/`.**
`app/Providers/AppServiceProvider.php:21-24` registers only password rules.
`bootstrap/app.php` has no custom route-model-binding resolver.

### Existing owner-isolation test

`tests/Feature/GameMatchTest.php:423-447`:

```php
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
```

This already proves the exact behavior §2 asks for (404, no existence leak,
no mutation) for all 3 ID-taking routes, in one method with 3 inline
assertions. A neighboring test,
`test_guest_is_redirected_to_login_for_edit_update_destroy`
(`GameMatchTest.php:449-458`), covers the unauthenticated case (redirect to
`/login`), which is a different concern (missing session, not cross-user ID
swap) — not part of this risk.

`StatsTest.php:165` and `:274` assert another user's matches don't leak into
stats totals (data-isolation of aggregates, not per-resource access denial —
`stats.index` has no ID param to attack). `home-away-stats-split` archive
(`context/archive/2026-07-26-home-away-stats-split/reviews/impl-review.md:23-31`)
adds `test_another_users_home_and_away_matches_do_not_affect_my_tabs`
(commit `776cc44`) for the same reason. No isolation test exists for
`team.edit`/`team.store`/`team.search` — but none is meaningfully possible:
`Team` is a 1:1 relation with no route ID param, so there is no ID to swap.

### Historical decisions (archive)

- `context/archive/2026-07-26-edit-delete-match/plan.md:28` — "Nie tworzymy
  `GameMatchPolicy` — autoryzacja to query scoping przez
  `$request->user()->gameMatches()`, tak jak już robi `index()`."
- `context/archive/2026-07-26-edit-delete-match/plan.md:34` — scoping via the
  user relation "gwarantuje, że próba dostępu do cudzego meczu kończy się
  `ModelNotFoundException` → 404, zanim jakikolwiek cudzy rekord trafi do
  pamięci."
- `context/archive/2026-07-26-stats-dashboard/plan.md:36-38` — same pattern
  confirmed for stats: "izolacja między kontami to kwestia zapytania
  (`$request->user()->gameMatches()`), nie osobnej `Policy`."
- `context/archive/2026-07-26-edit-delete-match/plan.md:145,180` — the IDOR
  test above was planned and implemented at commit `628f135`.
- `context/foundation/prd.md:66-67,174-177` — guardrail: "dane jednego
  użytkownika nigdy nie są widoczne dla innego"; flat single-role model, no
  admin/roles in MVP.

## Code References

- `app/Http/Controllers/GameMatchController.php:92-113` — `edit`/`update`/`destroy`, the only 3 ID-taking, attackable methods
- `app/Http/Controllers/GameMatchController.php:19` — `index` scoping precedent
- `app/Http/Controllers/TeamController.php:17,42` — Team scoping (no ID param)
- `app/Http/Controllers/StatsController.php:13` — Stats scoping (no ID param)
- `routes/web.php:34-36` — the 3 routes with `{match}`
- `tests/Feature/GameMatchTest.php:423-447` — existing IDOR test, needs data-provider refactor
- `tests/Feature/StatsTest.php:165,274` — stats aggregate isolation (different concern, already covered)

## Architecture Insights

Ownership is enforced by a single, consistently-applied convention: every
controller method resolves `GameMatch`/`Team` records via
`$request->user()->{relation}()`, never via the bare model. This is
discipline, not a structural guarantee — nothing in the framework or a linter
stops a future method from writing `GameMatch::findOrFail($id)` directly. The
risk is real (per §2's "must challenge"), but not currently manifest: the
gap is forward-looking (future endpoints), not a bug in shipped code.

## Historical Context (from prior changes)

See "Historical decisions" above — the no-Policy decision and its rationale
were made explicitly in S-04 (`edit-delete-match`) and reaffirmed in S-05
(`stats-dashboard`); the pattern has held through S-04/S-05/S-06 without
exception, including the follow-up isolation test added in S-06 triage
(`776cc44`) when the tab-split feature introduced a review-caught gap.

## Related Research

- `context/changes/testing-geocoding-distance-coverage/research.md` (Phase 1)
- `context/changes/testing-stats-consistency-after-edit-delete/research.md` (Phase 2)

## Open Questions

None — scope is narrow and fully grounded: 3 routes, 1 existing test to
refactor into a contract, no new production code implied.
