---
project: wyjazdowicz
researched_at: 2026-07-18
recommended_platform: self-host (cyberFolks shared hosting, DirectAdmin/CloudLinux)
runner_up: n/a — platform was locked in tech-stack.md before this research; see Out of Scope
context_type: mvp
tech_stack:
  language: php
  framework: laravel
  runtime: "php 8.5.6 (CloudLinux alt-php85, web-facing) / composer+artisan run via /opt/alt/php85/usr/bin/php explicitly"
---

## Recommendation

**Deploy to the existing cyberFolks shared hosting account (`s17.cyber-folks.pl`, user `yogiber`), domain `wyjazdowicz.cfolks.pl`, managed via DirectAdmin/CloudLinux.**

`tech-stack.md` already locked self-host as the deployment target before this research ran, so no managed-platform bake-off (Cloudflare/Vercel/Railway/etc.) was performed — see Out of Scope. This document instead verifies the actual server profile and turns it into a concrete deploy plan. The account has everything Laravel `^8.3` needs: PHP 8.5 (selected via CloudLinux's PHP Selector), Composer 2.9.2, SSH access, and a live domain already resolving (HTTP 200 confirmed). Two corrections from the original plan, both confirmed by actually deploying: PostgreSQL is not available (`pdo_pgsql` missing) — switched to **MySQL/MariaDB**; and the standard "symlink `public_html` to `repo/public`" pattern doesn't work on this host — the DirectAdmin PHP Selector refuses to manage a symlinked domain, so `public_html` stays a real directory with a hand-patched `index.php` instead (see "Webroot Layout").

**Status: deployed and verified (2026-07-18).** `https://wyjazdowicz.cfolks.pl/` serves Laravel (HTTP 200, confirmed via `curl`). Full history and the exact commands used are in `context/deployment/deploy-plan.md`.

## Hosting Profile (verified 2026-07-18)

| Aspect | Value |
|---|---|
| Provider | cyberFolks (`cyber-folks.pl`), Polish shared hosting |
| Control panel | DirectAdmin |
| OS/kernel | CloudLinux (RHEL8-based, LVE resource limiting), `4.18.0-553.126.2.lve.el8` |
| Access level | Jailed shell account (`yogiber`), no root, no `sudo`, no `systemctl` |
| PHP | Account CLI default was 8.2.31, but `composer.json` requires `^8.3` — user has since set **PHP 8.5** for the `wyjazdowicz.cfolks.pl` domain via CloudLinux's PHP Selector in the DirectAdmin panel (`/opt/alt/php85`, multiple versions available side-by-side in `/opt/alt/php*`) |
| PHP extensions confirmed | `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `pdo_sqlite`, `tokenizer`, `xml` + friends — covers Laravel's requirements. `composer install` under 8.5 completed with zero errors (confirmed live). |
| PHP extensions **missing** | `pdo_pgsql` — PostgreSQL is not an option on this account |
| Composer | 2.9.2, at `~/bin/composer`; invoked explicitly as `/opt/alt/php85/usr/bin/php ~/bin/composer ...` since the bare `composer` command resolves `php` from PATH (which is 8.2) |
| Node/npm | **Not installed on the account at all** (confirmed: `npm: nie znaleziono polecenia`) — frontend assets (Vite/Tailwind) must be built off-box (CI runner or local machine) and uploaded, never built on the server |
| Database | MySQL/MariaDB, provisioned via DirectAdmin panel ("MySQL Management"): `yogiber_wyjazdowicz` (DB + user). **Gotcha confirmed**: the DB user is granted for host `localhost`, not `127.0.0.1` — `DB_HOST=127.0.0.1` in `.env` fails with `Access denied`; `DB_HOST=localhost` works. |
| Domain | `wyjazdowicz.cfolks.pl` — live, HTTP 200, now serving Laravel (confirmed post-deploy) |
| Webroot | `~/domains/wyjazdowicz.cfolks.pl/public_html/` — **must be a real directory**, not a symlink (DirectAdmin's PHP Selector refuses to manage symlinked domains: "Domena posiada błędne uprawnienia lub katalog jest symlinkiem"). Contains a hand-patched `index.php`/`.htaccess` copied from `repo/public/`, plus `build/` (Vite output, the only part touched by routine deploys). See `context/deployment/deploy-plan.md` for the exact layout and patch. |
| SSH | Dedicated CI deploy key generated and authorized separately from the personal key; connection details stored as GitHub Actions repo secrets |

## Anti-Bias Cross-Check: Self-Host on cyberFolks (DirectAdmin/CloudLinux)

### Devil's Advocate — Weaknesses

1. **No atomic deploys.** There's no root/systemd, so there's no release-directory-and-symlink-swap pattern (Deployer/Envoyer-style) out of the box — a `git pull` + `composer install` that fails halfway can leave the live site in a broken intermediate state with no automatic rollback.
2. **Fixed document root, and the standard symlink fix doesn't work here.** DirectAdmin points the domain at `public_html` directly; Laravel needs its `public/` folder as the webroot. The usual fix — symlinking `public_html` to the app's `public/` directory — was tried and **confirmed broken on this host**: the PHP Selector panel refuses to manage a symlinked domain and silently drops the domain back to the account's default PHP version. The working fix (`public_html` as a real directory with a hand-patched `index.php`) means Laravel's own `public/index.php` and `.htaccess` are no longer the files actually served — they have to be manually kept in sync if they change upstream.
3. **No staging tier.** The only environment is production. Every merge to `master` (auto-deploy-on-merge, per `tech-stack.md`) goes live immediately with no isolated environment to catch a bad migration first.
4. **CloudLinux CageFS sandboxing.** Composer packages with post-install scripts that assume filesystem access outside the home directory, or that spawn certain system calls, can fail silently or behave differently than on a normal VPS.
5. **No visibility into resource limits from the shell.** CloudLinux enforces per-account CPU/memory/process caps (LVE) that aren't visible via `sudo`-gated tools like `systemctl` — they only show up in the DirectAdmin panel or when a request is throttled.

### Pre-Mortem — How This Could Fail

The team deployed Wyjazdowicz to cyberFolks shared hosting. Six months in, it went badly wrong. The auto-deploy-on-merge GitHub Action ran `composer install` and `php artisan migrate --force` directly against the live webroot with no release-directory strategy; one merge shipped a migration with a subtle bug, and because there was no staging tier, it hit production data immediately, corrupting match records. Rolling back meant SSHing in by hand, reverting via git, and manually fixing the database, since the migration wasn't safely reversible. Separately, nobody remembered that `public_html/index.php` was a hand-patched copy of Laravel's own `public/index.php`, not the real file — a framework upgrade changed the bootstrap file's structure, `git pull` updated `repo/public/index.php` as expected, but the stale, unpatched copy kept serving in `public_html` until someone noticed the site was running old code. Backups turned out to be shallow — a short rolling retention window — so by the time anyone noticed the corrupted data, no clean backup was left to restore from.

### Unknown Unknowns

- **Confirmed (2026-07-18)**: CloudLinux's PHP Selector only changed the web-facing handler (`phpinfo()` over HTTP correctly reports 8.5.6) — `php -v` in an SSH session still resolves to 8.2.31 from PATH. The 8.5 binary exists at `/opt/alt/php85/usr/bin/php` (verified), with no convenience symlink like `/usr/bin/php85`. All deploy commands (manual and in `.github/workflows/deploy.yml`) reference this path explicitly rather than relying on `php`/`composer` resolution through PATH, since non-interactive SSH sessions (as used by CI) don't reliably pick up shell profile changes anyway.
- **Confirmed (2026-07-18)**: the DirectAdmin PHP Selector panel refuses to manage a domain whose `public_html` is a symlink ("Domena posiada błędne uprawnienia lub katalog jest symlinkiem") and silently reverts the domain to the account default PHP (8.2). The standard Laravel-on-shared-hosting symlink pattern is off the table on this host — see "Webroot Layout" in `deploy-plan.md`.
- **Confirmed (2026-07-18)**: Node/npm is not installed on the account at all — frontend assets can never be built on the server, only uploaded pre-built.
- Laravel's scheduler (`php artisan schedule:run`) needs a DirectAdmin cron entry, not a systemd timer — easy to forget, and any scheduled feature added later will silently never run until this is wired up.
- Switching PHP versions later happens through CloudLinux's per-directory `alt-php` selector in the panel, not `apt`/`yum` — if cyberFolks changes the account's default PHP during a platform upgrade, it can silently drift from what `composer.json` pins (`"php": "^8.3"`).
- CloudLinux LVE resource caps (CPU/memory/concurrent processes) are invisible from the shell — check the "Resource Usage" panel in DirectAdmin before assuming Laravel's default `php.ini` limits (memory_limit, max_execution_time) are actually being honored at the plan's cap.
- cyberFolks' backup retention/frequency for this specific plan is unverified — check the DirectAdmin panel's backup section before real user data accumulates.

## Operational Story

- **Deploy pipeline**: GitHub Actions builds frontend assets (`npm ci && npm run build`, producing `public/build/`) on the CI runner — not on the shared-hosting account, which has no Node/npm at all (confirmed). Only `public/build/` is `rsync`'d to the server, landing in `public_html/build/` (the real, panel-managed webroot directory — not `repo/public/build/`, since `repo/` isn't web-accessible; see "Webroot Layout" in `deploy-plan.md`). Everything else in `public_html` (`index.php`, `.htaccess`) is untouched by routine deploys. A separate SSH-exec step then runs `git pull`, `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, and cache rebuilds inside `repo/` on the server, all explicitly under `/opt/alt/php85/usr/bin/php` (the bare `php`/`composer` on PATH still resolve to 8.2).
- **Preview deploys**: none available — shared hosting has one environment. Compensate by testing locally against SQLite (the project's current local default) before merging, and keeping PRs small.
- **Secrets**: production secrets (DB credentials, `APP_KEY`, future mail/API keys) live only in the server's `.env` file, edited by hand over SSH. CI-side, the SSH private key and connection details (`SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`) live in GitHub Actions repo secrets. No secrets manager beyond these two — rotation is manual.
- **Rollback**: SSH in, `git log` to find the last good commit, `git checkout <sha>` in the deploy directory, re-run `composer install --no-dev` and `php artisan config:cache`. Schema rollbacks (`migrate:rollback`) are not automatic and must be reviewed by hand before running — Laravel doesn't guarantee a destructive migration is safely reversible.
- **Approval**: the human gate is the pull-request merge itself (auto-deploy-on-merge is the agreed CI flow from `tech-stack.md`) — there is no separate deploy-approval step. Editing the server's `.env` or the DirectAdmin panel (DB credentials, domain/SSL settings) stays a manual, human-only action; no agent gets unattended panel access.
- **Logs**: `ssh yogiber@s17.cyber-folks.pl "tail -f ~/domains/wyjazdowicz.cfolks.pl/repo/storage/logs/laravel.log"` for app logs; the GitHub Actions run view for deploy-step logs; DirectAdmin's per-domain "Log Manager" for web server (LiteSpeed) error/access logs.

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| No atomic deploy — a failing `composer install`/`migrate` mid-run leaves the site broken | Devil's advocate | M | H | Deploy script runs `composer install` then `migrate --force` then cache rebuild in sequence with `set -e` in the Action, so a failed step stops before anything is marked successful |
| No staging environment — every merge hits production immediately | Devil's advocate / Pre-mortem | H | M | Test locally against SQLite before merging; keep PRs small and migrations reviewed; write `test-plan.md` risk-driven tests before touching schema |
| `public_html/index.php` is a hand-patched copy, can silently go stale after a Laravel upgrade | Pre-mortem | M | M | After any `laravel/framework` upgrade, diff `repo/public/index.php` against the deployed `public_html/index.php` and reapply the three path patches if it changed |
| CloudLinux CageFS blocks a Composer package's post-install script | Unknown unknowns | L | M | Watch `composer install` output on first deploy for silent script failures; avoid packages that assume system-level filesystem access |
| Laravel scheduler needs a DirectAdmin cron entry, not systemd | Unknown unknowns | M | L (no scheduled features in MVP yet) | Add the cron entry via the DirectAdmin panel once a scheduled feature is actually built |
| Backup retention on this hosting plan is unverified/likely shallow | Pre-mortem | M | H | Confirm cyberFolks' backup window in the DirectAdmin panel; add a simple scheduled `mysqldump` (via DirectAdmin cron) piped to off-server storage before real user data accumulates |
| Shared-hosting PHP-FPM/LVE resource caps invisible from the shell | Devil's advocate | L | M | Check "Resource Usage" in DirectAdmin; single-user MVP traffic is very unlikely to approach the plan's caps soon |
| MySQL swap not yet reflected anywhere except `tech-stack.md`/this file | Research finding | L | M | `.env` on server must set `DB_CONNECTION=mysql` explicitly; local dev keeps `sqlite` (already the scaffold default) — no code change needed, only environment config |
| DirectAdmin PHP Selector refuses symlinked `public_html`, silently drops to PHP 8.2 | Research finding (confirmed live) | — (already hit and fixed) | H | Use a real `public_html` directory with a hand-patched `index.php`/`.htaccess`, not a symlink; documented in `deploy-plan.md` |
| MySQL DB user granted for `localhost` only, not `127.0.0.1` — `Access denied` if misconfigured | Research finding (confirmed live) | — (already hit and fixed) | M | `.env` must use `DB_HOST=localhost` on this host |
| Laravel's `public_path()` resolves against `repo/public/`, not `public_html/` — `@vite()` read a stale, never-updated manifest while the CI pipeline only rsyncs assets to `public_html/build/`, so deploys reported success but the live site 404'd on every CSS/JS asset | Confirmed live, 2026-07-19 (S-01/`user-registration-login`) | — (already hit and fixed) | H | One-time fix: `cd repo/public && rm -rf build && ln -s ../../public_html/build build` — makes both paths resolve to the same physical files on every future deploy. See `context/deployment/deploy-plan.md` and `context/foundation/lessons.md`. |
| Production `.env` is server-local and gitignored — code/config changes in the repo (e.g. `APP_LOCALE`) never reach production automatically, only `.env.example` does | Confirmed live, 2026-07-19 | M | M | After any change to `.env.example` that isn't just documentation, manually apply the same change to the server's real `.env` and re-run `php artisan config:cache` |

## Getting Started

Already done — see `context/deployment/deploy-plan.md` for the checked-off checklist and the exact commands used. Summary, for reference or re-deploying to a fresh account:

1. **Provision the database** via DirectAdmin ("MySQL Management"). Use `DB_HOST=localhost` in `.env`, not `127.0.0.1` (confirmed gotcha — the DirectAdmin-created user is only granted for `localhost`).
2. **Select PHP 8.5 for the domain** via the CloudLinux PHP Selector in the panel — but only *after* `public_html` is a real directory (see step 5); the panel refuses to apply a version to a symlinked domain.
3. **Generate a dedicated CI deploy key**, separate from any personal key: `ssh-keygen -t ed25519 -C "github-actions-deploy-wyjazdowicz" -f ~/.ssh/wyjazdowicz_deploy -N ""`, append the `.pub` to `~/.ssh/authorized_keys`.
4. **Add GitHub Actions secrets**: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`.
5. **First deploy, manually over SSH**, using `/opt/alt/php85/usr/bin/php` explicitly (bare `php`/`composer` on PATH resolve to 8.2):
   ```bash
   PHP=/opt/alt/php85/usr/bin/php
   cd ~/domains/wyjazdowicz.cfolks.pl
   git clone <repo-url> repo
   cd repo
   $PHP ~/bin/composer install --no-dev --optimize-autoloader
   cp .env.example .env   # edit: APP_ENV=production, APP_URL=..., DB_CONNECTION=mysql, DB_HOST=localhost + credentials
   $PHP artisan key:generate
   $PHP artisan migrate --force

   # public_html must stay a REAL directory — DirectAdmin's PHP Selector refuses symlinks
   cd ~/domains/wyjazdowicz.cfolks.pl
   rm public_html   # or rm -rf if it's still the DirectAdmin default placeholder
   mkdir public_html
   cp repo/public/.htaccess public_html/.htaccess
   cp repo/public/index.php public_html/index.php
   # then hand-patch public_html/index.php: prefix the three __DIR__.'/../...' paths
   # (maintenance.php check, vendor/autoload.php, bootstrap/app.php) with repo/,
   # e.g. __DIR__.'/../repo/vendor/autoload.php'

   # frontend assets: Node isn't installed on this account — build locally and copy up
   # (from a machine with Node): npm ci && npm run build && scp -r public/build user@host:~/domains/.../public_html/
   cp -r repo/public/build public_html/build   # if built directly on-box some other way

   # CRITICAL: public_path() (used by @vite()) resolves against repo/public/, not
   # public_html/, no matter where index.php physically lives — so repo/public/build
   # must be a symlink into public_html/build, or Laravel will read a stale manifest
   # forever while the webserver serves the current one (confirmed bug, 2026-07-19,
   # see risk register + context/foundation/lessons.md):
   cd repo/public && rm -rf build && ln -s ../../public_html/build build && cd -

   $PHP artisan config:cache && $PHP artisan route:cache
   ```
   Then reselect PHP 8.5 in the panel (step 2) and load `https://wyjazdowicz.cfolks.pl/` to confirm.
6. **Wire the GitHub Actions workflow** (`.github/workflows/deploy.yml`, auto-deploy-on-merge to `master`): builds `public/build/` on the CI runner, `rsync`s it to `public_html/build/` (not `repo/public/build/`), then SSH-execs `git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache` inside `repo/`, all under the explicit PHP 8.5 binary. Fails the job on any non-zero exit.
7. **When a scheduled feature is added**, register `* * * * * cd ~/domains/wyjazdowicz.cfolks.pl/repo && /opt/alt/php85/usr/bin/php artisan schedule:run >> /dev/null 2>&1` as a cron job in the DirectAdmin panel.

## Out of Scope

The following were not evaluated in this research:
- A managed/serverless platform bake-off (Cloudflare, Vercel, Netlify, Fly.io, Railway, Render) — `tech-stack.md` already committed to self-host before this document was written; re-run `/10x-tech-stack-selector` first if that decision needs revisiting.
- Docker image configuration.
- CI/CD pipeline implementation details beyond the deploy step outline above (the actual `.github/workflows/*.yml` is written during implementation).
- Production-scale architecture (multi-region, HA, DR) — out of scope for a single-user MVP on shared hosting.
