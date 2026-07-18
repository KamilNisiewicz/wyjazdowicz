---
project: wyjazdowicz
researched_at: 2026-07-18
recommended_platform: self-host (cyberFolks shared hosting, DirectAdmin/CloudLinux)
runner_up: n/a — platform was locked in tech-stack.md before this research; see Out of Scope
context_type: mvp
tech_stack:
  language: php
  framework: laravel
  runtime: "php 8.2.31 (CloudLinux alt-php82)"
---

## Recommendation

**Deploy to the existing cyberFolks shared hosting account (`s17.cyber-folks.pl`, user `yogiber`), domain `wyjazdowicz.cfolks.pl`, managed via DirectAdmin/CloudLinux.**

`tech-stack.md` already locked self-host as the deployment target before this research ran, so no managed-platform bake-off (Cloudflare/Vercel/Railway/etc.) was performed — see Out of Scope. This document instead verifies the actual server profile and turns it into a concrete deploy plan. The account has everything Laravel 8.2+ needs: PHP 8.2.31 CLI with all required extensions, Composer 2.9.2, SSH access, and a live domain already resolving (HTTP 200 confirmed). One correction from `tech-stack.md`: PostgreSQL is not available (`pdo_pgsql` missing) — switched to **MySQL/MariaDB**, provisioned through the DirectAdmin panel.

## Hosting Profile (verified 2026-07-18)

