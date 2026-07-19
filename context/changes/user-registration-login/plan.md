# Rejestracja i logowanie użytkownika — Plan implementacji

## Przegląd

Wdrażamy S-01 z mapy drogowej: użytkownik może się zarejestrować i zalogować (FR-001). To pierwszy pionowy wycinek funkcjonalności projektu i fundament, od którego zależy cała reszta mapy drogowej (S-02..S-06). Implementacja opiera się na `laravel/breeze` (stack Blade), dostosowanym do single-user MVP bez skonfigurowanej poczty na produkcji: bez weryfikacji e-mail, bez self-service resetu hasła, z zaostrzoną polityką hasła.

## Analiza stanu obecnego

Baza kodu to czysty szkielet Laravel 13: `routes/web.php` ma tylko trasę `/`, jedyny widok to `welcome.blade.php`, `composer.json` nie zawiera żadnego pakietu auth (`laravel/breeze`/`fortify`/`sanctum`). Migracja `database/migrations/0001_01_01_000000_create_users_table.php` już tworzy tabele `users`, `password_reset_tokens` i `sessions` (domyślny bundle Laravela) — Breeze nie wymaga nowej migracji. Frontend ma już zeskafoldowane Blade + Vite + Tailwind CSS 4. `.env`/`.env.example` ustawia `MAIL_MAILER=log` lokalnie, a `context/foundation/infrastructure.md` nie wspomina o żadnej skonfigurowanej poczcie na produkcji (cyberFolks) — stąd decyzja o pominięciu funkcji zależnych od maila.

### Kluczowe odkrycia:

- `laravel/breeze` v2.4.2 deklaruje `illuminate/*: ^11.0|^12.0|^13.0` i `php: ^8.2.0` — kompatybilny z tym projektem (`composer show -a laravel/breeze`, potwierdzone na żywo).
- `app/Models/User.php` (obecny szkielet) nie implementuje `MustVerifyEmail` — weryfikacja e-mail jest więc domyślnie nieaktywna nawet po instalacji Breeze; nie trzeba nic wyłączać, tylko potwierdzić, że tak zostaje.
- Breeze blade generuje m.in.: kontrolery w `app/Http/Controllers/Auth/`, widoki w `resources/views/auth/`, `resources/views/dashboard.blade.php`, `resources/views/profile/`, trasy w `routes/auth.php` (dołączane z `routes/web.php`), oraz testy Feature w `tests/Feature/Auth/` (`RegistrationTest`, `AuthenticationTest`, `PasswordResetTest`, `PasswordConfirmationTest`, `PasswordUpdateTest`, `EmailVerificationTest`) i `tests/Feature/ProfileTest.php`.
- Domyślny wygenerowany `RegistrationTest` używa hasła `'password'` (bez wielkich liter/cyfr/symboli) — po dodaniu zaostrzonej polityki hasła ten test przestanie przechodzić, jeśli fixture nie zostanie zaktualizowany.

## Pożądany stan końcowy

Użytkownik może otworzyć `/register`, założyć konto (e-mail + hasło spełniające zaostrzoną politykę), zostać automatycznie zalogowanym i przekierowanym na `/dashboard`; może się wylogować i ponownie zalogować przez `/login`. Nie ma widocznego linku ani trasy do resetu hasła przez e-mail ani do weryfikacji adresu e-mail — obie funkcje są całkowicie usunięte (nie tylko ukryte), więc próba wejścia na ich dawne trasy zwraca 404. Cały zestaw testów (`php artisan test`) jest zielony, a `./vendor/bin/pint --test` nie zgłasza błędów formatowania.

Weryfikacja: `php artisan test`, ręczne przejście przez rejestrację → wylogowanie → logowanie w przeglądarce lokalnie (SQLite), potwierdzenie 404 na `/forgot-password` i `/verify-email`.

## Czego NIE robimy

