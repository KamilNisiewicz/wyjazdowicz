---
project: wyjazdowicz
approved_at: 2026-07-18
platform: self-host (cyberFolks, DirectAdmin/CloudLinux)
---

## Jednorazowa konfiguracja ręczna

Checklista do wykonania raz, zanim automatyczny deploy (`.github/workflows/deploy.yml`) zacznie działać. Wymaga interaktywnego dostępu do panelu DirectAdmin i sesji SSH — nie da się tego zautomatyzować z tej sesji.

- [ ] **Baza danych.** Panel DirectAdmin → "MySQL Management" → utwórz bazę i dedykowanego użytkownika dla `wyjazdowicz`. Zanotuj nazwę bazy, użytkownika, hasło.
- [x] **Wersja PHP.** PHP 8.5 ustawione dla domeny `wyjazdowicz.cfolks.pl` przez CloudLinux PHP Selector w panelu (2026-07-18). Do potwierdzenia: `php -v` w sesji SSH pokazuje 8.5.x — selector czasem obejmuje tylko ruch HTTP, nie CLI. Jeśli CLI nadal pokazuje 8.2, użyj `/opt/alt/php85/usr/bin/php` bezpośrednio.
- [ ] **Klucz SSH do CI** (osobny od klucza osobistego):
  ```bash
  ssh-keygen -t ed25519 -C "github-actions-deploy-wyjazdowicz" -f ~/.ssh/wyjazdowicz_deploy -N ""
  ```
  Dopisz `~/.ssh/wyjazdowicz_deploy.pub` do `~/.ssh/authorized_keys` na serwerze.
- [ ] **Sekrety w repo GitHub** (Settings → Secrets and variables → Actions):
  - `SSH_HOST` = `s17.cyber-folks.pl`
  - `SSH_PORT` = port aktualnie używany do logowania (sprawdź, jakiego portu używa Twoja działająca sesja SSH)
  - `SSH_USER` = `yogiber`
  - `SSH_PRIVATE_KEY` = zawartość prywatnej połowy klucza z poprzedniego punktu
- [ ] **Pierwszy deploy ręcznie przez SSH** (zanim CI cokolwiek dotknie):
  ```bash
  cd ~/domains/wyjazdowicz.cfolks.pl
  git clone <repo-url> repo
  cd repo
  composer install --no-dev --optimize-autoloader
  npm ci && npm run build   # opcjonalnie — pierwszy przebieg CI i tak nadpisze public/build/
  cp .env.example .env
  # edytuj .env: APP_ENV=production, APP_URL=https://wyjazdowicz.cfolks.pl,
  # DB_CONNECTION=mysql + dane bazy z pierwszego punktu
  php artisan key:generate
  php artisan migrate --force
  rm -rf ../public_html
  ln -s ~/domains/wyjazdowicz.cfolks.pl/repo/public ~/domains/wyjazdowicz.cfolks.pl/public_html
  php artisan config:cache && php artisan route:cache
  ```
  Zweryfikuj: `https://wyjazdowicz.cfolks.pl/` pokazuje stronę powitalną Laravela, nie domyślną stronę DirectAdmin.

Po odhaczeniu wszystkiego powyżej, każdy `git push`/merge do `master` uruchamia automatyczny deploy poniżej.

## Automatyczny przepływ CI/CD

Zdefiniowany w `.github/workflows/deploy.yml`, trigger: `push` na `master`.

1. Checkout kodu.
2. Setup PHP 8.5 (zgodnie z wersją ustawioną na serwerze) — używane tylko do walidacji w CI, nie do faktycznego builda produkcyjnego kodu PHP (ten dzieje się na serwerze w kroku 5).
3. Setup Node (LTS) + `npm ci` + `npm run build` → generuje `public/build/`.
4. `rsync` samego `public/build/` na serwer (`burnett01/rsync-deployments`). Nic innego nie jest nadpisywane — `.env`, `storage/`, baza zostają nietknięte.
5. SSH-exec na serwerze (`appleboy/ssh-action`): `git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`, z `set -e` — pierwszy nieudany krok przerywa i failuje joba (deploy nigdy nie jest cicho oznaczany jako sukces mimo błędu).

Frontend budowany jest w CI (Node gwarantowany na runnerze GitHuba), nie na koncie hostingowym — unika to zależności od wersji Node na sharedzie i obciążania limitów CPU/LVE konta (`context/foundation/infrastructure.md`, rejestr ryzyka).

## Znane luki / do zrobienia później

- Brak środowiska staging — każdy merge do `master` trafia bezpośrednio na produkcję (świadomie zaakceptowane ryzyko z `infrastructure.md`, kompensowane lokalnym testowaniem na SQLite przed mergem).
- Backupy bazy nie są jeszcze skonfigurowane — patrz rejestr ryzyka w `infrastructure.md` (retencja backupów cyberFolks do potwierdzenia w panelu).
- Cron dla `php artisan schedule:run` nie jest jeszcze skonfigurowany — dodać w DirectAdmin, gdy pojawi się pierwsza funkcja wymagająca harmonogramu.
