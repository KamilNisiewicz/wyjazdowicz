---
date: 2026-07-29T15:06:58+02:00
researcher: Kamil Nisiewicz
git_commit: 2edcb09dc4b24637ec0bd591690f24613c534135
branch: master
repository: KamilNisiewicz/wyjazdowicz
topic: "Test rollout Phase 1 — Geocoding & distance coverage (Risks #2, #6)"
tags: [research, codebase, geocoding, nominatim, distance, game-match]
status: complete
last_updated: 2026-07-29
last_updated_by: Kamil Nisiewicz
---

# Research: Test rollout Phase 1 — Geocoding & distance coverage

**Date**: 2026-07-29T15:06:58+02:00
**Researcher**: Kamil Nisiewicz
**Git Commit**: 2edcb09dc4b24637ec0bd591690f24613c534135
**Branch**: master
**Repository**: KamilNisiewicz/wyjazdowicz

## Research Question

Ground rollout Phase 1 of `context/foundation/test-plan.md` (Risks #2 and #6):
where does the real failure path live in code for (a) ambiguous/wrong-city
geocoding and (b) Nominatim API failure during away-match creation, what
already has test coverage, what is the cheapest useful test layer for the
remaining gaps, and does the plan's Risk Response Guidance survive contact
with the actual implementation?

## Summary

Both risks are grounded in a small, already-partially-tested surface:
`app/Services/NominatimGeocoder.php` (the only caller of the external API),
`app/Http/Controllers/GameMatchController.php@search/store`, and
`app/Http/Requests/GameMatch/{SearchCityRequest,StoreRequest}.php`. Existing
tests (`tests/Unit/NominatimGeocoderTest.php`,
`tests/Feature/GameMatchTest.php`) already cover the happy path, empty
results, generic 500, and a connection exception — all asserting the
geocoder degrades to `[]` and the controller then shows a generic
`city`-field error with nothing persisted. Two of the plan's Risk Response
Guidance claims need correction before `/10x-plan` writes sub-phases (see
"Corrections to Risk Response Guidance" below); everything else in §2 holds.

## Detailed Findings

### Risk #2 — Ambiguous/wrong city geocoding

- `NominatimGeocoder::search()` (`app/Services/NominatimGeocoder.php:13-47`)
  queries Nominatim fresh on every call, filters malformed records, and
  de-duplicates by `display_name` (`:41`) — keeping the *first* of any
  same-name duplicates (a real, previously-hit bug for "Zabrze", already
  fixed and regression-tested: `tests/Unit/NominatimGeocoderTest.php:29-45`).
- The controller never auto-picks a candidate: `search()`
  (`GameMatchController.php:28-64`) always returns a candidate list to
  confirm when `venue === 'away'`, rendered by
  `resources/views/matches/candidates.blade.php:31-39` as radio buttons with
  each candidate's `display_name`/`lat`/`lon` embedded as hidden fields.
  `store()` (`GameMatchController.php:66-90`) computes `distance_km` from
  whichever candidate's hidden-field coordinates were submitted — it never
  re-queries Nominatim at save time.
- `StoreRequest` (`app/Http/Requests/GameMatch/StoreRequest.php:25`)
  validates `candidate` is `Rule::in(array_keys($this->input('candidates')))`
  — already regression-tested for an out-of-range index
  (`tests/Feature/GameMatchTest.php:159-177`, fixed per
  2026-07-19 impl-review F2, per the archive-history agent's findings below).
- **No caching layer exists anywhere in the geocoding path** — confirmed as
  a deliberate non-decision, not an oversight (see archive findings below).
  This means the distance calculation itself is deterministic given fixed
  inputs (same submitted `lat`/`lon` → same haversine result,
  `app/Services/DistanceCalculator.php:7-20`), but two *separate* away-match
  submissions for the same city, each triggering its own live `search()`
  call, are not guaranteed to receive identical Nominatim coordinates back —
  that variability lives entirely outside this codebase and is not something
  `Http::fake()` can meaningfully falsify either way.

### Risk #6 — Nominatim API failure

- `NominatimGeocoder::search()` collapses **every** failure mode — HTTP
  non-2xx (`response()->failed()`, `:26-28`, includes 429/500/503), a
  malformed-but-200 body (filtered by `:31-32`), and any `Throwable`
  including connection timeouts (`:44-46`) — into the same return value:
  `[]`. There is no branch anywhere that distinguishes a rate-limit (429)
  from a timeout from a genuinely-empty result once it reaches the
  controller.
- The controller (`GameMatchController.php:51-55`) treats an empty
  candidate array as one case: it shows
  `__('Nie znaleziono miasta o podanej nazwie...')` ("city not found")
  on the `city` field, regardless of whether Nominatim was actually down,
  rate-limited, or the city genuinely doesn't exist. No match row is created
  either way (validated by the request pipeline running before `create()`).
  **This is existing, deliberate-looking but undocumented behavior**: the
  user-facing message is worded as if the city query itself was bad, even
  when the real cause was an infrastructure failure. No test today asserts
  on the *specific* message text, only `assertSessionHasErrors('city')`.
- Existing coverage: `tests/Unit/NominatimGeocoderTest.php` covers empty
  200, 500, and `ConnectionException`, all asserting `[]`.
  `tests/Feature/GameMatchTest.php:137-157` covers one feature-level case
  (500 → session error + zero rows). **Not covered**: a 429 response
  specifically (relevant given the roadmap's documented ~1 req/s Nominatim
  policy), and no test asserts that a genuinely-empty result (city really
  doesn't exist) and an infra failure produce the *same* safe outcome
  end-to-end at the feature level — only the unit-level geocoder is proven
  to conflate them.

### Corrections to Risk Response Guidance (backport candidates for `/10x-test-plan`)

1. **Risk #2** — the plan's guidance says a test should prove "picking the
   same city twice yields the same distance." As implemented, this is only
   provable (and only meaningful) as: *given the same confirmed candidate
   coordinates, the calculated distance is deterministic* — i.e. a
   `DistanceCalculator`/store-level assertion, not a claim about two live
   Nominatim searches years apart returning identical coordinates (that is
   an external-system property this codebase cannot control or test, and no
   caching layer is planned to make it true). The guidance should be
   narrowed to avoid implying a testable guarantee that doesn't exist.

2. **Risk #6** — the plan's "avoid" anti-pattern warns against "mocking only
   the happy path plus one generic exception case without distinguishing
   response codes." In the actual implementation, the codebase *itself*
   deliberately does not distinguish response codes past the geocoder
   boundary — 429, 500, timeout, and empty-but-200 all collapse to the same
   `[]` → same generic user-facing error → same "nothing saved" outcome.
   The valuable test here is confirming that *every* failure variant
   (429, 500, timeout, malformed JSON) degrades to that one safe, identical
   outcome — not that the app responds differently per failure mode (it
   doesn't, and nothing in the PRD/interview asked it to). Testing "each
   code produces different handling" would be testing for behavior that
   isn't there and isn't wanted.

### Existing test base (already in place, no gap)

- `tests/Unit/NominatimGeocoderTest.php` (95 lines, 6 tests) — happy path,
  duplicate dedup, malformed-record skip, empty result, 500, connection
  exception. All at the service-unit level with `Http::fake()`.
- `tests/Feature/GameMatchTest.php` (356 lines, 19 tests) — covers home-match
  (no Nominatim call, asserted via `Http::assertNothingSent()`,
  `:31-58`), away-match candidate display (`:60-82`), away-match store with
  distance range assertion (`:84-113`), empty-result validation error
  (`:115-135`), 500-failure validation error (`:137-157`), out-of-range
  candidate index rejection (`:159-177`), plus unrelated CRUD/ownership
  tests (edit/update/destroy, cross-user 404s).

## Code References

- `app/Services/NominatimGeocoder.php:13-47` — sole Nominatim integration point; all failure modes collapse to `[]`
- `app/Http/Controllers/GameMatchController.php:28-64` — `search()`: venue branch, candidate-list vs. home-city shortcut, generic error message on empty candidates
- `app/Http/Controllers/GameMatchController.php:66-90` — `store()`: distance computed from submitted candidate coordinates, no re-query
- `app/Services/DistanceCalculator.php:7-20` — pure haversine function, deterministic given fixed inputs
- `app/Http/Requests/GameMatch/StoreRequest.php:25-29` — candidate-index and lat/lon range validation
- `app/Http/Requests/GameMatch/SearchCityRequest.php:17-27` — city required only when `venue=away`
- `resources/views/matches/candidates.blade.php:31-39` — candidate confirmation UI, hidden-field coordinate passthrough
- `tests/Unit/NominatimGeocoderTest.php:1-95` — full existing unit coverage of geocoder failure modes
- `tests/Feature/GameMatchTest.php:31-177` — full existing feature coverage of the away-match geocoding/store flow

## Architecture Insights

- The project consistently pushes "never throw" contracts down to the
  service layer (`NominatimGeocoder` returns `[]`, never an exception) and
  lets the controller/request-validation layer turn that into user-facing
  state — same pattern as `DistanceCalculator` (pure function, no I/O).
- Geocoding has no cache and no queue — everything is synchronous,
  request-scoped. This was a conscious scope decision at S-02 (deferred to
  S-03) and then reconfirmed as out-of-scope at S-03 (see below), not an
  oversight this rollout should treat as a code bug.

## Historical Context (from prior changes)

- `context/archive/2026-07-19-team-and-home-profile/plan.md:76,92` —
  original `NominatimGeocoder` contract: returns `[]` on no results or
  failure, never throws; unit tests planned for 2 valid results, empty
  array, 500/timeout.
- `context/archive/2026-07-19-team-and-home-profile/` impl-review F3 — found
  the `try/catch` didn't wrap the response-mapping step, so a
  malformed-but-200 body would throw; fixed, regression test added
  (`tests/Unit/NominatimGeocoderTest.php:47-61`).
- `context/archive/2026-07-19-team-and-home-profile/` plan-brief:23,27,33 —
  candidate-list-not-auto-pick rationale (typo/ambiguity must not silently
  corrupt future distance calculations); geocoding cache explicitly scoped
  **out** of S-02, deferred to S-03.
- `context/archive/2026-07-23-add-match-with-distance/plan.md:13` — S-03's
  own current-state read of the geocoder: "Nie cache'uje niczego —
  cache'owanie to obowiązek wywołującego (kolumny lat/lng na rekordzie)."
  I.e., S-03 consciously chose to store resolved coordinates per-match
  rather than build a shared geocoding cache — confirms no cache layer is
  planned anywhere in this project, now or later.
- `context/archive/2026-07-23-add-match-with-distance/` impl-review F2/F3 —
  home-match test originally lacked `Http::fake()`/`assertNothingSent()`
  (could have silently passed on a real network call); away-match flow
  lacked a dedicated 5xx regression test. Both fixed; both are the direct
  ancestors of the coverage now in `tests/Feature/GameMatchTest.php:31-58`
  and `:137-157`.
- `context/archive/2026-07-23-add-match-with-distance/` impl-review F5 —
  client-supplied candidate `lat`/`lon` are never re-verified server-side
  against Nominatim before persisting; explicitly accepted as consistent
  with the S-02 `Team` pattern for a single-user app, not a regression to
  fix in this rollout.
- `context/foundation/roadmap.md` S-03 risk note — documents Nominatim's
  ~1 req/s rate-limit policy and recommends caching to avoid re-querying on
  every *display* (view-time), not to guarantee identical results across
  independent *write*-time searches. The "same city geocoded twice returns
  different coordinates" scenario is not documented anywhere in the
  roadmap, PRD, or either archived plan — it is a genuine, previously
  unexamined gap, but one that cannot be closed by a test (no cache exists
  or is planned to make repeat searches deterministic); it can only be
  scoped correctly in the test (see correction #1 above).

## Related Research

None yet — this is the first `/10x-research` run for this change.

## Open Questions

- Should the misleading "city not found" message shown on *any* Nominatim
  failure (not just genuine no-results) be reworded to something failure-
  mode-agnostic but honest (e.g. "couldn't verify that city right now")?
  This is a product/UX call, not a testing decision — flagging for
  `/10x-plan` to decide whether it's in scope for this test-only rollout
  (likely not: this lesson chain writes tests, not fixes) or worth a
  follow-up change.
