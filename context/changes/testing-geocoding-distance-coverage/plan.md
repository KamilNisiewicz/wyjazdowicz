# Geocoding and distance coverage (test rollout Phase 1) Implementation Plan

## Overview

Close the two test-coverage gaps identified by `research.md` for rollout
Phase 1 of `context/foundation/test-plan.md`: (1) no test proves distance is
computed *deterministically* from confirmed candidate coordinates (Risk #2),
and (2) three of the four Nominatim failure variants (429, timeout,
malformed response) are not exercised at the feature level, only the happy
path and a generic 500 (Risk #6). No production code changes — this phase
only adds tests.

## Current State Analysis

- `tests/Unit/NominatimGeocoderTest.php` (95 lines, 6 tests) already covers
  the geocoder in isolation: happy path, duplicate dedup, malformed-record
  skip, empty result, 500, connection exception. All correctly assert `[]`.
- `tests/Feature/GameMatchTest.php` (356 lines, 19 tests) covers the
  controller/request layer: home-match shortcut (no Nominatim call),
  away-match candidate display, away-match store with a distance-range
  assertion, empty-result validation error, one generic 500 validation
  error, and out-of-range candidate-index rejection.
- Nothing today asserts that two *separate* away-match submissions using
  identical candidate coordinates produce identical `distance_km` — the
  existing distance test only checks a single match falls in an expected
  range (`GameMatchTest.php:84-113`).
- Nothing today exercises a 429 (rate-limit) response, a connection
  timeout, or a malformed-but-200 body at the **feature** level (i.e.
  through `/matches/search`, not just the unit-level geocoder) — only the
  generic 500 case is proven end-to-end (`GameMatchTest.php:137-157`).

### Key discoveries:

- `NominatimGeocoder::search()` (`app/Services/NominatimGeocoder.php:13-47`)
  collapses every failure mode to `[]` — there is no branch to bypass, so
  all new failure-variant tests share the exact same assertion shape as the
  existing 500 test.
- `GameMatchController::store()` (`app/Http/Controllers/GameMatchController.php:66-90`)
  never re-queries Nominatim — `distance_km` is computed purely from the
  candidate `lat`/`lon` submitted in the request, via
  `DistanceCalculator::kilometersBetween()` (`app/Services/DistanceCalculator.php:7-20`),
  a pure function. This is what makes the determinism test meaningful: it
  proves the *calculation path* is deterministic, not that live Nominatim
  is (research explicitly found no cache exists or is planned — that
  broader claim is out of scope, see `research.md` correction #1).

## Desired End State

`php artisan test` includes 4 new test methods (1 in Phase 1, 3 in Phase 2)
in `tests/Feature/GameMatchTest.php`, all green, and
`context/foundation/test-plan.md` §6.3 documents the mocking-external-API
pattern with a concrete reference test. Verification: `php artisan test
--filter=GameMatchTest` passes with the new tests visible in the output.

### Key constraints carried from `test-plan.md` §2 (post-research):

- Risk #2 test must NOT claim two independent live Nominatim searches
  return identical coordinates — only that identical *submitted* candidate
  coordinates produce an identical computed distance.
- Risk #6 tests must NOT assert differentiated handling per failure code —
  the app deliberately treats all four variants identically. The test set
  must instead demonstrate the *same* safe outcome recurs across all of
  them.

## What We're NOT Doing

- Not changing `NominatimGeocoder`, `GameMatchController`, or any view —
  this phase is test-only, per the Module 3 Lesson 1 change-lifecycle
  boundary (implementation/bug-fixing is Lesson 5, TDD is Lesson 2).
- Not rewording the "city not found" message even though research flagged
  it as misleading during real API failures — tracked as an open product
  question in `research.md`, deliberately left for a future change, not
  this test-only rollout phase.
- Not adding a shared Nominatim response-fixture helper/trait — four new
  tests don't yet justify the abstraction; existing tests already inline
  `Http::fake()` payloads and this phase follows the same convention.
- Not testing cross-request Nominatim coordinate stability (no cache
  exists or is planned to make that true — see `research.md` correction #1).

## Implementation Approach

Both phases extend the existing `tests/Feature/GameMatchTest.php` (user's
confirmed choice over a new dedicated file) using the established
`Http::fake()` + `actingAs()` + `assertDatabaseCount`/`assertSessionHasErrors`
pattern already used by the file's 19 existing tests. No new test
infrastructure, factories, or helpers are needed.

## Phase 1: Distance determinism coverage (Risk #2)

### Overview

Add one feature test proving that two away-match submissions using
identical confirmed candidate coordinates compute an identical `distance_km`
— closing the gap the plan's corrected Risk #2 guidance calls for.

### Changes Required:

#### 1. New determinism test

**File**: `tests/Feature/GameMatchTest.php`

**Purpose**: Prove that `store()`'s distance calculation is deterministic
given fixed candidate coordinates — the behavior the plan actually promises,
scoped down from the original (untestable) claim about live Nominatim
stability across independent requests.

**Contract**: New test method (place near
`test_away_match_store_creates_match_with_calculated_distance`, same test
class, same `RefreshDatabase` trait already in use). Submit two separate
`POST /matches` requests for the same user, each with a `candidates` array
containing the *same* `display_name`/`lat`/`lon` values (no `Http::fake()`
needed — `store()` never calls Nominatim). Assert both resulting
`GameMatch` rows have the same `distance_km`. The oracle is the equality
between the two independently-created rows, not a hand-computed expected
km value — the existing range-based test
(`GameMatchTest.php:84-113`) already covers correctness of the haversine
result; this test covers only consistency, so it must not duplicate that
assertion.

**Anti-pattern avoided**: comparing the result to a value derived by
calling `DistanceCalculator` directly (tautological — would validate the
implementation against itself, not against independently-known behavior).

### Success Criteria:

#### Automated Verification:

- `php artisan test --filter=GameMatchTest` passes, including the new test
- Full suite passes: `php artisan test`

#### Manual Verification:

- Read the new test and confirm its two `distance_km` values come from two
  independently-created database rows (not the same row read twice)

---

## Phase 2: Nominatim failure-variant coverage (Risk #6)

### Overview

Add three feature tests — 429 rate-limit, connection timeout, and a
malformed-but-200 response — mirroring the existing 500 test's shape, so
the plan's "every failure variant degrades to the same safe outcome" claim
is actually demonstrated end-to-end rather than assumed from the unit-level
geocoder tests alone. Close the phase by updating the test-plan cookbook.

### Changes Required:

#### 1. 429 rate-limit feature test

**File**: `tests/Feature/GameMatchTest.php`

**Purpose**: Prove a 429 response from Nominatim during away-match search
produces the same safe outcome as any other failure — directly relevant
given the roadmap's documented ~1 req/s Nominatim policy (this is the one
variant research flagged as genuinely untested anywhere, unit or feature).

**Contract**: Same shape as `test_away_match_search_shows_validation_error_when_nominatim_fails`
(`GameMatchTest.php:137-157`), with `Http::fake([... => Http::response(null, 429)])`.
Assert `assertSessionHasErrors('city')` and `assertDatabaseCount('game_matches', 0)`.

#### 2. Connection-timeout feature test

**File**: `tests/Feature/GameMatchTest.php`

**Purpose**: Prove a timeout/connection failure during the *feature* flow
(not just the already-tested unit-level geocoder) produces the same safe
outcome — the controller must not leak a raw exception or 500 to the user.

**Contract**: Same assertion shape, with
`Http::fake(['nominatim.openstreetmap.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')])`
(mirrors `NominatimGeocoderTest.php:85-94`, now exercised through the HTTP
route instead of the service directly).

#### 3. Malformed-response feature test

**File**: `tests/Feature/GameMatchTest.php`

**Purpose**: Prove a 200 response whose body doesn't match the expected
shape (e.g. missing `lat`/`lon`) is filtered down to an empty result and
produces the same safe outcome, not a 500 — this variant is filtered
inside `NominatimGeocoder::search()` (`:31-32`) but was never proven at the
feature level.

**Contract**: Same assertion shape, with
`Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Rekord bez współrzędnych']], 200)])`
(reuses the malformed fixture shape from
`NominatimGeocoderTest.php:47-61`, now exercised end-to-end).

**Anti-pattern avoided** (all three): asserting that different failure
codes should produce *different* handling. All three tests must use the
identical two-assertion shape as the existing 500 test — the point is
sameness across variants, not differentiation.

#### 4. Cookbook update

**File**: `context/foundation/test-plan.md`

**Purpose**: Fill in §6.3 ("Adding a test that mocks an external API") now
that this rollout phase has shipped a concrete, complete example, per the
`/10x-test-plan` contract that each phase's final sub-phase updates the
cookbook.

**Contract**: Replace the `TBD — see §3 Phase 1` line in §6.3 with:
location (`tests/Feature/GameMatchTest.php` for end-to-end,
`tests/Unit/NominatimGeocoderTest.php` for service-level), the
`Http::fake()` pattern for simulating each failure shape (success,
duplicate, malformed, empty, 5xx, 429, connection exception), and a
one-line note that all Nominatim failure variants are expected to degrade
to the same safe outcome by design — differentiated handling is not a goal.
Also update §3's Phase 1 row Status to `complete` once this phase's
progress checklist is fully `[x]`.

### Success Criteria:

#### Automated Verification:

- `php artisan test --filter=GameMatchTest` passes, including all 3 new tests
- Full suite passes: `php artisan test`

#### Manual Verification:

- Read all three new tests side by side and confirm they share the same
  assertion shape (no per-variant special-casing crept in)
- Read the updated `test-plan.md` §6.3 and confirm it points at real
  file:line-equivalent references, not placeholders

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich
automatycznych weryfikacji, zatrzymaj się tutaj po ręcznym potwierdzeniu.

---

## Testing Strategy

### Integration tests (all new coverage in this plan):

- Distance determinism: two identical-coordinate submissions → equal
  `distance_km` (Phase 1)
- Nominatim 429 → same safe outcome (Phase 2)
- Nominatim connection timeout → same safe outcome (Phase 2)
- Nominatim malformed 200 body → same safe outcome (Phase 2)

### Manual testing steps:

1. Run `php artisan test --filter=GameMatchTest` and confirm 23 tests pass
   (19 existing + 4 new)
2. Read the 4 new tests and confirm none of them re-implements production
   logic to compute its own expected value (tautology check)

## References

- Research: `context/changes/testing-geocoding-distance-coverage/research.md`
- Existing failure-path pattern: `tests/Feature/GameMatchTest.php:137-157`
- Existing unit-level failure coverage: `tests/Unit/NominatimGeocoderTest.php:47-94`
- Distance calculation: `app/Services/DistanceCalculator.php:7-20`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` once done.

### Phase 1: Distance determinism coverage (Risk #2)

#### Automated

- [x] 1.1 `php artisan test --filter=GameMatchTest` passes, including the new test — 14e1203
- [x] 1.2 Full suite passes: `php artisan test` — 14e1203

#### Manual

- [x] 1.3 Read the new test and confirm its two `distance_km` values come from two independently-created database rows — 14e1203

### Phase 2: Nominatim failure-variant coverage (Risk #6)

#### Automated

- [x] 2.1 `php artisan test --filter=GameMatchTest` passes, including all 3 new tests — 14e1203
- [x] 2.2 Full suite passes: `php artisan test` — 14e1203

#### Manual

- [x] 2.3 Read all three new tests side by side and confirm they share the same assertion shape — 14e1203
- [x] 2.4 Read the updated `test-plan.md` §6.3 and confirm it points at real references, not placeholders — 14e1203
