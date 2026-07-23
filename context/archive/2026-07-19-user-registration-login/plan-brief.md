# Rejestracja i logowanie użytkownika — Krótki plan

> Pełny plan: `context/changes/user-registration-login/plan.md`

## Co i dlaczego

Wdrażamy S-01 z mapy drogowej: rejestracja i logowanie użytkownika (FR-001) przez `laravel/breeze`. To fundament, od którego zależy cała reszta mapy drogowej (S-02..S-06) — bez konta nie da się ustawić ulubionej drużyny, dodać meczu ani zobaczyć statystyk.

## Punkt wyjścia

Świeży szkielet Laravel 13 bez żadnego pakietu auth — `routes/web.php` ma tylko `/`, jedyny widok to `welcome.blade.php`. Tabele `users`, `password_reset_tokens`, `sessions` już istnieją w bazowej migracji Laravela. Frontend ma zeskafoldowane Blade + Vite + Tailwind CSS 4.

## Pożądany stan końcowy

Użytkownik rejestruje się na `/register`, zostaje automatycznie zalogowany i widzi `/dashboard`; może się wylogować i zalogować ponownie. Reset hasła i weryfikacja e-mail są całkowicie usunięte (404, nie tylko ukryte) — produkcja nie ma jeszcze skonfigurowanej poczty. Hasło musi spełniać zaostrzoną politykę (wielkie/małe litery, cyfry, symbole).

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
|---|---|---|---|
| Pakiet auth | Laravel Breeze (stack Blade) | Pasuje do już zeskafoldowanego Blade+Vite+Tailwind, sprawdzony kod zamiast pisania od zera w 3-tygodniowym budżecie czasowym | Plan |
| Weryfikacja e-mail | Wyłączona (usunięta) | Produkcja nie ma skonfigurowanej poczty; przy jednym realnym użytkowniku ryzyko praktyczne jest zerowe | Plan |
| Reset hasła | Pominięty (usunięty, nie tylko ukryty) | Ta sama przyczyna braku poczty; w razie potrzeby hasło zmienia się ręcznie przez `tinker` | Plan |
| Rejestracja | Publicznie dostępna | Zgodne wprost z FR-001, gotowe pod przyszłą wielo-użytkownikowość wspomnianą w PRD | Plan |
| Polityka hasła | Zaostrzona (mixedCase + numbers + symbols) | Wyższe bezpieczeństwo konta bez dodatkowego kosztu implementacyjnego | Plan |
| Testowanie | Domyślne testy Breeze, przycięte o usunięte funkcje | Pokrycie kluczowych ścieżek bez pisania od zera; auth samo w sobie nie jest logiką biznesową projektu | Plan |
| UI | Domyślny UI Breeze (Tailwind), bez customizacji | Zero dodatkowego czasu na stylowanie w zmianie-fundamencie; S-02+ i tak wprowadzą własny layout | Plan |

## Zakres

**W zakresie:** rejestracja, logowanie, wylogowanie, zmiana hasła w profilu (z potwierdzeniem obecnego hasła), zaostrzona polityka hasła, domyślna strona profilu Breeze.

**Poza zakresem:** weryfikacja e-mail, reset hasła przez e-mail, konfiguracja SMTP, customizacja wizualna widoków, ograniczanie dostępu do `/register`.

## Architektura / Podejście

Generator `php artisan breeze:install blade` tworzy standardowy przepływ auth Laravela w jednym kroku (kontrolery, widoki, trasy, testy). Faza 2 usuwa fizycznie (nie tylko ukrywa) kod związany z resetem hasła i weryfikacją e-mail, żeby nie zostawiać martwych, nieosiągalnych tras. Polityka hasła jest wymuszana centralnie przez `Password::defaults()` w `AppServiceProvider`, więc obowiązuje wszędzie (rejestracja, zmiana hasła).

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
|---|---|---|
| 1. Instalacja i scaffolding Breeze | Pełny domyślny scaffold auth działający lokalnie | Niezgodność wersji Breeze z Laravel 13 — już zweryfikowana (`composer show`), ryzyko niskie |
| 2. Dopasowanie do zakresu MVP | Usunięty reset/weryfikacja, zaostrzona polityka hasła, zaktualizowane testy | Domyślny test rejestracji Breeze używa hasła `'password'` — trzeba zaktualizować fixture, inaczej test czerwony po Fazie 2 |

**Wymagania wstępne:** brak (S-01 nie ma zależności w roadmapie).
**Szacowany nakład pracy:** ~1 sesja, 2 fazy.

## Otwarte ryzyka i założenia

- Zakładamy, że `php artisan breeze:install blade` w Laravel 13 generuje strukturę zgodną z opisem w planie (kontrolery/widoki/trasy) — do potwierdzenia przy faktycznym uruchomieniu w Fazie 1.
- Domyślna strona profilu Breeze (edycja danych, usuwanie konta) zostaje bez dodatkowej dyskusji — nieistotna dla FR-001, ale nie generuje dodatkowej pracy.

## Kryteria sukcesu (podsumowanie)

- Użytkownik może się zarejestrować, zalogować i wylogować przez przeglądarkę (w tym mobilną, zgodnie z NFR).
- `/forgot-password` i `/verify-email` zwracają 404.
- Słabe hasło jest odrzucane przy rejestracji z czytelnym komunikatem.
