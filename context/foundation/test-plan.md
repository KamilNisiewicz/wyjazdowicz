# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-07-26

## 1. Strategy

Tests follow three non-negotiable principles for this project:

1. **Cost × signal.** The cheapest test that gives a real signal for the
   risk wins. Do not promote to e2e because e2e "feels safer." Do not put a
   vision model on top of a deterministic visual diff that already catches
   the regression.
2. **User concerns are first-class evidence.** Risks anchored in "the team
   is worried about X, and the failure would surface somewhere in area Y"
   carry the same weight as PRD lines or hot-spot data.
3. **Risks are scenarios, not code locations.** This plan documents *what
   could fail* and *why we believe it's likely* — drawn from documents,
   interview, and codebase *signal* (churn, structure, test base). It does
   NOT claim to know which line owns the failure. That knowledge is
   produced by `/10x-research` during each rollout phase. If the plan and
   research disagree about where the failure lives, research is the
   ground truth.

Hot-spot scope used for likelihood weighting: `app/`, `resources/`, `routes/`, `database/` (last 30 days).

## 2. Risk Map

The top failure scenarios this project must protect against, ordered by
risk = impact × likelihood. Risks are failure scenarios in user / business
terms, not test names. The Source column cites the *evidence that surfaced
this risk* — never a specific file as "where the failure lives" (that is
research's job, see §1 principle #3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | Auto-deploy-on-merge ships a bad change (e.g. a broken migration) straight to production with no staging tier and no atomic rollback | High | High | Interview Q3; `infrastructure.md` Risk Register (no atomic deploy, no staging) |
| 2 | Geocoding (Nominatim) returns an ambiguous or wrong city, so the away-match distance is computed against the wrong place | High | Medium | Interview Q1; PRD Guardrail (distance/balance correctness); `archive/2026-07-19-team-and-home-profile/plan.md` (real duplicate-city bug already hit) |
| 3 | Win/draw/loss balance, streak, or "unlucky fan" flag goes stale or wrong after a match is edited or deleted | High | Medium | PRD Guardrail (FR-008…FR-012); roadmap risk notes for S-04/S-05 (recalculation must stay consistent after edit/delete) |
| 4 | A Tailwind/Vite frontend build silently fails to compile newly-used utility classes, so the UI looks broken in production | Medium | High | Interview Q2; `archive/2026-07-26-edit-delete-match/plan.md` (this already happened once) |
| 5 | No centralized authorization policy — a future endpoint touching `GameMatch`/`Team` could skip user-scoping and expose another user's match (IDOR) | High | Low | PRD Guardrail (data privacy); hot-spot directory `app/Http/Controllers` (22 commits/30d) — abuse/security lens |
| 6 | The Nominatim external API fails (timeout, rate-limit, empty response) while adding an away match | Medium | Medium | Roadmap S-03 (Nominatim ~1 req/s rate-limit policy); partially mitigated already per archived S-03 triage |

**Impact × Likelihood rubric** (High / Medium / Low, coarse on purpose):

| Rating | Impact | Likelihood |
|--------|--------|------------|
| High   | user loses access, data, or trust in core numbers; failure is publicly visible | area changes weekly, or we have already been burned here |
| Medium | feature degrades, a workaround exists, only part of the flow affected | touched occasionally, has been a source of bugs |
| Low    | cosmetic, easily reverted, no data effect | stable code, rarely touched |

Note on excluded candidate: production-database-backup gaps (`infrastructure.md` Risk Register) are real but are an operational/ops task, not a testable code scenario this rollout can decompose into `/10x-research` → `/10x-plan` → `/10x-implement` phases — tracked separately, not in this map.

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | A merge with a failing test suite or a broken migration never reaches the `git pull`/`migrate --force` step on the server — the pipeline stops before anything changes on production | "Green `php artisan test` locally means the merge is safe" — there is today no CI workflow that runs the test suite as a gate before deploy, only the deploy workflow itself | Exact contents and step order of `.github/workflows/deploy.yml`; whether a test-running workflow exists at all; failure semantics (`set -e` vs `continue-on-error`) | Quality gate (CI job), not a unit test | A test that only validates pipeline YAML syntax instead of actually requiring the suite to pass before deploy |
| #2 | A user adding an away match in a city with multiple geocoding matches sees a candidate list to confirm (not silent auto-pick of the first result), and given the same confirmed candidate coordinates, the calculated distance is deterministic (repeat, independent live Nominatim searches are outside this codebase's control — no cache exists or is planned, so cross-request coordinate stability is not a testable/promised guarantee) | "Nominatim returned a result, so the result is correct" — the API can return duplicates, shifted coordinates, or an unrelated place with the same name | How `NominatimGeocoder` deduplicates today (already patched once for "Zabrze"), where user confirmation happens in the flow, whether distance is computed once at save time or recomputed on every view | Feature/integration test with `Http::fake()` simulating multiple/duplicate Nominatim results | A test that fakes exactly one clean Nominatim result (happy path only) and only asserts `distance_km` is non-null |
| #3 | After editing a match's result or deleting a match, `/stats` (overall and per home/away tab) immediately reflects the new/removed match in balance, streak, and unlucky-fan flag, with no extra user action | "Testing match creation is enough because `StatsCalculator` is stateless" — statelessness of the service doesn't guarantee the controller re-fetches fresh, correctly-ordered data after an edit that changes `played_on` | Whether any query/collection is cached across requests; how edit/delete trigger recalculation; whether changing `played_on` in an edit re-sorts correctly for streak | Feature test: edit a match affecting the streak/balance, re-fetch `/stats`, assert on manually-computed expected values; separate test for deletion | A test that computes its expected value by calling the same `StatsCalculator` method under test (tautological, mirrors implementation) |
| #4 | After adding a new Tailwind class to any Blade view and running `npm run build`, the compiled CSS actually contains that class — and CI/a local check flags a build run under the wrong Node version | "The Blade code is correct, so the style will work" — this exact assumption broke in S-04: correct code, dead build | Whether `.github/workflows/deploy.yml` already pins Node 20+/LTS for the build step; whether any local hook checks Node version before `npm run build` | Quality gate / deterministic check (grep compiled CSS for newly-used classes), not a classic unit test | Relying only on remembering to `export PATH=...v20...` by hand before every build (already failed once) |
| #5 | Every route touching `GameMatch` (existing and future) denies access (404, no existence leak) to a user who swaps in another user's ID — verifiable by one shared pattern, not per-endpoint memory | "Existing owner-isolation tests cover this forever" — they cover today's endpoints; a new endpoint written without awareness of the `$request->user()->gameMatches()` pattern could use `GameMatch::find($id)` directly and quietly break isolation | Every controller touching `GameMatch`/`Team` today; whether all consistently scope through the user relation; whether a Laravel `Policy` is worth adding as a systemic guard instead of relying on discipline | Existing feature tests (already present) + one contract-style test iterating over all `matches.*`/`stats.*` routes with another user's ID | A test that checks only one endpoint (e.g. edit) and calls the topic closed, while delete/view may have a different code path |
| #6 | When Nominatim doesn't respond, errors, or returns empty during away-match creation, the user sees a clear error message and can retry — the app never saves a match with `distance_km = null`/garbage data, nor throws a raw 500. Confirmed by research: the codebase deliberately collapses every failure mode (429, 500, timeout, malformed body, genuine empty result) to the same safe outcome — the test must prove that collapse holds for each variant, not that variants are handled differently | "One failure-path test (already added in S-03) covers this" — different failure modes (timeout, 429 rate-limit, empty JSON, malformed response) might degrade differently and that's untested | Current error handling in `NominatimGeocoder`; which cases are already tested; which are missing (429 specifically, given the ~1 req/s policy) | Feature test with `Http::fake()` simulating distinct response codes (timeout, 429, 500, empty JSON) on the away-match creation route, each asserting the same safe outcome | Asserting that different failure codes should produce different user-facing handling — the app intentionally does not distinguish them; testing for differentiation would test for behavior that isn't there |

## 3. Phased Rollout

Each row is a discrete rollout phase that will open its own change folder
via `/10x-new`. Status moves left-to-right through the values below; the
orchestrator updates Status as artifacts appear on disk.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Geocoding & distance coverage | Prove city resolution and Nominatim failure handling are correct | #2, #6 | integration (feature, `Http::fake()`) | complete | `context/changes/testing-geocoding-distance-coverage/` |
| 2 | Stats consistency after edit/delete | Prove balance/streak/unlucky-fan stay correct across edits and deletes | #3 | integration (feature) | complete | `context/changes/testing-stats-consistency-after-edit-delete/` |
| 3 | Ownership contract | One contract test across all match/stats routes instead of per-endpoint memory | #5 | integration (feature, contract-style) | not started | — |
| 4 | Quality-gates wiring | Require the test suite to pass before deploy; catch stale frontend builds | #1, #4 | gates (CI) | not started | — |

**Status vocabulary** (fixed): `not started` → `change opened` → `researched` → `planned` → `implementing` → `complete`.

## 4. Stack

The classic test base for this project. Recommendations below are grounded
in local manifests/configs plus the MCP/tools actually exposed in the
current session.

| Layer | Tool | Version | Notes |
|---|---|---|---|
| unit + integration | PHPUnit | 12.5.31 | `phpunit.xml`, SQLite in-memory (`DB_CONNECTION=sqlite`, `:memory:`), isolated from dev `.env` |
| framework | Laravel | 13.20.0 | PHP 8.3.32 locally; production runs PHP 8.5 (CloudLinux alt-php) |
| API mocking | `Illuminate\Support\Facades\Http::fake()` | built-in | Already used for Nominatim in existing tests (`NominatimGeocoderTest.php`, `GameMatchTest.php`) |
| e2e | none yet | — | Not planned this rollout — small server-rendered Blade UI; cost × signal did not justify a browser layer for a 4-phase rollout this size |
| accessibility | none yet | — | Not evaluated this rollout |
| CI test gate | GitHub Actions | n/a | Not wired yet — only the deploy workflow exists today; addressed by §3 Phase 4 |

**Stack grounding tools (current session):**
- Docs: none available in current session (no Context7/framework-docs MCP); checked: 2026-07-26
- Search: WebSearch tool available (generic web search), not used for this write-up since no stack-currency question arose; checked: 2026-07-26
- Runtime/browser: `claude-in-chrome` MCP available but not used — no e2e/browser layer proposed this rollout; checked: 2026-07-26
- Provider/platform: GitHub accessible via `gh` CLI (not an MCP tool); relevant to §3 Phase 4 (CI workflow inspection/wiring); checked: 2026-07-26

## 5. Quality Gates

The full set of gates that must pass before a change reaches production.
"Required for §3 Phase N" means the gate is enforced once that rollout
phase lands; before that, the gate is `planned`.

| Gate | Where | Required? | Catches |
|---|---|---|---|
| unit + integration (`php artisan test`) | local | required today (manual) | logic regressions |
| unit + integration in CI | CI on push to `master` | planned — required after §3 Phase 4 | logic regressions reaching production undetected |
| frontend build verification (Node 20+, new Tailwind classes compiled) | CI | planned — required after §3 Phase 4 | stale/broken builds (Risk #4) |
| owner-isolation contract test | local + CI (once wired) | planned — required after §3 Phase 3 | IDOR-style access regressions (Risk #5) |
| pre-deploy smoke (manual) | between merge + prod | optional | environment-specific failures not caught locally |

## 6. Cookbook Patterns

How to add new tests in this project. Each sub-section is filled in once
the relevant rollout phase ships; before that, the sub-section reads
"TBD — see §3 Phase N."

### 6.1 Adding a unit test

- TBD — see §3 Phase 1. Existing reference pattern: `tests/Unit/DistanceCalculatorTest.php`.

### 6.2 Adding a feature/integration test

- **Location**: `tests/Feature/`.
- **Pattern already in use**: `User::factory()->create()` + `Team::factory()->for($user)->create()` + `GameMatch::factory()->for($user)->create([...])`, assertions via `actingAs($user)->get(route(...))`.
- **Reference test**: `tests/Feature/GameMatchTest.php`, `tests/Feature/StatsTest.php`.
- **Run locally**: `php artisan test --filter=<TestName>`.
- **Pattern: mutate then recheck a derived view.** When a test needs to prove a
  read view (like `/stats`) reflects a prior mutation (edit/delete) without
  extra user action: `actingAs($user)->patch(...)`/`->delete(...)` against the
  mutating route, then a fresh `actingAs($user)->get(...)` on the read route,
  asserting hand-derived expected values before *and* after the mutation (not
  just after) so the assertion actually proves the change was caused by the
  mutation. When the read view renders multiple percentage/count tiles that
  could collide as substrings (e.g. `"0%"` inside `"100%"`), anchor the search
  with surrounding markup (e.g. `'>0%<'`) instead of a bare `assertSee`.
  Reference: `tests/Feature/StatsTest.php`'s
  `test_editing_match_result_updates_balance_and_unlucky_fan_across_tabs`,
  `test_editing_played_on_reorders_streak_across_tabs`, and
  `test_deleting_match_updates_balance_and_unlucky_fan_on_next_stats_view`.
  Checked: 2026-07-30 (rollout §3 Phase 2).

### 6.3 Adding a test that mocks an external API (Nominatim)

- **Service-level (unit)**: `tests/Unit/NominatimGeocoderTest.php` — mock `Http::fake(['nominatim.openstreetmap.org/*' => ...])` and call `NominatimGeocoder::search()` directly. Covers: happy path (multiple results), duplicate `display_name` dedup, malformed record skip, empty 200, generic 5xx, `ConnectionException`.
- **Feature-level (end-to-end through the route)**: `tests/Feature/GameMatchTest.php` — same `Http::fake()` pattern, but `actingAs($user)->post('/matches/search', [...])` and assert on the HTTP response instead of the service return value. Covers all of: empty result, 500, 429, timeout (`ConnectionException`), and malformed 200 body.
- **Design invariant to preserve in new tests**: `NominatimGeocoder` deliberately collapses every failure mode (429, 5xx, timeout, malformed body, genuine empty result) to the same `[]` → same controller behavior (generic `city`-field error, zero rows persisted, no raw 500). New failure-mode tests should assert this *same* outcome recurs — not that different failure codes get different handling, which the app does not do by design.
- Checked: 2026-07-29 (rollout §3 Phase 1).

### 6.4 Wiring a new CI quality gate

- TBD — see §3 Phase 4.

### 6.5 Per-rollout-phase notes

(Filled in as each phase lands.)

- **§3 Phase 2 (stats consistency after edit/delete), 2026-07-30**: Stats have
  no cache or persisted aggregate to invalidate (deliberate S-05 design), so
  these tests only needed to prove the query/sort/compute chain still holds
  under mutation — not to test any invalidation logic, which doesn't exist.
  The one real latent gap was an implicit contract: `StatsCalculator::forMatches()`
  trusts its input is pre-sorted newest-first and never verifies it
  (`app/Services/StatsCalculator.php:9-10`). For testing any other implicit
  ordering/precondition contract elsewhere in this codebase, see
  `tests/Feature/StatsTest.php::test_streak_result_depends_entirely_on_caller_supplied_order`
  as the reference pattern: feed the same input in two different orders and
  assert the output differs, making the undefended precondition explicit and
  regression-proof.

## 7. What We Deliberately Don't Test

No exclusions were agreed during the Phase 2 interview — the user explicitly
said "I'd like to test everything" in response to Q5. Given the project's
small surface (single-user MVP, no admin tools, no generated clients), this
is treated as a deliberate decision rather than scope creep: nothing is
carved out as intentionally untested. Re-evaluate at `--refresh` if new
low-value surface (e.g. a marketing page, an admin tool) is added later.

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-07-26
- Stack versions last verified: 2026-07-26
- AI-native tool references last verified: n/a (no AI-native layer in this rollout)

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
