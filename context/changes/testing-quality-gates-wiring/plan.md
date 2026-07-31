# Quality-gates wiring (test-plan.md Phase 4) Plan implementacji

## Przegląd

Wire two CI quality gates into `.github/workflows/deploy.yml`, the project's only CI/CD workflow, which today goes straight from `npm run build` to `git pull && migrate --force` on the production server with nothing in between. This closes rollout Phase 4 of `context/foundation/test-plan.md`, covering Risk #1 (a broken merge reaches production because nothing runs the test suite first) and Risk #4 (a frontend build silently omits a used Tailwind class and nothing catches it before deploy).

## Analiza stanu obecnego

`deploy.yml` is a single `deploy` job: checkout → setup PHP 8.5 → setup Node `lts/*` → `npm ci` → `npm run build` → rsync `public/build/` to the server → SSH block (`set -e`; `git pull`, `composer install --no-dev`, `migrate --force`, cache rebuilds). No step anywhere runs `php artisan test` or any test command. The workflow only triggers on `push: branches: [master]` — there is no PR-triggered run today, so "merge" and "deploy" are the same event.

The test suite itself needs no CI infrastructure changes: `phpunit.xml` already runs on `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (no service container, no DB provisioning). Verified locally: `php artisan test` → 85 tests / 227 assertions / ~1.3s. `composer.json`'s `scripts.test` chain is broken under old Composer (`@no_additional_args` needs Composer ≥2.4) — `php artisan test` is called directly instead, sidestepping that wrapper entirely (confirmed working locally).

Tailwind's `content` glob (`tailwind.config.js`) already covers all Blade views, and CI's Node version (`lts/*`) already rules out the S-04 Node-18-vs-Vite/rolldown failure mode — `public/build/` is gitignored, so CI always builds fresh. Confirmed live (this plan's research step): Tailwind compiles a responsive-variant class like `sm:hidden` to the CSS selector `.sm\:hidden{display:none}` — **the colon is escaped with a literal backslash in the compiled output**, not left bare. A check that greps for `sm:hidden` (no backslash) will never match and will always report "missing," even on a correct build.

### Kluczowe odkrycia:

- `.github/workflows/deploy.yml:27-28` — `npm run build` step, currently trusted by exit code only, no content check
- `.github/workflows/deploy.yml:41-57` — SSH deploy block; nothing gates this on tests passing
- `phpunit.xml:15-16` — SQLite `:memory:`, zero CI infra needed for the test job
- Compiled CSS escapes `:` as `\:` — confirmed by an actual local build (`public/build/assets/app-*.css` contains `.sm\:hidden{display:none}`, `.sm\:block{display:block}`)
- Actually-used responsive classes in `resources/views/**/*.blade.php` (via `class="..."` grep): `sm:hidden`, `sm:block` (the exact pair that broke in S-04, in `matches/index.blade.php` and `layouts/navigation.blade.php`), plus `sm:flex`, `sm:grid-cols-4`, `lg:block`, `lg:flex-row` among others
- The workflow's only trigger is `push: branches: [master]` — verifying "a failing test blocks deploy" cannot be done via a PR preview; it requires either a temporary trigger change or accepting the risk of testing directly against `master`

## Pożądany stan końcowy

`deploy.yml` has two enforced gates before any code reaches the production `migrate --force` step:
1. A `test` job runs `php artisan test` on every push to `master`; the `deploy` job declares `needs: test` so GitHub Actions refuses to run the deploy job (including the SSH block) if the test job fails.
2. After `npm run build` inside the `deploy` job, a step greps the compiled CSS for a fixed set of known responsive-variant classes (escaped correctly, `\:` not `:`) and fails the job — before the rsync/SSH steps — if any are missing.

Verified by: pushing a commit with a deliberately failing test to a temporary branch trigger, confirming the `deploy` job shows as blocked/skipped in the Actions tab; then confirming a passing commit lets `deploy` run; then the same push/observe cycle for a deliberately stripped responsive class.

## Czego NIE robimy

- Not fixing `composer.json`'s broken `scripts.test` chain (explicit scope decision — `php artisan test` is called directly in CI instead)
- Not adding a staging tier, atomic deploy/rollback, or a `pull_request` trigger as a permanent addition to the workflow (Risk #1's "no staging tier, no atomic rollback" framing is explicitly named in `infrastructure.md`'s Risk Register as an operational task, not a testable code scenario this rollout phase covers — see `test-plan.md` §2 note on excluded candidates)
- Not building a full source-vs-compiled-output class diff (the "catches every possible missing class" option) — the plan uses a fixed, manually-maintained class list per the confirmed design decision; extending that list is a one-line edit when a new responsive class is introduced, documented in §6
- Not touching `NominatimGeocoder`, `StatsCalculator`, `OwnershipContractTest`, or any other Phase 1–3 rollout surface

## Podejście do implementacji

Two independent, additive changes to the same file (`deploy.yml`), sequenced so each is independently verifiable before the next lands:

- **Phase 1** adds the test-suite gate via a `needs:` job dependency — the anti-pattern this specifically avoids (per research) is wiring a test *step* into the same job as deploy without `needs:`, which would not actually block anything since GitHub Actions doesn't infer step-level fail-fast across job boundaries the way `needs:` does across jobs.
- **Phase 2** adds the CSS content-check step, using the corrected (backslash-escaped) grep pattern confirmed against a real local build, positioned after `npm run build` and before the rsync step so a failed check never uploads/deploys a broken build.
- **Phase 3** closes the loop on `test-plan.md` itself: §6.5 is currently a `TBD` placeholder pointing at this phase; once shipped, it gets the real pattern (file, job names, how to add a new required check).

## Krytyczne szczegóły implementacji

- **Verification requires a temporary trigger, not a permanent one.** `deploy.yml`'s only trigger is `push: branches: [master]`. To manually verify the gate actually blocks a bad push without doing that against real `master` (which is also the deploy trigger), temporarily add the working branch name to `on.push.branches`, push the verification commits there, observe the Actions tab, then **remove the temporary branch entry before the phase's final commit lands on `master`**. Forgetting this step leaves an extra trigger branch permanently wired into deploy.
- **The escaped-colon grep pattern is not optional polish — it is the entire correctness of Phase 2.** `grep -q -- "sm:hidden"` never matches Tailwind's actual compiled output (`sm\:hidden`); the check step's grep patterns must include the literal backslash (e.g. `'sm\\:hidden'` in a bash script, mind shell-quoting so the backslash survives into the pattern seen by `grep`). This was confirmed against a real `npm run build` output during planning, not assumed.

## Faza 1: Test-suite gate for deploy

### Przegląd

Add a `test` job to `deploy.yml` that runs the full PHPUnit suite, and make the existing `deploy` job depend on it via `needs:`, so a failing test suite blocks the entire deploy pipeline — including the `migrate --force` step — before it ever reaches the server.

### Wymagane zmiany:

#### 1. New `test` job

**Plik**: `.github/workflows/deploy.yml`

**Cel**: Run the project's test suite on every push to `master`, on the CI runner, before any deploy step executes. Mirrors production's PHP version (8.5) for the tightest signal, per the confirmed design decision.

**Kontrakt**: A new top-level job (e.g. `test:`) alongside the existing `deploy:` job, `runs-on: ubuntu-latest`. Steps: checkout (`actions/checkout@v4`), PHP setup (`shivammathur/setup-php@v2`, `php-version: "8.5"` — same action/version already used by `deploy`), `composer install --prefer-dist --no-interaction` (dev dependencies included — PHPUnit is a dev dependency, unlike the deploy job's `--no-dev`), copy `.env.example` to `.env` and run `php artisan key:generate` (required — `.env.example` ships `APP_KEY=` empty, and `phpunit.xml`'s `<php>` env block does not set `APP_KEY`), then `php artisan test`. No database service container needed — `phpunit.xml` already forces SQLite `:memory:` regardless of what `.env` says.

#### 2. Gate the `deploy` job on `test`

**Plik**: `.github/workflows/deploy.yml`

**Cel**: Prevent the deploy job (and therefore the SSH/`migrate --force` block) from running at all unless the `test` job succeeded.

**Kontrakt**: Add `needs: test` to the existing `deploy:` job definition. No other step in `deploy` changes in this phase.

### Kryteria sukcesu:

#### Automatyczne:

- [ ] `deploy.yml` parses as valid YAML and the `test` job is syntactically well-formed (`yamllint .github/workflows/deploy.yml` or equivalent, or a dry-run via `act`/GitHub's own workflow syntax check on push)
- [ ] `php artisan test` (the exact command wired into the new job) passes locally: 85/85 tests

#### Ręczne:

- [ ] Temporarily add the working branch to `on.push.branches`, push a commit that makes one test fail, push it, and confirm in the GitHub Actions tab that the `test` job fails and the `deploy` job shows as skipped/blocked (not run)
- [ ] Push a follow-up commit fixing the test, confirm `test` passes and `deploy` now runs
- [ ] Remove the temporary branch from `on.push.branches` before this phase's final commit reaches `master`

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że testy ręczne zakończyły się sukcesem, zanim przejdziesz do następnej fazy.

---

## Faza 2: Compiled-CSS class verification

### Przegląd

After `npm run build` runs inside the `deploy` job, add a step that greps the compiled CSS for a fixed, known set of responsive Tailwind classes and fails the job — before the rsync/SSH steps run — if any are missing. This is independent of Risk #1's fix; it catches a build that exits 0 but silently produced incomplete CSS, regardless of cause.

### Wymagane zmiany:

#### 1. Build-output verification step

**Plik**: `.github/workflows/deploy.yml`

**Cel**: Fail the deploy job before any asset is uploaded if the compiled CSS is missing a class the codebase actually relies on for responsive layout — the exact failure mode that broke `matches/index.blade.php` in S-04 (a correct Blade template, a build that silently didn't compile the class it used).

**Kontrakt**: A new step, placed after "Build frontend assets" (`npm run build`) and before "Upload built assets" (the rsync step). Locates the compiled CSS file(s) under `public/build/assets/*.css`, then checks each class in a fixed list against the file content, failing (non-zero exit) with a clear message naming the missing class if any check fails.

**Class list** (seed set, confirmed present in the current codebase via `grep -rhoE 'class="[^"]*"' resources/views | grep -oE '\b(sm|md|lg|xl):[a-zA-Z0-9_/-]+'`): `sm:hidden`, `sm:block` (the exact S-04 pair — `matches/index.blade.php`, `layouts/navigation.blade.php`), plus `sm:flex`, `sm:grid-cols-4`, `lg:block`, `lg:flex-row` for breadth across both the `sm:` and `lg:` breakpoints and both display/layout-changing utilities.

**Escaping**: confirmed by an actual local build — Tailwind compiles `sm:hidden` to the selector `.sm\:hidden{display:none}` (colon escaped with a literal backslash). The grep pattern for each class must search for the class with `:` replaced by `\:`, not the bare class name — a bare-colon grep will report every class as missing on every build, including correct ones.

### Kryteria sukcesu:

#### Automatyczne:

- [ ] `deploy.yml` parses as valid YAML with the new step present
- [ ] Running the equivalent grep locally against a fresh `npm run build` output (`public/build/assets/app-*.css`) finds all six seed classes with the escaped pattern

#### Ręczne:

- [ ] On the same temporary verification branch from Phase 1 (re-add it to `on.push.branches` if already removed), temporarily rename `sm:hidden` to something else in `matches/index.blade.php`, push, confirm the new step fails the `deploy` job before the rsync step runs
- [ ] Revert the rename, push, confirm the step passes and the job proceeds
- [ ] Remove the temporary branch from `on.push.branches` before this phase's final commit reaches `master`

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że testy ręczne zakończyły się sukcesem, zanim przejdziesz do następnej fazy.

---

## Faza 3: Cookbook update (`test-plan.md` §6.5)

### Przegląd

Close the loop on the test plan's own cookbook: §6.5 ("Wiring a new CI quality gate") is currently a `TBD — see §3 Phase 4` placeholder. Fill it in with the actual pattern shipped in Phases 1–2, and mark rollout Phase 4 `complete` in §3.

### Wymagane zmiany:

#### 1. §6.5 cookbook entry

**Plik**: `context/foundation/test-plan.md`

**Cel**: Document the real pattern so a future contributor knows how to add another required CI gate without rediscovering the `needs:` job-dependency mechanism or the escaped-colon grep gotcha.

**Kontrakt**: Replace the `TBD` line under `### 6.5 Wiring a new CI quality gate` with: the workflow file location (`.github/workflows/deploy.yml`), the `test` job as the reference pattern for a required-before-deploy gate (`needs: <job>` on the `deploy` job — not step ordering within one job), the compiled-CSS check as the reference pattern for a deterministic build-output check (fixed class list, escaped-colon grep, extend the list when a new fragile responsive class is introduced), and a `Checked: <today>` date.

#### 2. §3 status update

**Plik**: `context/foundation/test-plan.md`

**Cel**: Mark rollout Phase 4 `complete` now that both gates are wired and verified.

**Kontrakt**: Row 4 of the §3 Phased Rollout table: `Status` column `researched` → `complete`.

### Kryteria sukcesu:

#### Automatyczne:

- [ ] `test-plan.md` §6.5 no longer contains the literal string `TBD`
- [ ] `test-plan.md` §3 row 4 `Status` cell reads `complete`

#### Ręczne:

- [ ] Read §6.5 as a contributor who has never seen this change would — confirm it's enough to add a third gate without re-deriving the `needs:`/escaping gotchas from scratch

---

## Strategia testowania

### Testy jednostkowe:

- None added — this phase wires CI infrastructure, not application code. `phpunit.xml`/existing 85 tests are the payload the new gate runs, unchanged.

### Testy integracyjne:

- N/A for this phase (gates (CI) test type per `test-plan.md` §3, not integration/feature tests)

### Kroki testowania ręcznego:

1. Temporary-branch push cycle for Phase 1 (failing test → blocked deploy; fixed test → deploy runs)
2. Temporary-branch push cycle for Phase 2 (missing class → blocked deploy; restored class → deploy runs)
3. Final confirmation: both temporary branch triggers removed from `on.push.branches` before the change is considered done

## Uwagi dotyczące wydajności

`php artisan test` adds ~1.3s of job time (SQLite in-memory, no service container to spin up). The CSS-check step is a handful of `grep` calls against one or two compiled CSS files — negligible. Total added CI time is well under the noise floor of the existing `npm ci`/`composer install` steps.

## Uwagi dotyczące migracji

None — no data model, schema, or runtime behavior changes. Pure CI/CD configuration.

## Referencje

- Powiązane badania: `context/changes/testing-quality-gates-wiring/research.md`
- Ryzyka źródłowe: `context/foundation/test-plan.md` §2 (Risk #1, Risk #4) i §2 Risk Response Guidance
- Poprzedni incydent: `context/archive/2026-07-26-edit-delete-match/plan.md:113`, `context/archive/2026-07-26-edit-delete-match/reviews/impl-review.md:29,31,35`

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków. Zobacz `references/progress-format.md`.

### Faza 1: Test-suite gate for deploy

#### Automatyczne

- [x] 1.1 `deploy.yml` parses as valid YAML and the `test` job is syntactically well-formed
- [x] 1.2 `php artisan test` passes locally: 85/85 tests

#### Ręczne

- [x] 1.3 Failing-test push on temporary branch confirms `deploy` job is blocked/skipped
- [x] 1.4 Fixed-test push on temporary branch confirms `deploy` job runs
- [x] 1.5 Temporary branch removed from `on.push.branches`

### Faza 2: Compiled-CSS class verification

#### Automatyczne

- [x] 2.1 `deploy.yml` parses as valid YAML with the new step present
- [x] 2.2 Local grep against fresh `npm run build` output finds all six seed classes with the escaped pattern

#### Ręczne

- [x] 2.3 Stripped-class push on temporary branch confirms the step fails before rsync — verified locally instead of via live push (user-approved substitution): stripped all 4 seed classes from every source file that declares them, cleared Laravel's view cache, rebuilt, confirmed the exact CI grep logic reports all 4 missing
- [x] 2.4 Restored-class push on temporary branch confirms the step passes — verified locally: restored source, rebuilt, confirmed all 4 found
- [x] 2.5 Temporary branch removed from `on.push.branches` — N/A, no verification branch was created for this phase (local-only verification)

### Faza 3: Cookbook update (`test-plan.md` §6.5)

#### Automatyczne

- [x] 3.1 `test-plan.md` §6.5 no longer contains the literal string `TBD`
- [x] 3.2 `test-plan.md` §3 row 4 `Status` cell reads `complete`

#### Ręczne

- [x] 3.3 §6.5 read-through confirms it's sufficient for a future contributor to add a third gate unaided
