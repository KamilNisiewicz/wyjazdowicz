# Ulubiona drużyna i lokalizacja "dom" — Plan implementacji

## Przegląd

Wdrażamy S-02 z mapy drogowej: użytkownik ustawia swoją ulubioną drużynę wraz z miastem, w którym rozgrywa ona mecze domowe (FR-002). To miasto staje się lokalizacją "dom" używaną przez S-03 do liczenia dystansu na mecze wyjazdowe. Miasto jest wpisywane ręcznie jako tekst i geokodowane przez darmowe API OpenStreetMap Nominatim (decyzja z mapy drogowej) — appka pokazuje znalezione kandydatury do wyboru, żeby literówka lub niejednoznaczna nazwa nie zepsuła dystansu dla wszystkich przyszłych meczów wyjazdowych naraz.

## Analiza stanu obecnego

Baza kodu po S-01 ma działającą rejestrację/logowanie (Laravel Breeze, Blade), ale poza `users` nie istnieje żaden model domenowy — brak tabel, kontrolerów czy widoków związanych z drużyną, meczami czy geokodowaniem. `routes/web.php` ma tylko `/`, `/dashboard` (chronione `auth`) i trasy profilu Breeze. `bootstrap/app.php` ma pusty blok `withMiddleware` — aliasy middleware trzeba dopiero zarejestrować (Laravel 13, brak `Kernel.php`). `config/services.php` nie zawiera wpisu dla żadnego zewnętrznego API. Projekt ma już ustanowiony wzorzec pełnej polskiej lokalizacji UI (`lang/pl.json`, `lang/pl/*`) wprowadzony w S-01 — każdy nowy widoczny tekst musi przez niego przejść.

### Kluczowe odkrycia:

- Laravel 13 rejestruje aliasy middleware przez `->withMiddleware(fn ($middleware) => $middleware->alias([...]))` w `bootstrap/app.php:14-16`, nie przez `Kernel.php`.
- `Illuminate\Support\Facades\Http` (Guzzle) jest już dostępny przez `laravel/framework` — nie trzeba dodawać żadnej nowej zależności Composer do wywołania Nominatim.
- Wzorzec walidacji w projekcie to dedykowane klasy `FormRequest` (`app/Http/Requests/ProfileUpdateRequest.php`), nie inline `$request->validate()` w kontrolerze — nowe żądania powinny iść tą samą drogą.
- `resources/views/layouts/navigation.blade.php:14-18,68-73` ma jeden link nawigacyjny (`Dashboard`) powielony w wersji desktop i mobilnej — nowy link "Drużyna" wymaga tego samego powielenia w obu miejscach.
- Test suite używa `RefreshDatabase` + SQLite `:memory:` (`phpunit.xml`) i `Http::fake()` jest standardowym mechanizmem Laravela do testowania wywołań `Http` bez realnego ruchu sieciowego — nie ma potrzeby integracyjnego testu uderzającego w prawdziwe Nominatim.

## Pożądany stan końcowy

Zalogowany użytkownik bez ustawionej drużyny, próbując wejść na `/dashboard`, zostaje przekierowany na `/team`. Tam wpisuje nazwę drużyny i miasto, appka pyta Nominatim i pokazuje listę znalezionych miast (nazwa + kraj) do wyboru; po wybraniu jednego i zapisaniu, użytkownik trafia na `/dashboard`, które jest już dostępne. Powtórne wejście na `/team` pokazuje wcześniej zapisane dane i pozwala je nadpisać (ten sam formularz służy do tworzenia i edycji). Błędna/nieistniejąca nazwa miasta lub niedostępność Nominatim skutkuje czytelnym błędem walidacji po polsku, bez zapisania niczego.

Weryfikacja: `php artisan test`, ręczne przejście przez `/team` lokalnie z realnym wywołaniem Nominatim (nie fake) dla co najmniej jednego znanego polskiego miasta, potwierdzenie przekierowania z `/dashboard` na `/team` dla świeżo zarejestrowanego konta.

## Czego NIE robimy

