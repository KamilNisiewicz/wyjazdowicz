# Repository Guidelines

Wyjazdowicz to aplikacja webowa w Laravel 13 (PHP 8.3) do śledzenia wyjazdów kibica na mecze. Zob. @context/foundation/prd.md dla wymagań produktowych i @context/foundation/tech-stack.md dla decyzji o stosie.

## Twarde zasady

- Ten stos świadomie zaakceptował lukę w typowaniu (`quality_override: true` w @context/foundation/tech-stack.md, PHP nie wymusza typów tak jak TypeScript). Kompensuj to jawnie: deklaruj typy właściwości i zwracanych wartości w każdej nowej klasie/metodzie (`public function index(): View`, `protected int $id`) — nie polegaj na wnioskowaniu typów.
- Nie ma jeszcze skonfigurowanego PHPStan/Larastan. Przy dodawaniu logiki biznesowej (liczenie dystansu, bilans W/D/L, wskaźnik "pechowego kibica") pokrywaj ją testami — statyczna analiza jeszcze nie łapie regresji.

## Struktura projektu

- `app/Models`, `app/Http/Controllers`, `app/Providers` — standardowy układ Laravela.
- `database/migrations`, `database/factories`, `database/seeders` — schemat i dane testowe.
- `routes/web.php` — trasy HTTP (obecnie tylko `/`).
- `resources/views` — szablony Blade.
- Baza danych konfigurowana w `config/database.php`; deweloperski `.env` ustawia SQLite, celem produkcyjnym jest self-host (patrz @context/foundation/tech-stack.md).

## Polecenia

- `composer run dev` — uruchamia jednocześnie serwer, kolejkę, logi (pail) i Vite.
- `php artisan serve` — sam serwer deweloperski.
- `composer test` lub `php artisan test` — pełny zestaw testów PHPUnit.
- `npm run dev` / `npm run build` — assety frontendowe (Vite + Tailwind).
- `php artisan migrate` — zastosuj migracje.

## Styl kodu

- 4 spacje wcięcia, LF, końcowy newline pliku (`.editorconfig`).
- Formatowanie przez Laravel Pint: `./vendor/bin/pint`. Brak własnego `pint.json`, więc obowiązuje domyślny preset `laravel`.

## Testy

- PHPUnit, testy w `tests/Unit` i `tests/Feature` (konfiguracja: `phpunit.xml`).
- Pojedynczy test: `php artisan test --filter=<TestName>`.
- Baza testowa jest izolowana od dev: SQLite w pamięci (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` w `phpunit.xml`), nie czyta `.env`.

## Commity

- Dotychczasowa historia: komunikaty po polsku, tryb rozkazujący, bez prefiksów typu (np. „Zescaffolduj projekt Laravel”, „Domknij PRD, wybierz stos (Laravel), przestań śledzić skille 10x-cli”). Trzymaj się tego wzorca.
- **Krótko.** Jedna linijka, bez wieloakapitowego body, bez wypunktowań, bez trailera `Co-Authored-By`. Użytkownik jawnie tego zażądał — nie rozwijaj commit message w dłuższe wyjaśnienie, nawet gdy zmiana jest duża.
- Brak jeszcze pipeline'u CI (`.github/workflows/`) — do skonfigurowania w dalszej pracy nad projektem.
