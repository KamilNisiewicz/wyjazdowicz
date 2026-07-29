# Geocoding and distance coverage (test rollout Phase 1) — Short Plan

> Full plan: `context/changes/testing-geocoding-distance-coverage/plan.md`
> Research: `context/changes/testing-geocoding-distance-coverage/research.md`

## What and why

Add 4 missing feature tests closing the two test-coverage gaps rollout
Phase 1 of `context/foundation/test-plan.md` targets: distance-calculation
determinism (Risk #2) and full Nominatim failure-variant coverage (Risk #6).
No production code changes — this is a test-only phase.

## Starting point

`tests/Feature/GameMatchTest.php` (19 tests) and
`tests/Unit/NominatimGeocoderTest.php` (6 tests) already cover the happy
path, one generic 500 failure, and duplicate/malformed-record handling at
the unit level. Missing: a feature-level 429 test, a feature-level timeout
test, a feature-level malformed-response test, and any test proving
distance is computed consistently from fixed candidate coordinates.

## Desired end state

`php artisan test` has 23 tests in `GameMatchTest.php` (4 new), all green.
`test-plan.md` §6.3 documents the external-API-mocking pattern with a real
example instead of "TBD".

## Key decisions made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Misleading error message (found by research) | Test current behavior as-is | Rewording is a product fix, out of scope for a test-only lesson phase | Research + Plan |
| Test file organization | Extend `GameMatchTest.php` | Matches every prior slice's convention of extending the same feature-test file | Plan |
| Nominatim failure variants to cover | All 4 (429, 500 (existing), timeout, malformed) | Matches the exact claim in `test-plan.md` §2 that every variant degrades identically | Plan |
| Distance-determinism test shape | New feature-level test, two independent `store()` calls | Tests the real user flow (two matches, same city), not just the pure calculator function | Plan |

## Scope

**In scope:**
- 1 new feature test for distance determinism (Phase 1)
- 3 new feature tests for Nominatim failure variants: 429, timeout, malformed (Phase 2)
- `test-plan.md` §6.3 cookbook update (Phase 2, final step)

**Out of scope:**
- Any change to `NominatimGeocoder`, `GameMatchController`, or views
- Rewording the "city not found" error message (tracked as an open question in `research.md`, deferred)
- Cross-request Nominatim coordinate-stability testing (no cache exists or is planned — untestable/uncontrollable)
- A shared Nominatim fixture helper/trait (4 tests don't justify it yet)

## Architecture / Approach

Both phases add plain PHPUnit feature-test methods to the existing
`tests/Feature/GameMatchTest.php`, reusing the file's established
`Http::fake()` + `actingAs()` + `assertSessionHasErrors`/`assertDatabaseCount`
pattern. No new test infrastructure.

## Phases at a glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Distance determinism | 1 test proving same candidate coords → same `distance_km` | Could be written tautologically against `DistanceCalculator` — avoided by comparing two independently-created DB rows instead |
| 2. Nominatim failure variants | 3 tests (429/timeout/malformed) + `test-plan.md` §6.3 update | Could drift into asserting *different* handling per code — avoided by copying the exact same two-assertion shape across all variants |

**Prerequisites:** None — all target files and patterns already exist.
**Estimated effort:** ~1 session, 2 phases, ~4 new test methods total.

## Open risks and assumptions

- Assumes `Http::fake()` correctly intercepts a thrown `ConnectionException`
  the same way the existing unit test does (`NominatimGeocoderTest.php:85-94`)
  — already proven at unit level, expected to hold at feature level too.

## Success criteria (summary)

- `php artisan test` passes with 4 new tests, none of them tautological
  (verified by manual read, not just by passing)
- `test-plan.md` §6.3 no longer reads "TBD" and §3 Phase 1 status reads `complete`