- Budowy tabeli/katalogu drużyn (np. lista klubów Ekstraklasy) — nazwa drużyny to wolny tekst, zgodnie z decyzją z mapy drogowej (brak ręcznej bazy stadionów).
- Współdzielenia geokodowania między wieloma użytkownikami/cache'a na poziomie tabeli — MVP jest single-user, wystarczy zapisać wynik raz na koncie. Generalny cache geokodowania miast meczów to zakres S-03, nie tego elementu.
- Ograniczania liczby wywołań Nominatim (throttling po stronie appki) — przy jednym użytkowniku wykonującym tę akcję sporadycznie, limit ~1 zapytanie/s Nominatim nie jest praktycznym ryzykiem.
- Middleware wymuszającego drużynę na trasach innych niż `/dashboard` — przyszłe trasy meczów (S-03+) dodadzą ten sam alias middleware, gdy powstaną; nie tworzymy ich teraz.
- Możliwości usunięcia drużyny (appka zakłada, że raz ustawiona drużyna jest tylko nadpisywana, nigdy kasowana do stanu pustego).
- Testu integracyjnego uderzającego w prawdziwe API Nominatim w ramach `php artisan test` (wolne, niedeterministyczne, łamie limit zapytań) — tylko `Http::fake()` w testach automatycznych, prawdziwe API tylko przy weryfikacji ręcznej.

## Podejście do implementacji

Osobna tabela `teams` (1:1 z `users`, zgodnie z wyborem użytkownika w sesji planowania) zamiast kolumn na `users` — czystszy podział, mimo że MVP ma dokładnie jedną drużynę na użytkownika. Geokodowanie jest synchroniczne (w trakcie żądania HTTP), bo to rzadka, jednorazowa akcja, nie pętla dodawania meczów z NFR <1s. Wybór kandydata z listy Nominatim jest przenoszony między krokiem wyszukania a krokiem zapisu przez ukryte pola formularza (nie sesję) — prostsze do przetestowania i wystarczające dla single-user appki, gdzie użytkownik i tak jest jedynym, kto mógłby "zmanipulować" własne dane. Middleware wymuszający drużynę jest generycznym aliasem (`team.set`) aplikowanym punktowo tylko do `/dashboard`, żeby nie duplikować logiki wykluczania tras `/team` z przekierowania.

## Faza 1: Model danych i serwis geokodowania Nominatim

### Przegląd

Dodać tabelę `teams` i relację na `User`, oraz samodzielny serwis wywołujący Nominatim, przetestowany w izolacji przez `Http::fake()` — zanim podłączymy go do jakiegokolwiek kontrolera.

### Wymagane zmiany:

#### 1. Migracja `teams`

**Plik**: `database/migrations/<timestamp>_create_teams_table.php`

**Cel**: Utworzyć tabelę przechowującą drużynę i geokodowaną lokalizację "dom" jednego użytkownika.

**Kontrakt**: Kolumny: `id`, `user_id` (unsignedBigInteger, `unique`, foreign key → `users.id`, `onDelete('cascade')`), `name` (string), `home_city` (string — pełna nazwa zwrócona przez Nominatim, np. "Warszawa, Polska"), `home_lat` (decimal 10,7), `home_lng` (decimal 10,7), `timestamps()`.

#### 2. Model `Team`

**Plik**: `app/Models/Team.php`

**Cel**: Reprezentować wiersz `teams`, z relacją zwrotną do właściciela.

**Kontrakt**: `belongsTo(User::class)`; `$fillable = ['user_id', 'name', 'home_city', 'home_lat', 'home_lng']`; `casts()` rzutuje `home_lat`/`home_lng` na `decimal:7`.

#### 3. Relacja na `User`

**Plik**: `app/Models/User.php`

**Cel**: Umożliwić `$user->team` i `$user->team()->create(...)`.

**Kontrakt**: Dodać metodę `team(): HasOne` zwracającą `$this->hasOne(Team::class)`.

#### 4. Serwis geokodowania

**Plik**: `app/Services/NominatimGeocoder.php`

**Cel**: Odizolować wywołanie zewnętrznego API Nominatim za jedną metodą, którą można podmienić/fake'ować w testach i ponownie wykorzystać w S-03.

**Kontrakt**: Publiczna metoda `search(string $query): array`, zwraca listę asocjacyjnych tablic `['display_name' => string, 'lat' => float, 'lon' => float]` (pusta tablica, gdy brak wyników lub żądanie się nie powiedzie — nie rzuca wyjątku). Woła `GET https://nominatim.openstreetmap.org/search?q={query}&format=json&limit=5` przez fasadę `Http`, z nagłówkiem `User-Agent` z `config('services.nominatim.user_agent')` (Nominatim odrzuca żądania bez tego nagłówka) i timeoutem ~5s.

#### 5. Konfiguracja usługi

**Pliki**: `config/services.php`, `.env.example`

**Cel**: Umożliwić skonfigurowanie identyfikującego `User-Agent` wymaganego przez politykę użytkowania Nominatim, bez hardkodowania go w kodzie.

