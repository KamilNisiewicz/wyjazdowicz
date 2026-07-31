# Ownership contract test (GameMatch ID-taking routes) Implementation Plan

## Overview

Implement rollout Phase 3 of `context/foundation/test-plan.md` (Risk #5): a
single, data-provider-driven contract test proving every route that accepts a
`GameMatch` ID from the client denies access to a user who swaps in another
user's ID — so a future ID-taking route is a one-line array addition, not a
remembered new test method.

## Current State Analysis

Per `context/changes/testing-ownership-contract/research.md`:

- Exactly 3 routes accept a resource ID touching `GameMatch`/`Team`:
  `matches.edit` (GET), `matches.update` (PATCH), `matches.destroy` (DELETE)
  — all in `app/Http/Controllers/GameMatchController.php:92-113`. Every other
  route (`matches.index/search/store`, `stats.index`, `team.*`) takes no
  resource ID and is structurally immune to an ID-swap attack.
- All 3 routes already scope through `$request->user()->gameMatches()->findOrFail($match)`
  — no `app/Policies`, no `Gate::` calls exist in the project (confirmed by research).
- An isolation test already exists —
  `tests/Feature/GameMatchTest.php:423-447`,
  `test_user_cannot_edit_view_or_delete_another_users_match` — as 3 inline
  assertions in one method. It passes today. The gap is not missing coverage;
  it's that nothing forces a future ID-taking route to be added to this
  specific method.
- No production code changes are implied — the query-scoping pattern is
  already correct.

### Key Discoveries:

- `GameMatchController.php:92,99,107` types the route param as `int $match`,
  not `GameMatch $match` — Laravel's implicit route-model binding never
  engages, so nothing can silently bypass the `$request->user()->gameMatches()`
  scope (`research.md` "Architecture Insights").
- The project's test suite has no prior use of PHPUnit data providers
  (`grep -rn "DataProvider" tests/` returns nothing) — this introduces the
  pattern for the first time; use the modern attribute form
  (`#[\PHPUnit\Framework\Attributes\DataProvider]`), consistent with
  `phpunit/phpunit: ^12.5.12` (`composer.json:21`).
- Existing test style (`tests/Feature/GameMatchTest.php:1-20`): `RefreshDatabase`
  trait, `User::factory()->create()` + `Team::factory()->for($user)->create()`
  + `GameMatch::factory()->for($user)->create([...])`, snake_case
  `test_*` method names — the new file follows the same conventions.

## Desired End State

`tests/Feature/OwnershipContractTest.php` exists as the canonical home for
this risk: a static data provider listing every ID-taking `GameMatch` route
as `[routeName, httpMethod]`, and one test method that, for each entry,
creates an owner + an intruder, hits the route as the intruder with the
owner's match ID, and asserts 404 + no DB mutation for the two mutating
routes. `test-plan.md` §3 Phase 3 status is `complete`, §6 documents the
pattern under a new "Adding a route that takes a resource ID" cookbook
sub-section (or extending §6.2), so a future contributor knows to add one
line to the provider array instead of writing a new test method.

**Verification**: `php artisan test --filter=OwnershipContractTest` passes;
`php artisan test` full suite passes (currently 87/87 — 83 existing + 4 net
new/removed: -1 removed inline method, +3 provider cases, net effect on count
depends on how PHPUnit reports parameterized cases, verify via `--testdox`).

### Key discoveries used to verify success:

- The old test's DB-integrity assertion (`GameMatchTest.php:443-446`,
  `assertDatabaseHas` with unchanged `opponent`) must be preserved for the
  mutating routes (`matches.update`, `matches.destroy`) per the user's
  decision to keep 404 + no-mutation signal, not just 404.

## What We're NOT Doing

- No production code changes (scoping pattern already correct, per research).
- No coverage of `matches.index/search/store`, `stats.index`, or `team.*` —
  they take no resource ID, so an "another user's ID" attack has no surface
  there (explicitly named anti-pattern to avoid, per test-plan.md §2).
