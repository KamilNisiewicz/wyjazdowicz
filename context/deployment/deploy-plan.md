---
project: wyjazdowicz
approved_at: 2026-07-18
platform: self-host (cyberFolks, DirectAdmin/CloudLinux)
---

## Webroot layout (confirmed 2026-07-18 — differs from the original plan)

The original plan symlinked `public_html` → `repo/public` (standard Laravel-on-shared-hosting pattern). **This does not work on cyberFolks**: the DirectAdmin PHP Selector panel refuses to manage a domain whose `public_html` is a symlink ("Domena posiada błędne uprawnienia lub katalog jest symlinkiem") and silently falls back the domain to the account's default PHP (8.2), breaking the `^8.3` requirement in `composer.json`.

**Actual layout in use**:
- `~/domains/wyjazdowicz.cfolks.pl/repo/` — full Laravel app (`vendor/`, `app/`, `bootstrap/`, `storage/`, `.env`, `artisan`). Not web-accessible directly.
- `~/domains/wyjazdowicz.cfolks.pl/public_html/` — a **real directory** (required by the PHP Selector), containing:
  - `index.php` — copied from `repo/public/index.php`, with three `require`/`file_exists` paths hand-patched from `__DIR__.'/../...'` to `__DIR__.'/../repo/...'` (maintenance-mode check, Composer autoloader, `bootstrap/app.php`). This file is **not** overwritten by deploys — it's static bootstrap boilerplate that only needs updating if Laravel's own `public/index.php` structure changes upstream.
  - `.htaccess` — copied once from `repo/public/.htaccess` (Laravel's default rewrite rules). Also not touched by routine deploys.
  - `build/` — Vite output, **overwritten on every deploy** (this is the only part of `public_html` the CI pipeline touches).

**Confirmed gotcha #3 (2026-07-19, hit during S-01/`user-registration-login`)**: Laravel's `public_path()` (used internally by the `@vite()` Blade directive to locate `build/manifest.json`) resolves relative to `repo/public/`, **not** `public_html/`, regardless of where `index.php` physically sits — the framework's base path is fixed by `bootstrap/app.php`'s own location inside `repo/`. Since the CI pipeline only rsyncs built assets to `public_html/build/` (step 4 below), `repo/public/build/` was left containing a stale one-time manifest from before this was ever deployed — Laravel kept reading *that* stale manifest to generate `<link>`/`<script>` tags, while the browser requested files only present under the *current* `public_html/build/`, producing 404s on every CSS/JS asset even though the deploy workflow reported success and the live `public_html/build/manifest.json` was correct. **Fix (one-time, done 2026-07-19)**: `cd ~/domains/wyjazdowicz.cfolks.pl/repo/public && rm -rf build && ln -s ../../public_html/build build` — makes `repo/public/build` a symlink into `public_html/build`, so Laravel and the webserver always agree on the same physical files after every future deploy, with no pipeline change needed. See also `context/foundation/lessons.md`.

## Jednorazowa konfiguracja ręczna

Checklista wykonana ręcznie na serwerze (wymaga interaktywnego dostępu do panelu DirectAdmin i sesji SSH — nie da się tego zautomatyzować z tej sesji).

- [x] **Baza danych.** Utworzona w DirectAdmin: `yogiber_wyjazdowicz` (DB + user). **Potwierdzony gotcha**: użytkownik MySQL na DirectAdmin jest przypisany do hosta `localhost`, nie `127.0.0.1` — `DB_HOST=127.0.0.1` dawał `Access denied`, `DB_HOST=localhost` działa. `.env` musi używać `localhost`.
- [x] **Wersja PHP.** PHP 8.5 ustawione dla domeny przez CloudLinux PHP Selector (2026-07-18), potwierdzone przez `phpinfo()` (PHP 8.5.6-CF0.1) i finalnie przez działającą stronę. **Potwierdzone gotcha #1**: selector pierwotnie obejmował tylko ruch HTTP (lsapi) — `php -v` w SSH nadal 8.2.31. Binarka 8.5: `/opt/alt/php85/usr/bin/php` (brak wygodnego symlinku `/usr/bin/php85`) — wszystkie komendy poniżej i w `.github/workflows/deploy.yml` wskazują tę ścieżkę jawnie. **Potwierdzone gotcha #2**: selector w panelu odmawia działania, gdy `public_html` jest symlinkiem — patrz sekcja "Webroot layout" wyżej.
- [x] **Klucz SSH do CI.** Wygenerowany, dopisany do `~/.ssh/authorized_keys`.
- [x] **Sekrety w repo GitHub** (Settings → Secrets and variables → Actions): `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY` — wszystkie ustawione i zweryfikowane nazwami (`gh secret list`).
- [x] **Usunięto `test.php`** (publiczny `phpinfo()`) z `public_html`.
- [x] **Pierwszy deploy ręcznie przez SSH** — wykonany. `git clone`, `composer install` (pod `/opt/alt/php85/usr/bin/php`), frontend zbudowany lokalnie (Node niedostępny na koncie hostingowym) i wgrany przez `scp`, `.env` skonfigurowany (z poprawką `DB_HOST=localhost`), `migrate --force` przeszedł, `public_html` przebudowany jako prawdziwy katalog z hand-patched `index.php` (patrz "Webroot layout"), cache produkcyjny odpalony.
- [x] **Weryfikacja**: `https://wyjazdowicz.cfolks.pl/` zwraca HTTP 200 i serwuje Laravela (potwierdzone `curl` + zawartość strony).
- [x] **Symlink `repo/public/build` → `public_html/build`** (2026-07-19, patrz "Confirmed gotcha #3" w sekcji "Webroot layout" wyżej) — bez tego Laravel czyta martwy, nigdy nieaktualizowany manifest Vite i strona 404-uje na CSS/JS mimo zielonego deployu.

Jednorazowa konfiguracja **zakończona**. Automatyczny deploy poniżej zadziała od następnego `git push`/merge do `master` — nie był jeszcze przetestowany end-to-end (pierwszy deploy poszedł ręcznie, żeby zweryfikować layout przed automatyzacją).

## Automatyczny przepływ CI/CD

Zdefiniowany w `.github/workflows/deploy.yml`, trigger: `push` na `master`.

1. Checkout kodu.
2. Setup PHP 8.5 (zgodnie z wersją ustawioną na serwerze) — używane tylko do walidacji w CI, nie do faktycznego builda produkcyjnego kodu PHP (ten dzieje się na serwerze w kroku 5).
3. Setup Node (LTS) + `npm ci` + `npm run build` → generuje `public/build/` w checkout CI.
4. `rsync` samego `public/build/` na serwer, **do `public_html/build/`** (nie `repo/public/build/` — `public_html` to osobny prawdziwy katalog, patrz "Webroot layout"). Nic innego w `public_html` nie jest nadpisywane — `index.php` i `.htaccess` zostają jak są.
5. SSH-exec na serwerze (`appleboy/ssh-action`), w `repo/`: `git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache` (wszystko pod `/opt/alt/php85/usr/bin/php`), z `set -e` — pierwszy nieudany krok przerywa i failuje joba.

Frontend budowany jest w CI (Node gwarantowany na runnerze GitHuba), nie na koncie hostingowym — unika to zależności od wersji Node na sharedzie (potwierdzone: `npm` nie jest tam zainstalowany) i obciążania limitów CPU/LVE konta (`context/foundation/infrastructure.md`, rejestr ryzyka).

## Znane luki / do zrobienia później

- Brak środowiska staging — każdy merge do `master` trafia bezpośrednio na produkcję (świadomie zaakceptowane ryzyko z `infrastructure.md`, kompensowane lokalnym testowaniem na SQLite przed mergem).
- Backupy bazy nie są jeszcze skonfigurowane — patrz rejestr ryzyka w `infrastructure.md` (retencja backupów cyberFolks do potwierdzenia w panelu).
- Cron dla `php artisan schedule:run` nie jest jeszcze skonfigurowany — dodać w DirectAdmin, gdy pojawi się pierwsza funkcja wymagająca harmonogramu.
- Automatyczny przepływ CI/CD nie był jeszcze przetestowany end-to-end (dopiero pierwszy `git push` do `master` po scaleniu tych poprawek to zweryfikuje).
- `public_html/index.php` i `.htaccess` są utrzymywane ręcznie, poza kontrolą wersji repo — jeśli Laravel zmieni strukturę `public/index.php` w przyszłej aktualizacji frameworka, trzeba będzie ręcznie zsynchronizować zmiany na serwer.