**Kontrakt**: `config/services.php` dostaje klucz `'nominatim' => ['user_agent' => env('NOMINATIM_USER_AGENT', 'Wyjazdowicz/1.0 (kamnisiewicz@gmail.com)')]`. `.env.example` dostaje `NOMINATIM_USER_AGENT="Wyjazdowicz/1.0 (kamnisiewicz@gmail.com)"`.

#### 6. Testy jednostkowe serwisu

**Plik**: `tests/Unit/NominatimGeocoderTest.php`

**Cel**: Zweryfikować mapowanie odpowiedzi Nominatim na strukturę serwisu oraz bezpieczne zachowanie przy błędzie, bez żadnego realnego ruchu sieciowego.

**Kontrakt**: Przypadki: (a) `Http::fake()` zwraca 2 pasujące miasta → `search()` zwraca 2 zmapowane wpisy; (b) `Http::fake()` zwraca pustą tablicę JSON → `search()` zwraca `[]`; (c) `Http::fake()` symuluje błąd 500 lub timeout → `search()` zwraca `[]` (nie rzuca wyjątku).

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Migracja stosuje się czysto: `php artisan migrate --force` na świeżej bazie SQLite
- Testy jednostkowe przechodzą: `php artisan test tests/Unit/NominatimGeocoderTest.php`
- `./vendor/bin/pint --test` nie zgłasza błędów formatowania

#### Weryfikacja ręczna:

- `php artisan tinker`: `(new App\Services\NominatimGeocoder)->search('Warszawa')` przeciw prawdziwemu Nominatim zwraca niepustą listę z sensownymi `display_name`/`lat`/`lon`

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że test ręczny zakończył się sukcesem, zanim przejdziesz do następnej fazy.

---

## Faza 2: Formularz drużyny, potwierdzenie geokodowania i wymuszone przekierowanie z dashboardu

### Przegląd

Podłączyć serwis z Fazy 1 do przepływu użytkownika: formularz nazwa+miasto → lista kandydatów do potwierdzenia → zapis → przekierowanie. Dodać middleware blokujące `/dashboard`, dopóki drużyna nie jest ustawiona, oraz link nawigacyjny.

### Wymagane zmiany:

#### 1. Żądania walidacji

**Pliki**: `app/Http/Requests/Team/SearchCityRequest.php` (nowy), `app/Http/Requests/Team/StoreTeamRequest.php` (nowy)

**Cel**: Walidować dane wejściowe obu kroków formularza, zgodnie z istniejącym wzorcem `ProfileUpdateRequest`.

**Kontrakt**: `SearchCityRequest`: `name` (`required|string|max:255`), `city` (`required|string|max:255`). `StoreTeamRequest`: `name` (`required|string|max:255`), `candidate` (`required|integer`, indeks wybranej pozycji), `candidates` (`required|array|min:1`), `candidates.*.display_name` (`required|string`), `candidates.*.lat` (`required|numeric`), `candidates.*.lon` (`required|numeric`). Oba `authorize()` zwracają `true` (dostęp już ograniczony przez middleware `auth` na trasie).

#### 2. Kontroler drużyny

**Plik**: `app/Http/Controllers/TeamController.php`

**Cel**: Obsłużyć trzy kroki: pokazanie formularza (prefilled, jeśli drużyna już istnieje), wyszukanie kandydatów przez `NominatimGeocoder`, zapis wybranego kandydata jako `Team` (create lub update — `updateOrCreate(['user_id' => $request->user()->id], [...])`).

**Kontrakt**: `edit(Request $request): View` — zwraca `team.edit` z `team: $request->user()->team` (może być `null`). `search(SearchCityRequest $request, NominatimGeocoder $geocoder): View|RedirectResponse` — woła `$geocoder->search($request->city)`; jeśli pusta lista, `back()->withErrors(['city' => __('Nie znaleziono miasta o podanej nazwie. Spróbuj dokładniejszej nazwy, np. z krajem.')])->withInput()`; inaczej zwraca widok `team.candidates` z listą kandydatów i nazwą drużyny do przeniesienia w ukrytych polach. `store(StoreTeamRequest $request): RedirectResponse` — bierze `$request->validated()['candidates'][$request->validated()['candidate']]`, zapisuje `Team`, `redirect()->route('dashboard')->with('status', 'team-updated')`.

#### 3. Widoki

**Pliki**: `resources/views/team/edit.blade.php` (nowy), `resources/views/team/candidates.blade.php` (nowy)