| Aspect | Value |
|---|---|
| Provider | cyberFolks (`cyber-folks.pl`), Polish shared hosting |
| Control panel | DirectAdmin |
| OS/kernel | CloudLinux (RHEL8-based, LVE resource limiting), `4.18.0-553.126.2.lve.el8` |
| Access level | Jailed shell account (`yogiber`), no root, no `sudo`, no `systemctl` |
| PHP | Account CLI default was 8.2.31, but `composer.json` requires `^8.3` — user has since set **PHP 8.5** for the `wyjazdowicz.cfolks.pl` domain via CloudLinux's PHP Selector in the DirectAdmin panel (`/opt/alt/php85`, multiple versions available side-by-side in `/opt/alt/php*`) |
| PHP extensions confirmed | `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `pdo_sqlite`, `tokenizer`, `xml` + friends — covers Laravel's requirements (re-verify against 8.5 specifically; extension list above was captured under 8.2) |
| PHP extensions **missing** | `pdo_pgsql` — PostgreSQL is not an option on this account |
| Composer | 2.9.2, at `~/bin/composer`, bound to PHP 8.2 |
| Database | MySQL/MariaDB client present system-wide; DB itself must be provisioned per-account via the DirectAdmin panel ("MySQL Management") — not yet created |
| Domain | `wyjazdowicz.cfolks.pl` — live, resolves HTTP 200, HTTP/2 + HTTP/3 via LiteSpeed |
| Webroot | `~/domains/wyjazdowicz.cfolks.pl/public_html/` — currently the DirectAdmin default placeholder (`index.html`, `cgi-bin`, `.htaccess`), nothing to clean up |
| SSH | 1 authorized key already present (assume personal); port unconfirmed via config file (no read access) — use whatever port the user's current interactive SSH session already connects on |

## Anti-Bias Cross-Check: Self-Host on cyberFolks (DirectAdmin/CloudLinux)

### Devil's Advocate — Weaknesses

1. **No atomic deploys.** There's no root/systemd, so there's no release-directory-and-symlink-swap pattern (Deployer/Envoyer-style) out of the box — a `git pull` + `composer install` that fails halfway can leave the live site in a broken intermediate state with no automatic rollback.
2. **Fixed document root.** DirectAdmin points the domain at `public_html` directly; Laravel needs its `public/` folder as the webroot. The standard fix is symlinking `public_html` to the app's `public/` directory, which works but is a manual step that panel actions (SSL reissue, "rebuild" operations) could theoretically disturb.
3. **No staging tier.** The only environment is production. Every merge to `master` (auto-deploy-on-merge, per `tech-stack.md`) goes live immediately with no isolated environment to catch a bad migration first.
4. **CloudLinux CageFS sandboxing.** Composer packages with post-install scripts that assume filesystem access outside the home directory, or that spawn certain system calls, can fail silently or behave differently than on a normal VPS.
5. **No visibility into resource limits from the shell.** CloudLinux enforces per-account CPU/memory/process caps (LVE) that aren't visible via `sudo`-gated tools like `systemctl` — they only show up in the DirectAdmin panel or when a request is throttled.

### Pre-Mortem — How This Could Fail

The team deployed Wyjazdowicz to cyberFolks shared hosting. Six months in, it went badly wrong. The auto-deploy-on-merge GitHub Action ran `composer install` and `php artisan migrate --force` directly against the live symlinked webroot with no release-directory strategy; one merge shipped a migration with a subtle bug, and because there was no staging tier, it hit production data immediately, corrupting match records. Rolling back meant SSHing in by hand, reverting via git, and manually fixing the database, since the migration wasn't safely reversible. Separately, a DirectAdmin auto-SSL renewal reset the `public_html` symlink back to the panel's default page, and the site "went down" with no error in the GitHub Actions log (the deploy itself had succeeded — the panel just overwrote the webroot afterward). Backups turned out to be shallow — a short rolling retention window — so by the time anyone noticed the corrupted data, no clean backup was left to restore from.

### Unknown Unknowns

- CloudLinux's PHP Selector sometimes only changes the web-facing PHP-FPM handler for a domain, not the PHP-CLI binary an SSH session resolves — confirm `php -v` in an SSH session actually reports 8.5.x before relying on it for `composer install`/`artisan` commands; if it still shows 8.2, the CLI binary needs to be selected separately (e.g. via `/opt/alt/php85/usr/bin/php` directly, or a CloudLinux CLI selector command).
- DirectAdmin's automatic Let's Encrypt renewal has been known to reset `.htaccess`/webroot symlinks it doesn't recognize on some configurations — worth testing once (or asking cyberFolks support) rather than assuming the symlink survives certificate renewal.
- Laravel's scheduler (`php artisan schedule:run`) needs a DirectAdmin cron entry, not a systemd timer — easy to forget, and any scheduled feature added later will silently never run until this is wired up.
- Switching PHP versions later happens through CloudLinux's per-directory `alt-php` selector in the panel, not `apt`/`yum` — if cyberFolks changes the account's default PHP during a platform upgrade, it can silently drift from what `composer.json` pins (`"php": "^8.2"`).
- CloudLinux LVE resource caps (CPU/memory/concurrent processes) are invisible from the shell — check the "Resource Usage" panel in DirectAdmin before assuming Laravel's default `php.ini` limits (memory_limit, max_execution_time) are actually being honored at the plan's cap.
- cyberFolks' backup retention/frequency for this specific plan is unverified — check the DirectAdmin panel's backup section before real user data accumulates.

## Operational Story

- **Deploy pipeline**: GitHub Actions builds frontend assets (`npm ci && npm run build`, producing `public/build/`) on the CI runner — not on the shared-hosting account, to avoid depending on Node being installed there and to keep CPU/LVE load off the account (see risk register). Only `public/build/` is `rsync`'d to the server; everything else (`.env`, `storage/`, the database) is left untouched by that step. A separate SSH-exec step then runs `git pull`, `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, and cache rebuilds directly on the server (PHP dependency installation stays lightweight enough for the account's resource caps at MVP traffic levels).
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
| DirectAdmin SSL auto-renewal may reset the `public_html` symlink | Unknown unknowns | L | H | After the first Let's Encrypt renewal post-launch, manually verify the site still serves Laravel (not the DirectAdmin default page); document the fix in `AGENTS.md` if it recurs |
| CloudLinux CageFS blocks a Composer package's post-install script | Unknown unknowns | L | M | Watch `composer install` output on first deploy for silent script failures; avoid packages that assume system-level filesystem access |
| Laravel scheduler needs a DirectAdmin cron entry, not systemd | Unknown unknowns | M | L (no scheduled features in MVP yet) | Add the cron entry via the DirectAdmin panel once a scheduled feature is actually built |
| Backup retention on this hosting plan is unverified/likely shallow | Pre-mortem | M | H | Confirm cyberFolks' backup window in the DirectAdmin panel; add a simple scheduled `mysqldump` (via DirectAdmin cron) piped to off-server storage before real user data accumulates |
| Shared-hosting PHP-FPM/LVE resource caps invisible from the shell | Devil's advocate | L | M | Check "Resource Usage" in DirectAdmin; single-user MVP traffic is very unlikely to approach the plan's caps soon |
| MySQL swap not yet reflected anywhere except `tech-stack.md`/this file | Research finding | L | M | `.env` on server must set `DB_CONNECTION=mysql` explicitly; local dev keeps `sqlite` (already the scaffold default) — no code change needed, only environment config |

## Getting Started

1. **Provision the database.** In the DirectAdmin panel → "MySQL Management", create a database and a dedicated user for `wyjazdowicz`; note the DB name, username, and password.
2. **Generate a dedicated CI deploy key** (don't reuse the personal key already in `authorized_keys`): `ssh-keygen -t ed25519 -C "github-actions-deploy-wyjazdowicz" -f ~/.ssh/wyjazdowicz_deploy -N ""`, then append the `.pub` to `~/.ssh/authorized_keys` on the server.
3. **Add GitHub Actions secrets**: `SSH_HOST` (`s17.cyber-folks.pl`), `SSH_PORT` (whatever the current working interactive SSH session uses), `SSH_USER` (`yogiber`), `SSH_PRIVATE_KEY` (the new deploy key's private half), plus `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` if the Action needs to template the server `.env`.
4. **Confirm PHP-CLI resolves to 8.5 over SSH** (`php -v`) — the DirectAdmin PHP Selector sometimes only affects the web-facing handler, not the CLI binary a fresh SSH session picks up. If it still shows 8.2, use the CLI binary directly (`/opt/alt/php85/usr/bin/php`) for the steps below, or resolve the CLI selector separately before proceeding.
5. **First deploy — do it manually once over SSH before wiring CI**, so the webroot layout is verified before automation touches it:
   ```bash
   cd ~/domains/wyjazdowicz.cfolks.pl
   git clone <repo-url> repo
   cd repo
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build   # or skip and let the first CI run populate public/build/ instead
   cp .env.example .env   # then edit: APP_ENV=production, APP_URL=https://wyjazdowicz.cfolks.pl, DB_CONNECTION=mysql + DB credentials from step 1
   php artisan key:generate
   php artisan migrate --force
   rm -rf ../public_html
   ln -s ~/domains/wyjazdowicz.cfolks.pl/repo/public ~/domains/wyjazdowicz.cfolks.pl/public_html
   php artisan config:cache && php artisan route:cache
   ```
   Then load `https://wyjazdowicz.cfolks.pl/` and confirm it serves the Laravel welcome page, not the DirectAdmin default.
6. **Wire the GitHub Actions workflow** (`.github/workflows/deploy.yml`, auto-deploy-on-merge to `master` per `tech-stack.md`): builds `public/build/` on the CI runner (Node/npm, not on the server), `rsync`s just that directory to the server, then SSH-execs `git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`. Fail the job (don't mark deploy successful) if any step exits non-zero.
7. **When a scheduled feature is added**, register `* * * * * cd ~/domains/wyjazdowicz.cfolks.pl/repo && php artisan schedule:run >> /dev/null 2>&1` as a cron job in the DirectAdmin panel.

## Out of Scope

The following were not evaluated in this research:
- A managed/serverless platform bake-off (Cloudflare, Vercel, Netlify, Fly.io, Railway, Render) — `tech-stack.md` already committed to self-host before this document was written; re-run `/10x-tech-stack-selector` first if that decision needs revisiting.
- Docker image configuration.
- CI/CD pipeline implementation details beyond the deploy step outline above (the actual `.github/workflows/*.yml` is written during implementation).
- Production-scale architecture (multi-region, HA, DR) — out of scope for a single-user MVP on shared hosting.