- Weryfikacji adresu e-mail (wymaga skonfigurowanej poczty — poza zakresem tej zmiany).
- Self-service resetu hasła przez e-mail (ta sama przyczyna; w razie potrzeby hasło zmienia się ręcznie przez `php artisan tinker` na serwerze).
- Konfiguracji SMTP na produkcji.
- Customizacji wizualnej widoków logowania/rejestracji poza domyślnym UI Breeze (Tailwind) — dopasowanie brandingu zostaje na później.
- Usuwania domyślnej strony profilu Breeze (`/profile`, edycja danych/hasła, usuwanie konta) — zostaje jako standardowy element scaffoldu, nieistotny dla FR-001, ale niewymagający dodatkowej pracy.
- Ograniczania dostępu do `/register` (zostaje publicznie dostępna, zgodnie z FR-001).

## Podejście do implementacji

Użycie oficjalnego generatora Breeze (`php artisan breeze:install blade`) zamiast ręcznej implementacji — daje sprawdzony, przetestowany kod (throttling logowania, walidacja, testy) przy budżecie 3 tygodni pracy po godzinach. Po instalacji usuwamy (nie tylko wyłączamy) kod związany z resetem hasła i weryfikacją e-mail, żeby nie zostawiać martwych, nieosiągalnych tras w kodzie. Polityka hasła jest wzmacniana centralnie przez `Password::defaults()` w service providerze, tak by obowiązywała wszędzie, gdzie Laravel waliduje hasła (rejestracja, zmiana hasła w profilu).

## Faza 1: Instalacja i scaffolding Breeze

### Przegląd

Zainstalować `laravel/breeze` (stack Blade), wygenerować kod, zbudować assety, potwierdzić że domyślny scaffold działa end-to-end zanim przytniemy go do zakresu MVP.

### Wymagane zmiany:

#### 1. Zależności Composer

**Plik**: `composer.json`

**Cel**: Dodać `laravel/breeze` jako zależność dev.

**Kontrakt**: `composer require laravel/breeze --dev`, wersja zgodna z `^2.4`.

#### 2. Generator Breeze (stack Blade)

**Pliki**: `routes/web.php`, `routes/auth.php` (nowy), `app/Http/Controllers/Auth/*` (nowe), `app/Http/Requests/Auth/LoginRequest.php` (nowy), `app/Models/User.php`, `resources/views/auth/*`, `resources/views/dashboard.blade.php`, `resources/views/profile/*`, `resources/views/layouts/*`, `resources/views/components/*`, `tests/Feature/Auth/*`, `tests/Feature/ProfileTest.php`, `package.json`

**Cel**: Uruchomić `php artisan breeze:install blade --dark` (bez `--dark` jeśli niepotrzebne — użyć wariantu domyślnego), co wygeneruje standardowy przepływ auth Laravela: rejestracja, logowanie, wylogowanie, potwierdzenie hasła, reset hasła, weryfikacja e-mail, edycja profilu — wszystko w jednym kroku generatora.

**Kontrakt**: Po instalacji `routes/web.php` musi `require __DIR__.'/auth.php'` i zawierać trasę `/dashboard` chronioną middleware `auth` + (tymczasowo, do Fazy 2) `verified`. `app/Models/User.php` NIE implementuje `MustVerifyEmail` (potwierdzić — to już stan domyślny szkieletu, generator go nie zmienia).

#### 3. Assety frontendowe

**Plik**: `package.json`, `resources/css/app.css`, `resources/js/app.js` (jeśli Breeze doda Alpine.js)

**Cel**: Doinstalować zależności JS wymagane przez Breeze (Alpine.js do dropdownów w nawigacji) i zbudować assety.

**Kontrakt**: `npm install && npm run build` kończy się bez błędów; `public/build/manifest.json` zawiera skompilowane assety.

#### 4. Migracje

**Plik**: `database/migrations/`

**Cel**: Potwierdzić, że `php artisan migrate` nie wymaga nowych migracji (tabele `users`, `password_reset_tokens`, `sessions` już istnieją w bazowej migracji Laravela).