**Cel**: `edit.blade.php` — formularz z polami `name` i `city` (prefilled z `$team?->name` / `$team?->home_city`), POST do `team.search`. `candidates.blade.php` — lista radio-buttonów jednego kandydata na wiersz (`display_name`), plus ukryte pola z `lat`/`lon`/`display_name` per kandydat i ukryte pole `name` z kroku 1, POST do `team.store`.

**Kontrakt**: Oba widoki używają `<x-app-layout>` (ten sam layout co `dashboard.blade.php`/`profile/edit.blade.php`) i wszystkie widoczne stringi przechodzą przez `__()`, z tłumaczeniem dopisanym do `lang/pl.json` (kontynuacja wzorca lokalizacji z S-01 — patrz Dodatek do planu S-01 w `context/changes/user-registration-login/plan.md`).

#### 4. Middleware wymuszające drużynę

**Plik**: `app/Http/Middleware/EnsureTeamIsSet.php` (nowy)

**Cel**: Zablokować dostęp do `/dashboard`, dopóki zalogowany użytkownik nie ma zapisanej drużyny.

**Kontrakt**: `handle($request, Closure $next)` — jeśli `$request->user()->team === null`, zwraca `redirect()->route('team.edit')`; inaczej `$next($request)`.

#### 5. Rejestracja aliasu middleware

**Plik**: `bootstrap/app.php`

**Cel**: Udostępnić middleware z kroku 4 pod krótkim aliasem do użycia na trasach.

**Kontrakt**: W `->withMiddleware(function (Middleware $middleware) { ... })` dodać `$middleware->alias(['team.set' => \App\Http\Middleware\EnsureTeamIsSet::class]);`.

#### 6. Trasy

**Plik**: `routes/web.php`

**Cel**: Wystawić trzy trasy drużyny (chronione samym `auth`, bez `team.set` — inaczej `/team` przekierowywałoby samo do siebie) i dołożyć `team.set` do istniejącej trasy `dashboard`.

**Kontrakt**: `Route::middleware('auth')->group(...)` zawiera `GET /team` → `[TeamController::class, 'edit']` (`name('team.edit')`), `POST /team/search` → `[TeamController::class, 'search']` (`name('team.search')`), `POST /team` → `[TeamController::class, 'store']` (`name('team.store')`). Istniejąca trasa `dashboard` zmienia `->middleware(['auth'])` na `->middleware(['auth', 'team.set'])`.

#### 7. Link nawigacyjny

**Plik**: `resources/views/layouts/navigation.blade.php`

**Cel**: Umożliwić dostęp do `/team` z każdej strony appki (edycja drużyny w dowolnym momencie), nie tylko przez wymuszone przekierowanie.

**Kontrakt**: Dodać `<x-nav-link :href="route('team.edit')" :active="request()->routeIs('team.*')">{{ __('Drużyna') }}</x-nav-link>` obok istniejącego linku `Dashboard`, w obu miejscach pliku (wersja desktop `x-nav-link`, wersja mobilna `x-responsive-nav-link`).

#### 8. Testy feature

**Plik**: `tests/Feature/TeamTest.php` (nowy)

**Cel**: Pokryć cały przepływ end-to-end z `Http::fake()`, bez uderzania w prawdziwe Nominatim.

**Kontrakt**: Przypadki: niezalogowany użytkownik trafia na `/login` przy próbie wejścia na `/team`; zalogowany użytkownik bez drużyny, wchodząc na `/dashboard`, zostaje przekierowany na `/team`; `POST /team/search` z `Http::fake()` zwracającym 2 kandydatów renderuje widok z 2 opcjami; `POST /team/search` z pustą odpowiedzią Nominatim zwraca błąd walidacji `city` i nie tworzy `Team`; `POST /team/search` z `Http::fake()` symulującym błąd 500 zwraca ten sam błąd walidacji; `POST /team` z poprawnymi danymi kandydata tworzy `Team` i przekierowuje na `/dashboard`, które jest teraz dostępne; ponowny `POST /team` dla użytkownika z istniejącą drużyną aktualizuje ten sam wiersz (nie tworzy drugiego).

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Pełny zestaw testów przechodzi: `php artisan test`
- `./vendor/bin/pint --test` nie zgłasza błędów formatowania
- `php artisan route:list` pokazuje trasy `team.edit`, `team.search`, `team.store`

#### Weryfikacja ręczna:

- Świeżo zarejestrowane konto, próba wejścia na `/dashboard`, przekierowanie na `/team`
- Wpisanie realnej nazwy drużyny i polskiego miasta (np. "Legia Warszawa" / "Warszawa"), zapytanie do prawdziwego Nominatim (nie fake) zwraca listę kandydatów po polsku
- Wybór kandydata i zapis → przekierowanie na `/dashboard`, które jest teraz dostępne
- Ponowne wejście na `/team` pokazuje wcześniej zapisaną nazwę drużyny i miasto w formularzu
- Wpisanie nieistniejącej nazwy miasta (losowy ciąg znaków) pokazuje czytelny polski komunikat błędu, nic nie zapisuje
- Link "Drużyna" w nawigacji działa i podświetla się jako aktywny na `/team`

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że testy ręczne zakończyły się sukcesem.

---

## Strategia testowania

### Testy jednostkowe:

- `NominatimGeocoder::search()` — mapowanie odpowiedzi, pusta lista przy braku wyników, pusta lista przy błędzie/timeout (wszystko przez `Http::fake()`)

### Testy integracyjne:

- Pełny przepływ `/team` → `/team/search` → `/team` (POST) → `/dashboard`, w tym ścieżki błędów (brak wyników, błąd API) i ścieżkę edycji (drugi zapis nadpisuje pierwszy)
- Middleware `team.set` blokuje `/dashboard` bez drużyny i przepuszcza z drużyną

### Kroki testowania ręcznego:

1. Zarejestrować nowe konto testowe, potwierdzić przekierowanie `/dashboard` → `/team`
2. Wypełnić formularz realną nazwą drużyny i miastem, potwierdzić listę kandydatów z prawdziwego Nominatim
3. Wybrać kandydata, zapisać, potwierdzić przekierowanie na `/dashboard` i dostępność dashboardu
4. Wrócić na `/team`, sprawdzić że pola są wypełnione poprzednimi danymi, zmienić miasto i zapisać ponownie — potwierdzić że w bazie jest wciąż jeden wiersz `teams` dla tego użytkownika
5. Wpisać bzdurną nazwę miasta, potwierdzić polski komunikat błędu i brak zapisu

## Uwagi dotyczące wydajności

Pojedyncze synchroniczne wywołanie Nominatim przy rzadkiej akcji (ustawienie/zmiana drużyny) — NFR <1s p95 z PRD dotyczy akcji na meczach (S-03+), nie tego formularza; opóźnienie zewnętrznego API tutaj jest akceptowalne.

## Uwagi dotyczące migracji

Nowa tabela `teams`, brak istniejących danych do migrowania. Istniejące konto(a) z S-01 (w tym produkcyjne) będą miały `team === null` po wdrożeniu tej zmiany i zostaną przekierowane na `/team` przy najbliższym wejściu na `/dashboard` — to zamierzone zachowanie (wymuszony onboarding), nie regresja.

## Referencje

- Mapa drogowa: `context/foundation/roadmap.md` (S-02)
- PRD: `context/foundation/prd.md` (FR-002)
- Poprzedni element (wzorzec lokalizacji PL, struktura planu): `context/changes/user-registration-login/plan.md`

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Model danych i serwis geokodowania Nominatim

#### Automatyczne

- [x] 1.1 Migracja stosuje się czysto: php artisan migrate --force na świeżej bazie SQLite — 5296fe8
- [x] 1.2 Testy jednostkowe przechodzą: php artisan test tests/Unit/NominatimGeocoderTest.php — 5296fe8
- [x] 1.3 ./vendor/bin/pint --test nie zgłasza błędów formatowania — 5296fe8

#### Ręczne

- [x] 1.4 php artisan tinker: NominatimGeocoder->search('Warszawa') przeciw prawdziwemu Nominatim zwraca niepustą listę — 5296fe8

### Faza 2: Formularz drużyny, potwierdzenie geokodowania i wymuszone przekierowanie z dashboardu

#### Automatyczne

- [x] 2.1 Pełny zestaw testów przechodzi: php artisan test
- [x] 2.2 ./vendor/bin/pint --test nie zgłasza błędów formatowania
- [x] 2.3 php artisan route:list pokazuje trasy team.edit, team.search, team.store

#### Ręczne

- [x] 2.4 Świeżo zarejestrowane konto: /dashboard przekierowuje na /team
- [x] 2.5 Realna nazwa drużyny i polskie miasto: lista kandydatów z prawdziwego Nominatim
- [x] 2.6 Wybór kandydata i zapis: przekierowanie na /dashboard, dashboard dostępny
- [x] 2.7 Ponowne wejście na /team pokazuje wcześniej zapisane dane
- [x] 2.8 Nieistniejąca nazwa miasta: czytelny polski błąd, nic nie zapisane
- [x] 2.9 Link "Drużyna" w nawigacji działa i podświetla się jako aktywny