- No new Policy/Gate class — out of scope for this phase; the plan proves
  the existing query-scoping discipline holds, it doesn't change the
  authorization architecture.
- No test for the guest/unauthenticated case — already covered by
  `test_guest_is_redirected_to_login_for_edit_update_destroy`
  (`GameMatchTest.php:449-458`), a different concern (missing session vs.
  cross-user ID swap), left untouched.

## Implementation Approach

Single new feature test file, one data provider, one parameterized test
method. The old inline-assertion method is deleted from `GameMatchTest.php`
once its coverage is fully subsumed, so there is exactly one place that knows
about this risk (the point of the phase). `test-plan.md` §3/§6 updated last,
after the test is green, so the cookbook entry only documents a shipped
pattern rather than an aspirational one.

## Phase 1: Ownership contract test

### Overview

Create the data-provider-driven contract test and remove the now-redundant
inline test from `GameMatchTest.php`.

### Changes Required:

#### 1. New contract test file

**File**: `tests/Feature/OwnershipContractTest.php`

**Purpose**: Prove, for every `GameMatch` route that accepts a client-supplied
resource ID, that a different authenticated user is denied access (404, no
existence leak) and cannot mutate the owner's record — verifiable by adding
one array entry, not one test method, when a future ID-taking route ships.

**Contract**:
- Class `Tests\Feature\OwnershipContractTest extends Tests\TestCase`, `use RefreshDatabase`.
- A static data provider method (e.g. `idTakingRoutes(): array`) returning
  one entry per ID-taking route, keyed by a readable label for PHPUnit's
  test name output. Each entry carries: HTTP verb, a closure or route-name
  template that builds the URL from a `$match->id`, and whether the route is
  mutating (so the test method knows whether to also assert DB-unchanged).
  Concretely, three entries for `matches.edit` (GET), `matches.update`
  (PATCH, with a representative valid-shaped payload so the request would
  succeed as a 302 *if* scoping failed — this makes the 404 assertion a real
  proof, not a vacuous one because validation rejected the request first),
  `matches.destroy` (DELETE).
- `#[\PHPUnit\Framework\Attributes\DataProvider('idTakingRoutes')]` on the
  test method, matching the project's PHPUnit 12.5 dependency.
- Test method signature accepts the provider's tuple, builds `$owner` +
  `Team::factory()->for($owner)->create()` + `GameMatch::factory()->for($owner)->create()`,
  builds `$intruder` + `Team::factory()->for($intruder)->create()` (an
  intruder needs a team too, since `team.set` middleware gates the group —
  confirm by reading `routes/web.php:27` middleware group), issues the
  request as `$intruder` against the owner's match ID, asserts
  `assertNotFound()`, and for mutating routes additionally asserts
  `assertDatabaseHas('game_matches', ['id' => $match->id, 'opponent' => $match->opponent])`
  to prove no mutation occurred (mirrors `GameMatchTest.php:443-446`).
- Doc comment above the data provider (one line) explicitly instructs: "Add a
  new entry here when a future route accepts a GameMatch (or other owned
  model) ID from the client" — this is the operative artifact the cookbook
  entry in Phase 2 points back to.

#### 2. Remove now-redundant inline test

**File**: `tests/Feature/GameMatchTest.php`

**Purpose**: Eliminate the duplicate/superseded coverage so there is exactly
one place this risk is tested (per the user's decision to delete rather than
keep both).

**Contract**: Delete `test_user_cannot_edit_view_or_delete_another_users_match`
(`GameMatchTest.php:423-447`) in full. Leave
`test_guest_is_redirected_to_login_for_edit_update_destroy`
(`GameMatchTest.php:449-458`) untouched — different concern, not superseded.

### Success Criteria:

#### Automatic Verification:

- [ ] New file passes in isolation: `php artisan test --filter=OwnershipContractTest`
- [ ] Full suite passes with no regressions: `php artisan test`
- [ ] `tests/Feature/GameMatchTest.php` no longer contains `test_user_cannot_edit_view_or_delete_another_users_match`: `grep -c test_user_cannot_edit_view_or_delete_another_users_match tests/Feature/GameMatchTest.php` returns `0`

