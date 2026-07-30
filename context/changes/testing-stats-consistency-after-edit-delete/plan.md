# Stats consistency after edit/delete (test rollout Phase 2) Implementation Plan

## Overview

Close the test-coverage gap identified by `research.md` for rollout Phase 2
of `context/foundation/test-plan.md`: no existing test proves that balance,
streak, and the unlucky-fan flag on `/stats` reflect an edited or deleted
match without extra user action (Risk #3). No production code changes —
this phase only adds tests.

## Current State Analysis

- `tests/Feature/StatsTest.php` (294 lines, 18 tests) thoroughly covers
  `StatsCalculator` in isolation and `/stats` rendering for freshly-created
  matches, including per-tab independence (`:185-293`) and same-`played_on`
  tie-breaking fetched fresh from the DB (`:102-123`). It has **zero**
  `patch`/`delete` calls — no test exercises an edit or a delete at all.
- `tests/Feature/GameMatchTest.php` edit tests (`:348-409`) and delete test
  (`:411-421`) assert only against the `game_matches` DB table
  (`assertDatabaseHas`/`assertDatabaseMissing`) — none call `/stats`
  afterward.
- Stats have no cache or persisted aggregate to invalidate — `StatsController::index()`
  re-queries and re-sorts by `played_on DESC, id DESC` fresh on every request
  (`app/Http/Controllers/StatsController.php:13-16`). This was a deliberate
  design choice (`context/archive/2026-07-26-stats-dashboard/plan.md:56-60`),
  so there is no invalidation step to test — the gap is purely in coverage,
  not in behavior believed to be broken.

### Key discoveries:

- `StatsCalculator::forMatches()` (`app/Services/StatsCalculator.php:13-38`)
  computes `streak_result`/`streak_length` from `$matches->first()` and a
  `takeWhile()` (`:24-25`) — it trusts its input is already sorted
  newest-first and never sorts or verifies it itself. `StatsController`
  supplies that order today, but nothing makes the dependency explicit or
  regression-proof.
- The Blade view renders the streak as `"{length}× {letter}"` with
  `W`/`R`/`P` for win/draw/loss (`resources/views/stats/partials/stats-block.blade.php:5,20`),
  and an unlucky-fan tile with class `border-red-200` only when
  `is_unlucky_fan` is true (`:29-34`). All three tab panels (Ogółem/Dom/Wyjazd)
  render server-side in the same response — Alpine hides the inactive ones
  client-side, not server-side (confirmed by
  `context/archive/2026-07-26-home-away-stats-split/plan.md:227-234`) — so
  assertions must pick values that don't collide across tabs, following the
  existing `substr_count()` convention (`StatsTest.php:222,271,291`).
- `UpdateRequest::rules()` requires all four editable fields — `opponent`,
  `played_on`, `goals_for`, `goals_against` — on every `PATCH`
  (`app/Http/Requests/GameMatch/UpdateRequest.php:19-24`); `venue`,
  `distance_km`, `city` are immutable via edit.
- Delete is a hard delete with no soft-delete scope and no cascading
  aggregate (`app/Models/GameMatch.php:13-16`), so a deleted match cannot
  appear in the very next `/stats` query.

## Desired End State

`php artisan test --filter=StatsTest` includes 4 new test methods (2 in
Phase 1, 2 in Phase 2), all green, and `context/foundation/test-plan.md`
§6.2 and §6.5 document the "mutate then recheck `/stats`" pattern.
Verification: `php artisan test --filter=StatsTest` passes with the new
tests visible in the output, and `php artisan test` (full suite) stays
green.

### Key constraints (from `test-plan.md` §2 Risk Response Guidance for #3, post-research):

- Tests must not compute their expected value by calling `StatsCalculator`
  inside the test (tautological) — expected numbers are hand-derived in
  comments, matching the existing `StatsTest.php` convention.
- The defensive `StatsCalculator` order-dependency test is a deliberate,
  user-approved widening beyond Risk #3's literal edit/delete wording — it
  documents the sort-trust contract the two HTTP-level tests otherwise rely
  on implicitly.

## What We're NOT Doing

- Not changing `StatsCalculator`, `StatsController`, `GameMatchController`,
  or any view — test-only, per the Module 3 Lesson 1 change-lifecycle
  boundary.
- Not adding a test for deleting the only match of one venue (home or away
  reverting to its empty state) — user-deferred edge case, not required by
  Risk #3's wording; a candidate for a future phase or `lessons.md` note if
  it ever causes a real bug.
- Not re-testing owner isolation (another user's matches unaffected) for
  edit/delete — already covered for match mutation itself in
  `GameMatchTest.php:423-447`, and for stats rendering in
  `StatsTest.php:165-183,274-293`; this phase only closes the
  edit/delete-then-recheck-`/stats` gap, not isolation.
- Not adding `SoftDeletes` or any caching layer — out of scope for a
  test-only rollout phase, and contradicts the project's deliberate
  "recompute live" design.

## Implementation Approach

Both phases extend the existing `tests/Feature/StatsTest.php` (matches the
established convention of extending in place rather than creating a new
file, same as Phase 1's `GameMatchTest.php` extension). Phase 1 covers the
two HTTP-level scenarios the user confirmed as must-have from Risk #3
directly: an edit that flips a match's result, and an edit that changes
`played_on` enough to reorder the streak. Phase 2 covers the delete
scenario plus the user-approved defensive unit-level test for
`StatsCalculator`'s sort-order contract, then updates the cookbook.

## Phase 1: Edit-driven stats consistency

### Overview

Add two feature tests proving `/stats` reflects an edit to an existing
match without any extra user action: one where the edit changes the
match's *result* (balance + unlucky-fan), one where the edit changes
`played_on` enough to *reorder* the match relative to its siblings
(streak). Both are checked across all three tabs (Ogółem/Dom/Wyjazd) per
the user's explicit choice, since the reorder case is exactly where the
implicit sort-trust contract could silently break per-tab.

### Changes Required:

#### 1. Result-flip edit test

**File**: `tests/Feature/StatsTest.php`

**Purpose**: Prove that editing a match's score to flip its result (win →
loss) updates balance and the unlucky-fan flag on the very next `/stats`
view, across overall, home, and away tabs.

**Contract**: New test method, same class (`RefreshDatabase` already in
use). Fixture: one home match (win, `goals_for=2, goals_against=0`,
`distance_km=null`) and one away match (win, `goals_for=2,
goals_against=0`, `distance_km=100`). First `GET /stats` asserts the
baseline: `100%` appears 3× (overall + home + away all-wins) and
`border-red-200` does not appear. `PATCH` the away match to `goals_for=0,
goals_against=1` (keeping `opponent`/`played_on` unchanged, per the
existing `test_update_changes_opponent_date_and_score` pattern). Second
`GET /stats` asserts: `50%` (overall: 1 win + 1 loss), `100%` still present
once (home, unaffected — proves isolation held through the edit too), `0%`
(away), and `border-red-200` appears exactly once (only the away tab is
unlucky — overall is a tie, home has no losses).

**Anti-pattern avoided**: expected percentages are hand-derived from the
fixture's goals, not computed by calling `StatsCalculator` in the test.

#### 2. `played_on` reorder edit test

**File**: `tests/Feature/StatsTest.php`

**Purpose**: Prove that editing a match's `played_on` date, without
touching its result, correctly reorders it for streak computation on the
next `/stats` view — the one scenario where `StatsController`'s sort and
`StatsCalculator`'s trust in that sort must actually cooperate under
mutation.

**Contract**: New test method. Fixture (fixed dates, all `before_or_equal:today`
since "today" is after 2026-07):

| Match | Venue | `played_on` | Result |
|---|---|---|---|
| H1 | home | 2026-01-10 | win |
| H2 | home | 2026-01-05 | loss |
| A1 | away | 2026-01-08 | loss |
| A2 | away | 2026-01-03 | win |

Baseline order (desc by `played_on`): H1, A1, H2, A2 overall; H1, H2 home;
A1, A2 away. Baseline streaks: overall `"1× W"`, home `"1× W"`, away
`"1× P"` — assert `substr_count($content, '1× W') === 2` and
`substr_count($content, '1× P') === 1`.

`PATCH` H2's `played_on` to `2026-01-12` (goals unchanged — still a loss),
keeping `opponent`/`goals_for`/`goals_against` the same. H2 is now the
overall- and home-tab newest match. New order: H2, H1, A1, A2 overall; H2,
H1 home; A1, A2 away (unchanged). New streaks: overall `"1× P"`, home
`"1× P"`, away `"1× P"` (unchanged) — assert `substr_count($content, '1×
W') === 0` and `substr_count($content, '1× P') === 3`.

This single before/after pair proves three things at once: the edit is
picked up without extra action, the reorder propagates correctly to both
the overall and home tabs (the two tabs H2 belongs to), and the away tab
correctly stays unaffected (isolation held under an edit, not just under
creation).

**Anti-pattern avoided**: streak letters/lengths are hand-derived from the
table above, not computed by calling `StatsCalculator` in the test.

### Success Criteria:

#### Automated Verification:

- `php artisan test --filter=StatsTest` passes, including both new tests
- Full suite passes: `php artisan test`

#### Manual Verification:

- Read both new tests and confirm the expected percentages/streak strings
  in the assertions match the hand-derived values in this plan (not values
  computed by calling `StatsCalculator`)
- Confirm the reorder test's fixture dates are genuinely in the past
  relative to whenever it runs (fixed 2026 dates, `before_or_equal:today`
  validation) — re-check if this plan is implemented after 2026 ends

---

## Phase 2: Delete-driven consistency, sort-contract regression test, cookbook update

### Overview

Add one feature test proving `/stats` reflects a deleted match without
extra action (balance + unlucky-fan, overall tab per the user's choice —
the simpler of the two mutation types), one unit-level regression test that
makes `StatsCalculator`'s "caller must supply sorted input" contract
explicit and test-protected, then close the phase by updating the
cookbook.

### Changes Required:

#### 1. Delete test

**File**: `tests/Feature/StatsTest.php`

**Purpose**: Prove that deleting a match updates balance and the
unlucky-fan flag on the very next `/stats` view, with no extra user
action.

**Contract**: New test method. Fixture: three **away** matches only (no
home matches) — one win (`goals_for=2, goals_against=0, distance_km=10`)
and two losses (`goals_for=0, goals_against=1, distance_km=20` and
`distance_km=30`). Using away-only matches makes the overall and away tabs
share the same numbers by construction, so the assertions stay
unambiguous without needing to reason about a third differing value (home
tab shows its own empty state, contributing no percentage text to collide
with). Baseline: wins=1, losses=2, total=3 → `33%`,
`losses(2) > wins(1) && losses(2) > draws(0)` → unlucky. First `GET
/stats` asserts `substr_count($content, '33%') === 2` (overall + away) and
`substr_count($content, 'border-red-200') === 2`. `DELETE` one of the two
losing matches. Second `GET /stats` asserts: wins=1, losses=1, total=2 →
`50%` (`substr_count === 2`), and `border-red-200` no longer present
(losses no longer strictly exceed wins) — `assertDontSee('border-red-200')`.

**Anti-pattern avoided**: expected percentages/unlucky-fan outcome are
hand-derived from the fixture's goals, not computed by calling
`StatsCalculator` in the test.

#### 2. `StatsCalculator` sort-order-dependency test

**File**: `tests/Feature/StatsTest.php`

**Purpose**: Make the implicit "caller must supply matches sorted
newest-first" contract (`StatsCalculator.php:9-10`) explicit and
regression-protected, rather than leaving it as an unverified docblock
comment — the latent gap the two HTTP-level tests above rely on
`StatsController` never violating.

**Contract**: New test method, no DB/HTTP involved (same style as
`test_calculator_counts_results_and_win_percentage`). Build one two-match
collection representing a win followed by a loss. Call
`(new StatsCalculator)->forMatches($matches)` on it directly and assert
`streak_result === 'win'`. Call it again on the *same two matches in
reversed order* and assert `streak_result === 'loss'`. Identical
underlying matches, opposite `streak_result` purely from input order —
this documents, in an executable and regression-proof way, that
`StatsCalculator` does not sort its input and fully trusts the caller.

#### 3. Cookbook update

**File**: `context/foundation/test-plan.md`

**Purpose**: Record the "mutate via HTTP, then recheck `/stats`" pattern
this phase introduced, per the `/10x-test-plan` contract that each
rollout phase's final sub-phase updates the cookbook.

**Contract**: Append a bullet to §6.2 ("Adding a feature/integration
test") describing the mutate-then-recheck pattern: `actingAs($user)->patch(...)`
or `->delete(...)` against the match route, followed by a fresh
`GET /stats`, with expected balance/streak/unlucky-fan values hand-derived
in a comment — reference `tests/Feature/StatsTest.php`'s edit/delete
tests added in this phase. Add a one-line note under §6.5 ("Per-rollout-phase
notes") for this phase: date, that stats have no cache to invalidate so
these tests only need to prove the query/sort/compute chain holds under
mutation, and a pointer to the `StatsCalculator` sort-order-dependency test
as the reference for testing implicit ordering contracts elsewhere in the
codebase. Also update §3's Phase 2 row Status to `complete` once this
phase's progress checklist is fully `[x]`.

### Success Criteria:

#### Automated Verification:

- `php artisan test --filter=StatsTest` passes, including both new tests
- Full suite passes: `php artisan test`

#### Manual Verification:

- Read the delete test and the sort-order-dependency test and confirm
  neither computes its expected value by calling `StatsCalculator` for the
  balance/unlucky-fan test, and that the sort-order test's whole point
  *is* calling `StatsCalculator` directly (it's testing the service, not
  the HTTP layer — confirm this distinction is clear from the test name/comment)
- Read the updated `test-plan.md` §6.2 and §6.5 and confirm they point at
  real file:line-equivalent references, not placeholders

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich
automatycznych weryfikacji, zatrzymaj się tutaj po ręcznym potwierdzeniu.

---

## Testing Strategy

### Integration tests (all new coverage in this plan):

- Edit flips result → balance + unlucky-fan update on next `/stats`,
  across overall/home/away (Phase 1)
- Edit reorders `played_on` → streak updates on next `/stats`, across
  overall/home/away, with away as a same-test control that stays
  unaffected (Phase 1)
- Delete → balance + unlucky-fan update on next `/stats` (Phase 2)
- `StatsCalculator` streak depends entirely on caller-supplied order
  (unit-level regression test, Phase 2)

### Manual testing steps:

1. Run `php artisan test --filter=StatsTest` and confirm 22 tests pass (18
   existing + 4 new)
2. Read the 4 new tests and confirm none of them re-implements production
   logic to compute its own expected value (tautology check), except the
   sort-order test, whose entire point is calling `StatsCalculator`
   directly to characterize its current (undefended) behavior

## References

- Research: `context/changes/testing-stats-consistency-after-edit-delete/research.md`
- Existing per-tab independence pattern: `tests/Feature/StatsTest.php:185-293`
- Existing same-`played_on` tie-break pattern: `tests/Feature/StatsTest.php:102-123`
- Existing edit/delete HTTP pattern: `tests/Feature/GameMatchTest.php:348-421`
- Streak/unlucky-fan rendering: `resources/views/stats/partials/stats-block.blade.php:5,20,29-34`
- Sibling rollout phase (cookbook precedent): `context/changes/testing-geocoding-distance-coverage/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` once done.

### Phase 1: Edit-driven stats consistency

#### Automated

- [x] 1.1 `php artisan test --filter=StatsTest` passes, including both new tests
- [x] 1.2 Full suite passes: `php artisan test`

#### Manual

- [x] 1.3 Read both new tests and confirm the expected percentages/streak strings match the hand-derived values in this plan
- [x] 1.4 Confirm the reorder test's fixture dates are genuinely in the past relative to whenever it runs

### Phase 2: Delete-driven consistency, sort-contract regression test, cookbook update

#### Automated

- [x] 2.1 `php artisan test --filter=StatsTest` passes, including both new tests
- [x] 2.2 Full suite passes: `php artisan test`

#### Manual

- [x] 2.3 Read the delete test and the sort-order-dependency test and confirm the tautology distinction is clear
- [x] 2.4 Read the updated `test-plan.md` §6.2 and §6.5 and confirm they point at real references, not placeholders
