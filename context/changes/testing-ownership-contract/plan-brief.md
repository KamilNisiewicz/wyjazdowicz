# Ownership contract test — Short plan

> Full plan: `context/changes/testing-ownership-contract/plan.md`
> Research: `context/changes/testing-ownership-contract/research.md`

## What and why

Rollout Phase 3 of `test-plan.md` (Risk #5): prove every route that accepts a
`GameMatch` ID from the client denies cross-user access via one shared,
extensible test — not per-endpoint memory that a future route can slip past.

## Starting point

Research confirmed authorization is pure query scoping
(`$request->user()->gameMatches()->findOrFail($match)`), no Policy/Gate
exists, and it's already correct for all 3 ID-taking routes today. An
isolation test already exists (`GameMatchTest.php:423-447`) but as 3 inline
assertions in one fixed method — nothing forces a future ID-taking route to
be added to it.

## Desired end state

A new `tests/Feature/OwnershipContractTest.php` with a data provider listing
`[route, verb]` pairs for the 3 ID-taking routes. Adding a future route to
this protection is a one-line array addition, documented in `test-plan.md`
§6 as the canonical pattern.

## Key decisions made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Risk scope (§2 correction) | Only 3 ID-taking routes, not all match/stats routes | The rest have no resource ID param — nothing to swap, asserting there is coverage theater | Research |
| Test location | New file `tests/Feature/OwnershipContractTest.php` | Stable, discoverable home for the "contract" concept as the app grows beyond GameMatch | Plan |
| Assertion depth | 404 + DB-unchanged for mutating routes | Preserves the mutation-regression signal the current test already has | Plan |
| Old inline test | Deleted, not kept alongside | Two places to remember is exactly the anti-pattern this phase closes | Plan |

## Scope

**In scope:** one new test file, one data provider, deletion of the superseded
inline test, `test-plan.md` §3/§6 update.

**Out of scope:** production code (scoping is already correct), any route
without a client-supplied resource ID (`matches.index/search/store`,
`stats.index`, `team.*`), unauthenticated/guest access (already covered
elsewhere), a new Policy/Gate class.

## Architecture / Approach

One PHPUnit data-provider-driven feature test replaces three inline
assertions. Each provider entry is a `[routeName, httpMethod]` (plus a
mutating flag) tuple; the test method is generic over all entries — owner +
intruder + team setup, request as intruder against owner's match ID, assert
404, assert no DB mutation for PATCH/DELETE.

## Phases at a glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Ownership contract test | New `OwnershipContractTest.php`, old inline test removed | PHPUnit data-provider attribute syntax is new to this codebase — first use, verify against installed `phpunit/phpunit ^12.5.12` |
| 2. Cookbook update | `test-plan.md` §6 pattern + §3 status → `complete` | Section renumbering in §6 if inserted mid-list |

**Prerequisites:** none — pure test addition, no dependencies.
**Estimated effort:** ~1 session, 2 phases.

## Open risks and assumptions

- PHPUnit 12.5's `#[DataProvider]` attribute namespace/usage is assumed
  correct from framework version; verify with a quick local run before
  trusting the full suite.

## Success criteria (summary)

- `php artisan test` passes in full with the new contract test included and
  the old duplicate removed.
- A future ID-taking route can be protected by adding one array line, proven
  by the manual "add a throwaway 4th entry" check in Phase 1.
