---
date: 2026-07-30T17:48:46+02:00
researcher: Kamil Nisiewicz
git_commit: 3fdbe1be2952268ab745f846f9dae5bcac5e4c14
branch: master
repository: wyjazdowicz
topic: "Stats consistency (balance/streak/unlucky-fan) after match edit or delete — grounding for Rollout Phase 2 (Risk #3)"
tags: [research, codebase, stats, game-match, edit, delete]
status: complete
last_updated: 2026-07-30
last_updated_by: Kamil Nisiewicz
---

# Research: Stats consistency after match edit or delete

**Date**: 2026-07-30T17:48:46+02:00
**Researcher**: Kamil Nisiewicz
**Git Commit**: 3fdbe1be2952268ab745f846f9dae5bcac5e4c14
**Branch**: master
**Repository**: wyjazdowicz

## Research Question

Ground `test-plan.md` Rollout Phase 2 / Risk #3: "Win/draw/loss balance, streak, or
'unlucky fan' flag goes stale or wrong after a match is edited or deleted." Specifically:
whether any query/collection is cached across requests; how edit/delete trigger
recalculation; whether changing `played_on` in an edit re-sorts correctly for streak.

## Summary

Stats are **never cached or persisted** — every `/stats` request runs a fresh Eloquent
query and recomputes balance, streak, and unlucky-fan from scratch via a stateless
service. This was a **deliberate design decision from S-05** ("no cache to invalidate"),
carried through S-06's home/away split. So the mechanical risk described in the plan
("statelessness of the service doesn't guarantee the controller re-fetches fresh data")
does not currently manifest as a caching bug — the real risk surface is narrower and
more subtle:

1. **Streak correctness depends entirely on the *order* of the collection passed into
   `StatsCalculator::forMatches()`**, not on the DB query being fresh. The service trusts
   its input is sorted newest-first and does not sort or verify it itself
   (`app/Services/StatsCalculator.php:9-10, 24-25`).
2. The DB query itself orders by `played_on DESC, id DESC`
   (`app/Http/Controllers/StatsController.php:13-16`), so an edit that changes
   `played_on` **does** get correctly re-sorted on the next `/stats` load — the ordering
   is derived fresh every time, there is no stale ordering to invalidate.
3. Delete is a hard delete with no soft-delete scope and no cascading aggregate table
   to go stale (`app/Models/GameMatch.php:13-16`; no `StatsCalculator` state persists
   anywhere) — a deleted match simply cannot appear in the next query.
4. **No existing test exercises either scenario.** `StatsTest.php` has zero edit/delete
   coverage; `GameMatchTest.php`'s edit/delete tests only assert on the `game_matches`
   table (`assertDatabaseHas`/`assertDatabaseMissing`), never on `/stats` output. This
   is a real coverage gap, not a currently-observed bug — Phase 2's job is to prove the
   "recalculates live" design actually holds under edit/delete, not just under fresh
   creation.

## Detailed Findings

### StatsCalculator — computation and its ordering contract

- `App\Services\StatsCalculator::forMatches()` — `app/Services/StatsCalculator.php:13-38`.
  Stateless: no properties, no injected dependencies, takes a `Collection` and throws if
  empty (`:15-17`).
- **Balance**: counts matches where the `result` accessor equals `win`/`draw`/`loss`
  (`:19-21`), `total` = sum (`:22`), `win_percentage` = `round(wins/total*100)` (`:32`).
- **Streak**: `streak_result` = `$matches->first()->result` (`:24`); `streak_length` =
  `$matches->takeWhile(...)->count()` while the result stays equal to that first result
  (`:25`). Correctness is **entirely dependent on the collection already being sorted
  newest-first** — the docblock (`:9-10`) states this precondition explicitly, but the
  method itself neither sorts nor verifies it.
- **Unlucky fan**: `losses > wins && losses > draws` (`:36`), per FR-010
  (confirmed in `context/archive/2026-07-26-stats-dashboard/plan.md:130-132`).
- `result` is not a stored column — it's an Eloquent accessor on `GameMatch`
  (`app/Models/GameMatch.php:35-44`) computed live from `goals_for`/`goals_against`, so it
  always reflects current column values with no caching at that layer either.

### No caching anywhere in the stats path

- No `Cache::` usage, static properties, session storage, or memoization anywhere in
  `app/Services`, `app/Http/Controllers`, or `app/Models` (grep across all three
  directories: zero hits).