#### Manual Verification:

- [ ] Read the PHPUnit output (`php artisan test --filter=OwnershipContractTest --testdox`) and confirm it reports 3 distinct labeled cases (one per route), not a single opaque pass/fail

---

## Phase 2: Cookbook update

### Overview

Document the shipped pattern in `test-plan.md` §6 and close out Phase 3 in §3.

### Changes Required:

#### 1. Cookbook entry

**File**: `context/foundation/test-plan.md`

**Purpose**: Give future contributors (and `/10x-tdd` in Lesson 2) a named
place to look when adding a new endpoint that takes an owned-resource ID.

**Contract**: Add a new §6 sub-section (after §6.3, renumbering §6.4/§6.5
onward by one, or insert as new §6.4 and shift "Wiring a new CI quality
gate" to §6.5 / "Per-rollout-phase notes" to §6.6 — whichever preserves the
existing numbering convention most cleanly) titled "Adding a test for a route
that takes an owned resource's ID", pointing at
`tests/Feature/OwnershipContractTest.php`'s data provider as the single place
to add a new `[route, verb]` entry, with the "Checked: 2026-07-31 (rollout §3
Phase 3)" convention matching §6.2/§6.3's existing footer style. Also append a
`§6.5 Per-rollout-phase notes` (or renumbered equivalent) bullet for Phase 3
following the existing Phase 2 bullet's style, describing the "3
ID-taking-routes-only" scoping correction from research as the reusable
insight (why the naive "iterate all match/stats routes" framing was wrong).

#### 2. Close out §3 status

**File**: `context/foundation/test-plan.md`

**Purpose**: Let the `/10x-test-plan` orchestrator advance to Phase 4 on next invocation.

**Contract**: §3 Phase 3 row: `Status` → `complete`.

### Success Criteria:

#### Automatic Verification:

- [ ] `test-plan.md` §3 Phase 3 row reads `complete`: `grep -A1 "Ownership contract" context/foundation/test-plan.md | grep -q complete`

#### Manual Verification:

- [ ] Cookbook entry reads clearly as a standalone "how do I add this kind of test" answer, without needing to open `plan.md` for context

---

## Testing Strategy

### Integration tests:

- The 3-entry data provider IS the test strategy for this phase — no
  additional unit tests needed since the risk is specifically about
  HTTP-layer access control, not internal logic.

### Manual Testing Steps:

1. Run `php artisan test --filter=OwnershipContractTest --testdox` and read
   the 3 labeled case names to confirm they're readable or meaningful.
2. Manually add a 4th throwaway entry to the provider (e.g. duplicate
   `matches.edit`) locally, confirm it produces a 4th passing case with zero
   other code changes, then revert — proves the "one-line addition" claim
   before committing it to the cookbook.

## References

- Related research: `context/changes/testing-ownership-contract/research.md`
- Superseded test: `tests/Feature/GameMatchTest.php:423-447`
- Existing scoping pattern: `app/Http/Controllers/GameMatchController.php:92-113`
- Prior phase's cookbook footer style: `context/foundation/test-plan.md` §6.2/§6.3

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Ownership contract test

#### Automatic

- [x] 1.1 New file passes in isolation: `php artisan test --filter=OwnershipContractTest` — 72cb672
- [x] 1.2 Full suite passes with no regressions: `php artisan test` — 72cb672
- [x] 1.3 `tests/Feature/GameMatchTest.php` no longer contains `test_user_cannot_edit_view_or_delete_another_users_match` — 72cb672

#### Manual

- [x] 1.4 PHPUnit testdox output confirms 3 distinct labeled cases — 72cb672

### Phase 2: Cookbook update

#### Automatic

- [x] 2.1 `test-plan.md` §3 Phase 3 row reads `complete` — 72cb672

#### Manual

- [x] 2.2 Cookbook entry reads clearly as a standalone "how do I add this kind of test" answer — 72cb672