**Kontrakt**: `php artisan migrate` na czystej bazie SQLite kończy się bez błędów i bez nowych plików migracji od Breeze.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `composer install` kończy się bez błędów
- `php artisan migrate --force` na świeżej bazie SQLite przechodzi bez błędów
- `npm install && npm run build` kończy się bez błędów
- Pełny zestaw wygenerowanych testów przechodzi: `php artisan test --filter=Auth`
- `php artisan route:list` pokazuje trasy `register`, `login`, `logout`, `dashboard`

#### Weryfikacja ręczna:

- Otwarcie `/register` w przeglądarce (lokalnie, `php artisan serve`), założenie konta testowego, przekierowanie na `/dashboard`
- Wylogowanie i ponowne zalogowanie przez `/login` działa

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że testy ręczne zakończyły się sukcesem, zanim przejdziesz do następnej fazy.

---

## Faza 2: Dopasowanie do zakresu MVP (bez weryfikacji e-mail, bez resetu hasła, zaostrzona polityka hasła)

### Przegląd

Usunąć z wygenerowanego scaffoldu funkcje zależne od poczty (reset hasła, weryfikacja e-mail) jako martwy kod, a nie tylko ukryte linki, oraz wymusić zaostrzoną politykę hasła przy rejestracji i zmianie hasła.

### Wymagane zmiany:

#### 1. Usunięcie resetu hasła

**Pliki**: `routes/auth.php`, `app/Http/Controllers/Auth/PasswordResetLinkController.php` (usunąć), `app/Http/Controllers/Auth/NewPasswordController.php` (usunąć), `resources/views/auth/forgot-password.blade.php` (usunąć), `resources/views/auth/reset-password.blade.php` (usunąć), `resources/views/auth/login.blade.php`

**Cel**: Usunąć trasy `password.request`, `password.email`, `password.reset`, `password.store` oraz ich kontrolery i widoki. Usunąć link "Forgot your password?" z widoku logowania.

**Kontrakt**: Po zmianie, `GET /forgot-password` zwraca 404. `php artisan route:list` nie zawiera żadnej trasy `password.*`.

#### 2. Usunięcie weryfikacji e-mail

**Pliki**: `routes/auth.php`, `app/Http/Controllers/Auth/EmailVerificationPromptController.php` (usunąć), `app/Http/Controllers/Auth/VerifyEmailController.php` (usunąć), `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` (usunąć), `resources/views/auth/verify-email.blade.php` (usunąć), `routes/web.php`