- `StatsController::index()` — `app/Http/Controllers/StatsController.php:11-30` — runs a
  fresh query every request: `$request->user()->gameMatches()->orderByDesc('played_on')->orderByDesc('id')->get()`
  (`:13-16`). The `id DESC` tiebreaker means same-day matches resolve ties by insertion
  order, which matters for streak when two edited matches land on the same `played_on`.
- Home/away split is derived **in-memory** after the single ordered fetch:
  `$matches->where('venue', 'home')` / `'away'` (`:22-23`) — `Collection::where()`
  preserves the original sort order, so `StatsCalculator` is called up to three times
  (overall/home/away, `:26-28`), each trusting the same already-correct order.

### Edit flow

- Route `PATCH /matches/{match}` → `matches.update` (`routes/web.php:35`), handled by
  `GameMatchController::update()` (`app/Http/Controllers/GameMatchController.php:99-105`)
  via `App\Http\Requests\GameMatch\UpdateRequest`.
- Editable fields are restricted to exactly `opponent`, `played_on`, `goals_for`,
  `goals_against` — both by validation rules (`UpdateRequest.php:19-24`) and by the
  controller's `$request->safe()->only([...])` mass-assignment guard (`:102`). `venue`,
  `city`, `lat`, `lng`, `distance_km` are immutable via edit (confirmed as an intentional
  design choice in `context/archive/2026-07-26-edit-delete-match/plan.md:26-29`: "fix
  wrong venue = delete+recreate").
- No re-sort step exists in the edit flow itself, and none is needed: `/stats` re-queries
  and re-orders by `played_on DESC` on every load (`StatsController.php:13-16`), so an
  edit changing `played_on` is picked up correctly next time stats are viewed.
- No Eloquent model events, observers, listeners, or jobs are tied to `GameMatch` update
  (`app/Models/GameMatch.php` has no `booted()` hook; no `Observer`/`Event`/`Listener`
  classes reference `GameMatch` anywhere in `app/`).

### Delete flow

- Route `DELETE /matches/{match}` → `matches.destroy` (`routes/web.php:36`), handled by
  `GameMatchController::destroy()` (`app/Http/Controllers/GameMatchController.php:107-113`).
  Scoped lookup via `$request->user()->gameMatches()->findOrFail($match)` (`:109`), then
  `$gameMatch->delete()` (`:110`).
- Hard delete: `GameMatch` has no `SoftDeletes` trait (`app/Models/GameMatch.php:13,16`)
  and the migration has no `softDeletes()` column — confirmed also in
  `context/archive/2026-07-26-edit-delete-match/plan.md:27`.
- No cascading aggregate/child table exists to go stale — the only FK cascade in the
  schema is `user_id` cascading *to* matches on user deletion, not the reverse. There is
  no persisted stats table at all; everything is computed on the fly.
- Since delete is hard and the stats query has no soft-delete scope to bypass, a deleted
  match cannot appear in the very next `/stats` query — no explicit invalidation step is
  needed or exists.

### Existing test coverage and conventions to follow

- `tests/Feature/GameMatchTest.php` edit tests (`:348, 374, 395, 423, 449`) and delete
  tests (`:335, 411, 423, 449`) assert only against the `game_matches` DB table
  (`assertDatabaseHas`/`assertDatabaseMissing`) — **none** call `/stats` afterward.
- `tests/Feature/StatsTest.php` has **zero** edit or delete calls (grep for
  `patch|delete|destroy` returns nothing) — this is the exact gap Phase 2 exists to close.
- Existing `StatsTest.php` convention (already avoids the tautology anti-pattern the
  test-plan warns against): expected values are **hand-derived in comments**
  (e.g. `// win`, `// loss`) and asserted as hardcoded numbers
  (`assertSame(3, $stats['wins'])`, streak/unlucky-fan booleans), never recomputed by
  calling `StatsCalculator` inside the test. New edit/delete tests should follow the same
  style.
- Factory pattern: `User::factory()->create()` + `Team::factory()->for($user)->create()`
  + `GameMatch::factory()->for($user)->create([...])`. `GameMatchFactory`
  (`database/factories/GameMatchFactory.php:19-32`) exposes `opponent`, `played_on`,
  `venue`, `city`, `lat`, `lng`, `distance_km`, `goals_for`, `goals_against` as
  overridable attributes. Some `StatsTest` cases build `new GameMatch([...])` in-memory
  collections directly to feed `StatsCalculator` without hitting the DB — not applicable
  here since edit/delete tests must go through the real HTTP routes and DB.
- Only one route serves all three stats tabs (`GET /stats` → `stats.index`,
  `routes/web.php:28`); tab-specific content is asserted via `assertSee` /
  `substr_count($response->getContent(), ...)` (`StatsTest.php:222, 271, 291`) rather than
  separate URIs.
- Match routes: `matches.edit` (GET), `matches.update` (PATCH), `matches.destroy`
  (DELETE) — `routes/web.php:33-36`.

## Code References

- `app/Services/StatsCalculator.php:9-38` — computation + unstated sort precondition
- `app/Http/Controllers/StatsController.php:11-30` — fresh query, no cache, home/away split
- `app/Models/GameMatch.php:13-44` — no SoftDeletes, `result` accessor
- `app/Http/Controllers/GameMatchController.php:99-113` — update/destroy actions
- `app/Http/Requests/GameMatch/UpdateRequest.php:19-24` — editable-field whitelist
- `routes/web.php:28,33-36` — stats and match routes
- `database/factories/GameMatchFactory.php:19-32` — factory attributes
- `tests/Feature/StatsTest.php` — existing stats assertion conventions (no edit/delete coverage)
- `tests/Feature/GameMatchTest.php:348-457` — existing edit/delete tests (DB-only assertions)

## Architecture Insights

- The project's answer to "how do we keep stats correct after mutation" is **structural,
  not procedural**: no cache/aggregate exists to go stale, so there is nothing to
  invalidate. This was a conscious choice, not an oversight (see Historical Context).
- The one real latent fragility is the **implicit sort contract** between
  `StatsController` (which sorts) and `StatsCalculator` (which trusts the sort). Today
  the two are co-located and consistent, but `StatsCalculator` has no defensive check —
  a test that feeds it an unsorted collection would silently produce a wrong streak. This
  is a good target for a unit-level test in addition to the feature-level edit/delete
  tests the plan calls for.
- Home/away tabs reuse the single overall-ordered query via in-memory `Collection::where()`,
  so an edit/delete test should check all three tabs consistently, not just the overall one.

## Historical Context (from prior changes)

- `context/archive/2026-07-26-stats-dashboard/plan.md:56-60` — the "no caching, recompute
  live every page load" design was chosen specifically to answer the roadmap's
  edit/delete-consistency risk; `plan.md:325-329` walks a manual QA script (edit → confirm
  `/stats` updates; delete → confirm stats drop by exactly that match) that is effectively
  the template for this phase's automated tests.
- `context/archive/2026-07-26-stats-dashboard/reviews/impl-review.md:30-38` — an
  empty-collection guard was added to `StatsCalculator::forMatches()` anticipating later
  per-subcollection (home/away) reuse; relevant if a new test's edit/delete leaves a venue
  subcollection empty (e.g. deleting a user's only away match).
- `context/archive/2026-07-26-edit-delete-match/plan.md:26-29` — edit intentionally
  excludes `venue`/`distance_km`/`city` ("Nie dotykamy statystyk/bilansu... przyszły panel
  statystyk będzie liczył się na żywo"); delete is hard, no soft-delete, by design.
- `context/archive/2026-07-26-edit-delete-match/reviews/impl-review.md:52-60` — a past
  fix (F3) tightened `update()` from relying only on validation rules to an explicit
  `$request->safe()->only([...])` whitelist, guarding against future rule additions
  silently making `venue`/`distance_km` writable — worth a regression assertion.
- `context/archive/2026-07-26-home-away-stats-split/plan.md:99-109` — confirms
  `Collection::where()` (in-memory, order-preserving) is required over query-builder
  `where()` for the home/away split, and that this preserves `StatsCalculator`'s sort
  contract automatically.
- `context/archive/2026-07-26-home-away-stats-split/reviews/impl-review.md:23-31` — prior
  gap (F1) was missing an HTTP-level owner-isolation test *per subcollection*
  (home/away tabs); direct precedent for making sure new edit/delete tests check
  isolation/consistency across all three tabs, not just overall.
- `context/changes/testing-geocoding-distance-coverage/plan.md:120-122` and
  `research.md:106-116` — explicit anti-pattern already documented project-wide: don't
  assert against a value computed by calling the same production code under test
  (tautology). Directly applicable to asserting recalculated stats.

## Related Research

- `context/changes/testing-geocoding-distance-coverage/research.md` — Phase 1 research doc,
  same rollout, establishes the `Http::fake()` feature-test pattern this phase's sibling.

## Open Questions

- None blocking. One judgment call for `/10x-plan`: whether to add a defensive unit test
  on `StatsCalculator` for the unsorted-input case (not required by Risk #3's wording,
  which is about edit/delete, but surfaced here as a related latent gap) — leave for the
  planning phase to accept or explicitly defer.
