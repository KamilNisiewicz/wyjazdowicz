---
date: 2026-07-31T20:31:06+02:00
researcher: Claude (10x-research)
git_commit: 69c26fef82994a66c7a0efa624a5d0d08bac5470
branch: master
repository: wyjazdowicz
topic: "Grounding rollout Phase 4 (Quality-gates wiring) — Risks #1 and #4"
tags: [research, codebase, ci, github-actions, deploy, tailwind, vite, node, phpunit]
status: complete
last_updated: 2026-07-31
last_updated_by: Claude (10x-research)
---

# Research: Grounding rollout Phase 4 (Quality-gates wiring)

**Date**: 2026-07-31T20:31:06+02:00
**Researcher**: Claude (10x-research)
**Git Commit**: 69c26fef82994a66c7a0efa624a5d0d08bac5470
**Branch**: master
**Repository**: wyjazdowicz

## Research Question

Ground rollout Phase 4 of `context/foundation/test-plan.md` ("Quality-gates wiring") for Risk #1 (auto-deploy-on-merge ships a bad change straight to production, no staging, no atomic rollback) and Risk #4 (a Tailwind/Vite frontend build silently fails to compile newly-used utility classes, so the UI looks broken in production). Verify the risk-response guidance against the real deploy pipeline, locate the exact gap, identify the cheapest test/gate layer, and flag any speculative or misleading evidence.

## Summary

- **Risk #1 confirmed exactly as described.** `.github/workflows/deploy.yml` has no test-running step of any kind. The workflow goes `checkout → setup PHP → setup Node → npm ci → npm run build → rsync assets → SSH: git pull, composer install, migrate --force, cache rebuilds`. Nothing runs `php artisan test` (or any test command) before the SSH block reaches `migrate --force` on the production database. The response guidance in the plan is correct and directly actionable: add a job/step that runs the test suite and gates the deploy job on it via `needs:`.
- **Risk #4's stated mechanism does not hold for the CI path — the plan's evidence needs a correction.** The real S-04 incident (`context/archive/2026-07-26-edit-delete-match/plan.md:113`, `reviews/impl-review.md:29`) happened during **local** manual builds under system Node 18 (`Vite`/`rolldown` throws `styleText is not defined in node:util` below Node 20) — not in CI. `deploy.yml:18-21` already pins `actions/setup-node@v4` with `node-version: lts/*`, which resolves to a current LTS (≥20) on every run; `public/build/` is gitignored (`.gitignore:31`) and never committed, so CI always rebuilds from source with a correct Node. `START-KONTEKST.md:51` states this explicitly: "Produkcja nie jest zagrożona — GitHub Actions buduje z `node-version: lts/*`, więc CI zawsze ma świeży Node." Tailwind's `content` glob (`tailwind.config.js:6-10`) already covers all Blade views, so there's no content-scanning gap either — the class-compilation risk was 100% a local-Node-version problem, already structurally absent from the CI path.
- **The real, still-open gap behind Risk #4 is not "pin Node in CI" (already true) but "no deterministic check that the build actually contains what the code uses."** Nothing in CI verifies the *contents* of `public/build/*.css` — a build that silently omits a class (for any reason: Tailwind config regression, a future switch away from `lts/*`, a broken `@tailwindcss/vite` update) would still exit 0 and deploy. This is exactly the "deterministic check (grep compiled CSS for newly-used classes)" layer the test plan's §5 quality-gates table and §1 risk-response guidance already named — it's real, just independent of the Node-version story.
- **Test suite is CI-ready with no infrastructure changes.** `phpunit.xml:15-16` already runs on `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` — no MySQL service container is needed in the CI job. `php artisan test` (verified locally) passes 85 tests / 227 assertions in ~1.3s — a trivially cheap gate.
- **One local-only footgun found, not a CI blocker**: `composer.json`'s `test` script (`@php artisan config:clear --ansi @no_additional_args`) fails locally with this repo's installed Composer 2.3.2 (`@no_additional_args` requires Composer ≥2.4). `php artisan test` run directly works fine. CI should not rely on the `composer test` alias unless the CI job's Composer is confirmed ≥2.4 (GitHub-hosted runners' bundled Composer is well above this, so it would work there, but the plan/implementation should call `php artisan test` directly to avoid depending on an environment-sensitive wrapper — or fix the composer.json script, which is a one-line, high-value adjacent fix).
- **`lessons.md` gap**: the Node 18/20 local-build trap was explicitly flagged for capture via `/10x-lesson` in the S-04 impl-review (`context/archive/2026-07-26-edit-delete-match/reviews/impl-review.md:35`) but was never actually added to `context/foundation/lessons.md` (current content confirmed: only two unrelated entries — AGENTS.md-as-source-of-truth and the `public/build` symlink gotcha). This is a loose end from a prior phase, not part of Phase 4's CI-gate scope, but worth flagging since a future contributor hitting the same Node 18 error locally has no `lessons.md` entry to find.

## Detailed Findings

### Risk #1 — No test gate before deploy