**Cel**: Usunąć trasy `verification.notice`, `verification.verify`, `verification.send`. Usunąć middleware `verified` z trasy `/dashboard` (zbędny, skoro `MustVerifyEmail` i tak nie jest zaimplementowane, ale trasa nie powinna odwoływać się do middleware'u dla nieużywanej funkcji).

**Kontrakt**: Po zmianie, `GET /verify-email` zwraca 404. Trasa `dashboard` w `routes/web.php` używa tylko `middleware(['auth'])`, bez `'verified'`.

#### 3. Zaostrzona polityka hasła

**Plik**: `app/Providers/AppServiceProvider.php`

**Cel**: Wymusić globalną regułę walidacji hasła (min. 8 znaków, wielkie i małe litery, cyfry, symbole) dla każdego miejsca, gdzie Laravel waliduje hasło — rejestracja i zmiana hasła w profilu.

**Kontrakt**: W metodzie `boot()` dodać `Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());` (import `Illuminate\Validation\Rules\Password`).

#### 4. Aktualizacja testów pod nowy zakres

**Pliki**: `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/PasswordResetTest.php` (usunąć), `tests/Feature/Auth/EmailVerificationTest.php` (usunąć), `tests/Feature/Auth/PasswordUpdateTest.php`, `tests/Feature/Auth/PasswordConfirmationTest.php`

**Cel**: Usunąć testy dla usuniętych funkcji (reset hasła, weryfikacja e-mail). Zaktualizować fixture hasła w `RegistrationTest` i `PasswordUpdateTest` na wartość spełniającą nową politykę (np. `'Password123!'` zamiast `'password'`).

**Kontrakt**: Wszystkie pozostałe testy w `tests/Feature/Auth/` i `tests/Feature/ProfileTest.php` przechodzą z nową polityką hasła.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan route:list` nie zawiera żadnej trasy `password.*` ani `verification.*`
- Pełny zestaw testów przechodzi: `php artisan test`
- `./vendor/bin/pint --test` nie zgłasza błędów formatowania

#### Weryfikacja ręczna:

- `GET /forgot-password` i `GET /verify-email` zwracają 404 w przeglądarce
- Próba rejestracji ze słabym hasłem (np. `password123`) pokazuje błąd walidacji z opisem wymagań
- Rejestracja z hasłem spełniającym politykę (np. `Password123!`) kończy się sukcesem i zalogowaniem

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że testy ręczne zakończyły się sukcesem.

---

## Strategia testowania

### Testy jednostkowe:

- Brak nowej logiki domenowej w tej zmianie (auth to standardowy mechanizm Laravela) — pokrycie jednostkowe nie jest wymagane poza tym, co generuje Breeze.

### Testy integracyjne:

- Rejestracja z poprawnymi danymi → zalogowanie automatyczne → przekierowanie na `/dashboard`
- Logowanie z poprawnymi/niepoprawnymi danymi
- Wylogowanie
- Rejestracja ze słabym hasłem → błąd walidacji

### Kroki testowania ręcznego:

1. Zarejestrować konto testowe przez `/register` w przeglądarce lokalnie
2. Wylogować się i zalogować ponownie przez `/login`
3. Sprawdzić, że `/forgot-password` i `/verify-email` zwracają 404
4. Spróbować zarejestrować się słabym hasłem i potwierdzić czytelny komunikat błędu

## Uwagi dotyczące wydajności

Brak — standardowy mechanizm auth Laravela, ruch single-user, brak realnych wymagań wydajnościowych poza NFR z PRD (< 1s p95, spełnione domyślnie).

## Uwagi dotyczące migracji

Brak nowych migracji — tabele `users`, `password_reset_tokens`, `sessions` już istnieją w bazowym szkielecie Laravela. `password_reset_tokens` pozostaje w schemacie nieużywana (tańsze niż migracja usuwająca tabelę, którą Breeze i tak mógłby oczekiwać przy przyszłych aktualizacjach frameworka).

## Referencje

- Mapa drogowa: `context/foundation/roadmap.md` (S-01)
- PRD: `context/foundation/prd.md` (FR-001, `## Access Control`)
- Stack: `context/foundation/tech-stack.md`

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Instalacja i scaffolding Breeze

#### Automatyczne

- [x] 1.1 composer install kończy się bez błędów — 68eee94
- [x] 1.2 php artisan migrate --force na świeżej bazie SQLite przechodzi bez błędów — 68eee94
- [x] 1.3 npm install && npm run build kończy się bez błędów — 68eee94
- [x] 1.4 Pełny zestaw wygenerowanych testów przechodzi: php artisan test --filter=Auth — 68eee94
- [x] 1.5 php artisan route:list pokazuje trasy register, login, logout, dashboard — 68eee94

#### Ręczne

- [x] 1.6 Rejestracja konta testowego przez /register, przekierowanie na /dashboard — 68eee94
- [x] 1.7 Wylogowanie i ponowne zalogowanie przez /login — 68eee94

### Faza 2: Dopasowanie do zakresu MVP

#### Automatyczne

- [x] 2.1 php artisan route:list nie zawiera trasy password.* ani verification.*
- [x] 2.2 Pełny zestaw testów przechodzi: php artisan test
- [x] 2.3 ./vendor/bin/pint --test nie zgłasza błędów formatowania

#### Ręczne

- [x] 2.4 GET /forgot-password i GET /verify-email zwracają 404
- [x] 2.5 Rejestracja ze słabym hasłem pokazuje błąd walidacji
- [x] 2.6 Rejestracja z mocnym hasłem kończy się sukcesem i zalogowaniem