`.github/workflows/deploy.yml:1-58` is the *only* workflow file in `.github/workflows/` (confirmed: directory listing shows just `deploy.yml`). Its steps in order:

1. `actions/checkout@v4`
2. `shivammathur/setup-php@v2` pinned to `php-version: "8.5"` (`deploy.yml:13-16`)
3. `actions/setup-node@v4`, `node-version: lts/*` (`deploy.yml:18-22`)
4. `npm ci` (`deploy.yml:24-25`)
5. `npm run build` (`deploy.yml:27-28`)
6. `burnett01/rsync-deployments@7.0.1` uploads `public/build/` to the server (`deploy.yml:30-39`)
7. `appleboy/ssh-action@v1` runs, on the remote host, under `set -e` (`deploy.yml:48-57`):
   ```
   cd ~/domains/wyjazdowicz.cfolks.pl/repo
   git pull
   $PHP ~/bin/composer install --no-dev --optimize-autoloader
   $PHP artisan migrate --force
   $PHP artisan config:cache
   $PHP artisan route:cache
   $PHP artisan view:cache
   ```

No step anywhere runs `composer install` (dev deps) + `php artisan test` on the CI runner itself, and no step gates the SSH/migrate block on a test result. `set -e` at `deploy.yml:49` only protects the *remote* script from continuing after one of its own sub-steps fails (e.g., a failed `composer install` on the server won't proceed to `migrate --force`) — it does nothing about the test suite, because the test suite is never invoked. `infrastructure.md`'s own risk register (`infrastructure.md:76`) already names this same gap and its current (partial) mitigation is exactly this remote `set -e`, which the register itself scopes as protecting only "a failed step stops before anything is marked successful" — not "the code was tested first."

This confirms the plan's Risk #1 response guidance as-is: the fix is a **quality gate (CI job)**, not a unit test, and the anti-pattern to avoid (a test that only validates YAML syntax instead of requiring the suite to actually pass) is a real trap for an agent implementing this — GitHub Actions will happily "pass" a job that has a step but no `needs:` dependency wiring it to the deploy job.

### Risk #4 — Tailwind/Vite build correctness

**What actually broke in S-04** (`context/archive/2026-07-26-edit-delete-match/plan.md:113`):

> "sm:block nigdy nie zostało wkompilowane do statycznego builda Tailwind CSS tego projektu (npm run build uruchamiany raz, nie live przez Vite, bo systemowy Node 18 nie obsługuje Vite/rolldown ... patrz START-KONTEKST.md). Naprawione przebudowaniem assetów Node'em 20."

`START-KONTEKST.md:51` confirms this was a **local dev** problem (`composer dev` under system Node 18.20.8) and explicitly states production was never at risk because CI already builds under `lts/*`.

**Why the CI path is already safe from the Node-version angle**:
- `deploy.yml:18-21`: `actions/setup-node@v4` with `node-version: lts/*` — always resolves to a current LTS release (well above Node 20) on GitHub-hosted runners.
- `.gitignore:31`: `/public/build` is gitignored — confirmed via `git ls-files public/build` (empty) and `git log -- public/build` (no history). CI always builds fresh from `resources/`; a stale or wrong-Node local build can never be what gets deployed, because it's never committed.
- `tailwind.config.js:6-10`: `content` glob is `./resources/views/**/*.blade.php` (plus vendor pagination views and compiled view cache) — broad enough to catch all Blade-authored classes; no evidence of a content-scanning gap.

**What is still a real, open gap for Risk #4**: nothing in `deploy.yml` verifies the *output* of `npm run build`. The step at `deploy.yml:27-28` (`npm run build`) is trusted purely by exit code. A build that silently drops a class for a reason other than Node version (a Tailwind/Vite config regression, a future dependency upgrade, a JIT scanning edge case) would still exit 0 and get rsync'd to production. This matches the test-plan's §5 gate ("frontend build verification (Node 20+, new Tailwind classes compiled)") and the §1 anti-pattern warning ("relying only on remembering to export the right Node version by hand") — but the correction is that the *Node-version* half of that gate is already structurally satisfied by `lts/*` + gitignored `build/`; the gate that still needs building is a **deterministic content check** (e.g., grep the compiled CSS for a small fixed set of classes known to be used in the views, or diff class usage between Blade source and compiled CSS) run as a CI step after `npm run build` and before the rsync/deploy steps.

### Test suite readiness

- `phpunit.xml:15,16`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` — the entire suite runs against an in-memory SQLite DB. No MySQL service container, no `DB_HOST`/credentials wiring needed in a CI job — this removes what would otherwise be the most common source of CI-vs-local test flakiness for this stack.
- Verified locally: `php artisan test` → `{"tool":"phpunit","result":"passed","tests":85,"passed":85,"assertions":227,"duration_ms":1325}`. 15 test files under `tests/`. Fast enough that gating deploy on it adds negligible pipeline time.
- `composer.json`'s `test` script chain (`@php artisan config:clear --ansi @no_additional_args`) errors locally: `No arguments expected for "config:clear" command, got "@no_additional_args"`. Confirmed cause: local Composer is 2.3.2 (`composer --version`); `@no_additional_args` placeholder syntax needs Composer ≥2.4. `infrastructure.md:32` separately confirms the *production* server's Composer is 2.9.2 (new enough), but that's irrelevant to a CI runner's bundled Composer, which is a different environment. GitHub-hosted runners ship a modern Composer (well above 2.4) so `composer test` would likely work unmodified in CI — but planning/implementation should either call `php artisan test` directly (bypassing the wrapper entirely, zero risk) or fix the `composer.json` script as a small adjacent correctness fix. Either is cheap; calling `php artisan test` directly is the lower-risk choice since it has no dependency on the CI runner's exact Composer version.
- `composer.json`: `"php": "^8.3"` — the CI test job's PHP version should be ≥8.3 to match what the codebase actually declares support for; production runs PHP 8.5 (`deploy.yml:16`, `infrastructure.md:29`). Either 8.3 (matches the composer.json floor) or 8.5 (matches production exactly) is defensible for the test job; matching production (8.5) gives the tightest signal for "would this pass on the box that will actually run it."

### `lessons.md` — loose end, not in scope for this phase

`context/foundation/lessons.md` currently has exactly two entries (AGENTS.md-as-source-of-truth; `public/build` symlink on split webroot). The S-04 impl-review (`context/archive/2026-07-26-edit-delete-match/reviews/impl-review.md:35`) recommended capturing the Node 18 vs Tailwind static-build trap here via `/10x-lesson`, but it was never done. Not part of Phase 4's CI-gate scope (this phase is about wiring gates, not backfilling lessons), but flagged here since `/10x-plan` or a later session may want to pick it up — a contributor hitting the Node 18 `styleText is not defined` error today has nowhere in `context/foundation/` to find the known fix.

## Code References

- `.github/workflows/deploy.yml:1-58` — the entire (only) CI/CD workflow; no test step exists
- `.github/workflows/deploy.yml:13-16` — PHP 8.5 pinned for the deploy job
- `.github/workflows/deploy.yml:18-22` — Node `lts/*`, already correct for the build-Node-version half of Risk #4
- `.github/workflows/deploy.yml:27-28` — `npm run build`, currently trusted by exit code only
- `.github/workflows/deploy.yml:48-57` — remote SSH deploy script, `set -e` protects only this block, runs `migrate --force` with nothing gating it on tests
- `.gitignore:30-31` — `/node_modules`, `/public/build` both gitignored
- `tailwind.config.js:6-10` — Tailwind `content` glob, confirmed to cover all Blade views
- `phpunit.xml:15-16` — `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, no external DB needed for CI
- `composer.json` `scripts.test` — broken locally under Composer 2.3.2 (`@no_additional_args`); `php artisan test` works directly
- `composer.json` `require.php` — `^8.3`

## Architecture Insights

- The project has exactly one CI/CD workflow, and it conflates "build + deploy" into a single always-runs-on-push-to-master job with no independent test gate — a from-scratch addition, not a modification of existing gate logic.
- SQLite in-memory test config means the cheapest possible CI test job is a single job with just PHP setup + `composer install` + `php artisan test` — no services, no secrets, no DB provisioning.
- The Node-version risk and the "compiled CSS actually contains the class" risk are two different failure modes that got merged into one risk-map line (#4). CI already fully covers the first (via `lts/*` + gitignored build output); only the second is an open gate to build.

## Historical Context (from prior changes)

- `context/archive/2026-07-26-edit-delete-match/plan.md:113` — first-hand account of the Node 18/20 Tailwind build bug that Risk #4 is evidenced by.
- `context/archive/2026-07-26-edit-delete-match/reviews/impl-review.md:29,31,35` — impl-review that diagnosed the bug's root cause and recommended (but never executed) capturing it in `lessons.md`.
- `context/foundation/infrastructure.md:76` (Risk Register) — independently names the same Risk #1 gap ("No atomic deploy... Deploy script runs... with `set -e`... a failed step stops before anything is marked successful") — confirms this is a known, previously-accepted-as-partially-mitigated risk, not a new discovery.
- `START-KONTEKST.md:51` — explicit statement that production is not at risk from the Node-version half of Risk #4, made after the S-04 incident, before this research phase started. This research corroborates it independently from the workflow file and `.gitignore` rather than taking it on faith.

## Related Research

- None yet under this change folder prior to this document.

## Open Questions

- Should the new CI test job's PHP version be 8.3 (matches `composer.json` floor) or 8.5 (matches production exactly)? Leaning 8.5 for tighter signal, but this is a `/10x-plan` decision, not a research blocker.
- Should the `composer.json` `test` script be fixed (the `@no_additional_args` Composer-version incompatibility) as part of this phase, or left alone since CI would call `php artisan test` directly and never hit the broken wrapper? Recommend leaving it out of scope unless `/10x-plan` decides the inconsistency itself is worth a one-line fix.
- Exact deterministic check for "compiled CSS contains newly-used classes" (grep a fixed class list vs. a source→output diff) is a `/10x-plan` design decision, not resolved here — both are cheap; the research only confirms the check does not exist today and is not redundant with the Node-version fix.
