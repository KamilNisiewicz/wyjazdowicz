# Dodanie meczu z automatycznym obliczeniem dystansu — Plan implementacji

## Przegląd

Dodajemy możliwość zapisania meczu, na którym użytkownik był obecny (przeciwnik, data, dom/wyjazd, wynik, miejscowość). Dla meczów wyjazdowych aplikacja automatycznie liczy dystans dom→miejscowość (linia prosta, wzór haversine) na podstawie lokalizacji "dom" ustawionej w S-02 (`teams.home_lat`/`home_lng`) i miejscowości meczu geokodowanej przez `NominatimGeocoder`. Dla meczów domowych miejscowość i dystans są przejmowane wprost z profilu drużyny — bez dodatkowego zapytania do Nominatim. Mecz pojawia się na nowej liście `/matches`.

To gwiazda przewodnia roadmapy (S-03): błędny dystans unieważnia całą wartość aplikacji (guardrail z PRD), więc formuła dystansu musi być zweryfikowana testem na znanych parach miast.

## Analiza stanu obecnego

Baza po S-01 (auth) i S-02 (drużyna + geokodowanie) zawiera:
- `teams` (1:1 z `users`): `user_id`, `name`, `home_city`, `home_lat` (`decimal(10,7)`), `home_lng` (`decimal(10,7)`) — `database/migrations/2026_07_19_123637_create_teams_table.php:14-22`, `app/Models/Team.php`.
- `App\Services\NominatimGeocoder::search(string $query): array` (`app/Services/NominatimGeocoder.php:14-46`) — bezstanowy, zwraca do 5 kandydatów `{display_name, lat, lon}` (zdeduplikowanych po `display_name`), pusta tablica przy błędzie/braku wyników. Nie cache'uje niczego — cache'owanie to obowiązek wywołującego (kolumny lat/lng na rekordzie).
- Wzorzec dwuetapowego formularza z potwierdzeniem geokodowania: `TeamController::search()`/`store()` (`app/Http/Controllers/TeamController.php:21-53`), `SearchCityRequest`/`StoreTeamRequest` (`app/Http/Requests/Team/`), widoki `resources/views/team/edit.blade.php` i `resources/views/team/candidates.blade.php`.
- `App\Http\Middleware\EnsureTeamIsSet` (alias `team.set`, `bootstrap/app.php:16-18`) — przekierowuje na `team.edit`, gdy `$user->team === null`. Obecnie tylko na `/dashboard`.
- Brak jakiegokolwiek kodu meczów (`Match`, `mecz`, `opponent`, `przeciwnik`) — potwierdzone brakiem trafień w `app/`, `database/`, `routes/`, `tests/`.
- Brak biblioteki geo/dystansu w `composer.json` — formuła haversine musi zostać napisana od zera.
- Brak `Carbon::setTestNow`/`travelTo` w testach — `now()`/`today()` w testach to realny zegar UTC (`config/app.php:68`), bez zamrożenia czasu.

## Pożądany stan końcowy

Zalogowany użytkownik z ustawioną drużyną otwiera `/matches`, klika "Dodaj mecz", wypełnia formularz (przeciwnik, data, dom/wyjazd, wynik, miejscowość — tylko dla wyjazdu). Dla wyjazdu: po znalezieniu miasta przez Nominatim potwierdza kandydata (jak w S-02); mecz zapisuje się z policzonym dystansem w km. Dla meczu domowego: mecz zapisuje się od razu, dystans jest pusty (nie dotyczy). Lista `/matches` pokazuje wszystkie mecze użytkownika (data, przeciwnik, dom/wyjazd, wynik, dystans) posortowane od najnowszego. Link "Mecze" w nawigacji obok "Drużyna".

Weryfikacja: `php artisan test` przechodzi w całości, w tym test jednostkowy formuły dystansu na znanej parze miast z tolerancją, oraz ręczny przepływ end-to-end z prawdziwym Nominatim.

### Kluczowe odkrycia:

- `Team` już ma `home_lat`/`home_lng` — nie trzeba nic dodatkowo geokodować dla strony "dom", tylko odczytać.
- `Match` to zarezerwowane słowo kluczowe w PHP 8+ (wyrażenie `match`) — model i tabela nie mogą się tak nazywać. Używamy `App\Models\GameMatch` / tabeli `game_matches`.
- Istniejący wzorzec `TeamController` (`app/Http/Controllers/TeamController.php:21-53`) jest bezpośrednim szablonem do powielenia dla ścieżki wyjazdowej (search→candidates→store), łącznie z walidacją `candidate` przez `Rule::in(array_keys($this->input('candidates', [])))`.

## Czego NIE robimy

- Edycja i usuwanie meczu (S-04, osobna zmiana).
- Panel statystyk zbiorczych: bilans W/D/L, %, passa, wskaźnik "pechowy kibic" (S-05). Ta zmiana tylko zapisuje dane źródłowe (wynik jako gole) i udostępnia surową listę — nie liczy żadnych zagregowanych statystyk.
- Podział dom/wyjazd w statystykach (S-06).
- Przebudowa `/dashboard` w pełny panel — dashboard dostaje tylko link do `/matches`, jego właściwa przebudowa to S-05.
- Baza drużyn/przeciwników — pole "przeciwnik" to zwykły tekst wolny, bez walidacji względem listy klubów (zgodnie z Non-Goals PRD: brak bazy stadionów/klubów).
- Re-geokodowanie miasta drużyny przy każdym meczu domowym — miejscowość i współrzędne meczu domowego są kopiowane z `teams.home_city`/`home_lat`/`home_lng` bez wywołania Nominatim.

## Podejście do implementacji

Trzy fazy, rosnąco: (1) model danych + izolowana logika liczenia dystansu z testem na znanej parze miast — to najbardziej ryzykowna część (guardrail PRD), więc idzie pierwsza i jest testowalna w izolacji bez UI; (2) kontroler + formularz + dwuetapowe potwierdzenie geokodowania dla wyjazdów, powielające sprawdzony wzorzec z `TeamController`; (3) lista `/matches` + link nawigacyjny + testy feature end-to-end.

## Krytyczne szczegóły implementacji

- **Asymetria tworzenia rekordu między dom a wyjazd**: dla meczu domowego rekord `GameMatch` powstaje bezpośrednio w akcji `search()` (bo nie ma nic do potwierdzenia — miejscowość i współrzędne są już znane z `Team`), więc żądanie nigdy nie trafia do `store()`. Dla wyjazdu `search()` tylko pokazuje kandydatów, a rekord powstaje dopiero w `store()` po wyborze kandydata. To odzwierciedla nazwę trasy `matches.search` (wzorem `team.search`), ale implementator musi wiedzieć, że dla `venue=home` ta sama akcja od razu zapisuje i przekierowuje — nie tylko "szuka".
- **`Team` jest wymagany dla obu gałęzi** (`home_lat`/`home_lng` do kopiowania przy meczu domowym i do liczenia dystansu przy wyjeździe) — trasy `/matches/*` muszą być objęte middleware `team.set`, inaczej `$request->user()->team` może być `null` i wywołanie `$team->home_lat` wywali błąd.

## Faza 1: Model danych i logika dystansu

### Przegląd

Migracja `game_matches`, model `GameMatch` z relacją do `User` i computed accessorem wyniku (W/D/L z goli), oraz izolowany serwis `DistanceCalculator` (haversine) z testem jednostkowym na znanej parze miast.

### Wymagane zmiany:

#### 1. Migracja tabeli meczów

**Plik**: `database/migrations/2026_07_23_120000_create_game_matches_table.php`

**Cel**: Utworzyć tabelę przechowującą mecze użytkownika, wzorowaną na konwencjach `teams` (kolumny lat/lng jako `decimal(10,7)`), ale bez unikalności `user_id` (wielu meczów na użytkownika).

**Kontrakt**: `Schema::create('game_matches', ...)` z kolumnami: `id`, `foreignId('user_id')->constrained()->cascadeOnDelete()` (bez `unique()`), `string('opponent')`, `date('played_on')`, `enum('venue', ['home', 'away'])`, `string('city')`, `decimal('lat', 10, 7)`, `decimal('lng', 10, 7)`, `unsignedSmallInteger('distance_km')->nullable()` (puste dla `venue=home`), `unsignedTinyInteger('goals_for')`, `unsignedTinyInteger('goals_against')`, `timestamps()`.

#### 2. Model `GameMatch`

**Plik**: `app/Models/GameMatch.php`

**Cel**: Reprezentacja meczu z relacją do `User` i wyliczanym atrybutem wyniku (bez przechowywania W/D/L jako osobnej kolumny — wyliczane z `goals_for`/`goals_against`, żeby uniknąć rozjazdu danych).

**Kontrakt**: `#[Fillable(['user_id', 'opponent', 'played_on', 'venue', 'city', 'lat', 'lng', 'distance_km', 'goals_for', 'goals_against'])]`, `casts()`: `played_on => 'date'`, `lat`/`lng => 'decimal:7'`, `distance_km`/`goals_for`/`goals_against => 'integer'`. Relacja `user(): BelongsTo`. Computed accessor `result(): Attribute` (wzorem Laravel `Attribute::make(get: ...)`) zwracający `'win'`/`'draw'`/`'loss'` przez porównanie `goals_for` z `goals_against` (użycie wyrażenia `match(true) { ... }` wewnątrz metody jest OK — zakaz dotyczy tylko nazwy klasy `Match`, nie wyrażenia `match`).

#### 3. Relacja na `User`

**Plik**: `app/Models/User.php`

**Cel**: Umożliwić `$user->gameMatches` / `$user->gameMatches()->create(...)`.

**Kontrakt**: Dodać metodę `gameMatches(): HasMany` zwracającą `$this->hasMany(GameMatch::class)`, analogicznie do istniejącej `team(): HasOne` (`app/Models/User.php:34-37`).

#### 4. Serwis liczenia dystansu

**Plik**: `app/Services/DistanceCalculator.php`

**Cel**: Policzyć dystans w km po linii prostej między dwiema parami współrzędnych (dom drużyny ↔ miejscowość meczu wyjazdowego), zaokrąglony do pełnych km. Wydzielony z `NominatimGeocoder`, żeby geokodowanie (I/O, sieć) i matematyka (czysta funkcja) były testowalne osobno.

**Kontrakt**: Publiczna metoda `kilometersBetween(float $lat1, float $lon1, float $lat2, float $lon2): int`, standardowy wzór haversine z promieniem Ziemi 6371 km, wynik zaokrąglony (`round()`) do liczby całkowitej.

```php
public function kilometersBetween(float $lat1, float $lon1, float $lat2, float $lon2): int
{
    $earthRadiusKm = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return (int) round($earthRadiusKm * $c);
}
```

#### 5. Factory dla testów

**Plik**: `database/factories/GameMatchFactory.php`

**Cel**: Umożliwić `GameMatch::factory()->for($user)->create()` w testach, wzorem `database/factories/TeamFactory.php`.

**Kontrakt**: `definition()` zwraca losowe, ale spójne dane: `user_id => User::factory()`, `opponent => fake()->city().' FC'`, `played_on => fake()->dateTimeBetween('-1 year', 'now')`, `venue => fake()->randomElement(['home', 'away'])`, `city`/`lat`/`lng` losowe współrzędne w Polsce (jak w `TeamFactory`, `fake()->latitude(49, 55)` / `fake()->longitude(14, 24)`), `distance_km => fake()->numberBetween(0, 500)`, `goals_for`/`goals_against => fake()->numberBetween(0, 5)`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Migracja stosuje się czysto: `php artisan migrate --force` na świeżej bazie SQLite
- Testy jednostkowe przechodzą: `php artisan test tests/Unit/DistanceCalculatorTest.php`
- `./vendor/bin/pint --test` nie zgłasza błędów formatowania

#### Weryfikacja ręczna:

- `php artisan tinker`: `(new App\Services\DistanceCalculator)->kilometersBetween(52.2297, 21.0122, 50.0647, 19.9450)` zwraca wartość w rozsądnym zakresie dla dystansu Warszawa–Kraków (ok. 250–260 km po linii prostej)

---

## Faza 2: Formularz dodawania meczu

### Przegląd

Kontroler `GameMatchController` z akcjami `create`/`search`/`store`, żądania walidacyjne, widoki formularza i potwierdzenia kandydata — powielające sprawdzony wzorzec z `TeamController`, rozszerzony o rozgałęzienie dom/wyjazd opisane w `## Krytyczne szczegóły implementacji`.

### Wymagane zmiany:

#### 1. Żądanie walidacji formularza meczu

**Plik**: `app/Http/Requests/GameMatch/SearchCityRequest.php`

**Cel**: Zwalidować dane z pierwszego kroku formularza (przeciwnik, data, dom/wyjazd, wynik, opcjonalnie miejscowość).

**Kontrakt**: `rules()`: `opponent => required|string|max:255`, `played_on => required|date|before_or_equal:today` (guardrail: mecz nie może być w przyszłości), `venue => required|in:home,away`, `goals_for => required|integer|min:0`, `goals_against => required|integer|min:0`, `city => required_if:venue,away|nullable|string|max:255`.

#### 2. Żądanie walidacji potwierdzenia kandydata

**Plik**: `app/Http/Requests/GameMatch/StoreRequest.php`

**Cel**: Zwalidować finalne zapisanie meczu wyjazdowego po wyborze kandydata z listy Nominatim — mirror `StoreTeamRequest`.

**Kontrakt**: `rules()`: `opponent => required|string|max:255`, `played_on => required|date|before_or_equal:today`, `goals_for => required|integer|min:0`, `goals_against => required|integer|min:0`, `candidate => required|integer|Rule::in(array_keys($this->input('candidates', [])))`, `candidates => required|array|min:1`, `candidates.*.display_name => required|string`, `candidates.*.lat => required|numeric|between:-90,90`, `candidates.*.lon => required|numeric|between:-180,180`.

#### 3. Kontroler meczów

**Plik**: `app/Http/Controllers/GameMatchController.php`

**Cel**: Cztery akcje: `create` (formularz), `search` (rozgałęzienie dom/wyjazd — patrz `## Krytyczne szczegóły implementacji`), `store` (finalne zapisanie meczu wyjazdowego po potwierdzeniu kandydata), `index` (Faza 3).

**Kontrakt**:
- `create(): View` — zwraca `matches.create` (bez danych, formularz pusty).
- `search(SearchCityRequest $request, NominatimGeocoder $geocoder): View|RedirectResponse` — gdy `venue === 'home'`: tworzy `GameMatch` bezpośrednio przez `$request->user()->gameMatches()->create([...])` z `city`/`lat`/`lng` skopiowanymi z `$request->user()->team` i `distance_km = null`, przekierowuje na `matches.index` z `status=match-created`. Gdy `venue === 'away'`: woła `$geocoder->search($validated['city'])`; pusta lista → `back()->withInput()->withErrors(['city' => ...])` (identyczny komunikat jak w `TeamController::search()`); niepusta → `view('matches.candidates', [...])` z przekazaniem `opponent`/`played_on`/`goals_for`/`goals_against`/`candidates` (ukryte pola do replayu w formularzu, wzorem `team.candidates`).
- `store(StoreRequest $request, DistanceCalculator $calculator): RedirectResponse` — wybiera `$validated['candidates'][$validated['candidate']]`, liczy `distance_km` przez `$calculator->kilometersBetween((float) $team->home_lat, (float) $team->home_lng, $candidate['lat'], $candidate['lon'])`, tworzy `GameMatch` z `venue='away'`, przekierowuje na `matches.index` z `status=match-created`.

#### 4. Trasy

**Plik**: `routes/web.php`

**Cel**: Zarejestrować trasy meczów pod middleware `['auth', 'team.set']` (nie samo `auth` jak `/team` — mecz zawsze wymaga istniejącej drużyny, w przeciwieństwie do samego formularza drużyny).

**Kontrakt**: Nowa grupa obok istniejącej `Route::middleware('auth')->group(...)`:
```php
Route::middleware(['auth', 'team.set'])->group(function () {
    Route::get('/matches', [GameMatchController::class, 'index'])->name('matches.index');
    Route::get('/matches/create', [GameMatchController::class, 'create'])->name('matches.create');
    Route::post('/matches/search', [GameMatchController::class, 'search'])->name('matches.search');
    Route::post('/matches', [GameMatchController::class, 'store'])->name('matches.store');
});
```
(Akcja `index` istnieje formalnie w tej fazie w routingu, ale jej implementacja i widok to Faza 3 — do tego czasu można zwrócić pusty widok placeholder lub od razu zaimplementować minimalnie, patrz Faza 3.)

#### 5. Widok formularza

**Plik**: `resources/views/matches/create.blade.php`

**Cel**: Formularz jednego kroku: przeciwnik, data (input `type=date`, `max` = dzisiejsza data), dom/wyjazd (dwa radio), wynik (dwa pola liczbowe: gole drużyny użytkownika / gole przeciwnika), miejscowość (pole tekstowe, etykieta wyjaśnia "wymagane tylko dla wyjazdu"). POST na `matches.search`. Layout i stylistyka `x-app-layout` + komponenty `x-input-label`/`x-text-input`/`x-input-error`/`x-primary-button`, wzorem `resources/views/team/edit.blade.php`.

#### 6. Widok potwierdzenia kandydata

**Plik**: `resources/views/matches/candidates.blade.php`

**Cel**: Lista kandydatów miast do wyboru (radio + ukryte pola `display_name`/`lat`/`lon` na kandydata), plus ukryte pola replayujące `opponent`/`played_on`/`goals_for`/`goals_against` z pierwszego kroku. POST na `matches.store`. Bezpośredni mirror `resources/views/team/candidates.blade.php`.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Pełny zestaw testów przechodzi: `php artisan test`
- `./vendor/bin/pint --test` nie zgłasza błędów formatowania
- `php artisan route:list` pokazuje trasy `matches.index`, `matches.create`, `matches.search`, `matches.store`

#### Weryfikacja ręczna:

- Zalogowane konto z ustawioną drużyną: dodanie meczu domowego zapisuje się od razu, bez pytania o miejscowość, dystans pusty
- Dodanie meczu wyjazdowego z realną polską miejscowością: lista kandydatów z prawdziwego Nominatim, po wyborze mecz zapisuje się z policzonym dystansem > 0
- Nieistniejąca nazwa miejscowości przy wyjeździe: czytelny polski błąd, nic nie zapisane
- Próba wejścia na `/matches/create` bez ustawionej drużyny: przekierowanie na `/team` (middleware `team.set`)
- Data w przyszłości: błąd walidacji, nic nie zapisane

---

## Faza 3: Lista meczów i integracja z nawigacją

### Przegląd

Widok listy `/matches`, link w nawigacji ("Mecze" obok "Drużyna"), link z dashboardu, testy feature end-to-end.

### Wymagane zmiany:

#### 1. Akcja listy w kontrolerze

**Plik**: `app/Http/Controllers/GameMatchController.php`

**Cel**: Dokończyć akcję `index()` zaczętą w Fazie 2.

**Kontrakt**: `index(Request $request): View` zwraca `matches.index` z `matches => $request->user()->gameMatches()->latest('played_on')->get()`.

#### 2. Widok listy

**Plik**: `resources/views/matches/index.blade.php`

**Cel**: Tabela/lista meczów użytkownika: data, przeciwnik, dom/wyjazd (etykieta polska), wynik (`{{ $match->goals_for }}:{{ $match->goals_against }}`, ewentualnie z etykietą W/D/L z `$match->result`), dystans (`{{ $match->distance_km }} km` dla wyjazdu, `—` dla meczu domowego). Link "Dodaj mecz" do `matches.create`. Pusty stan (brak meczów) z zachęcającym komunikatem po polsku.

#### 3. Link w nawigacji

**Plik**: `resources/views/layouts/navigation.blade.php`

**Cel**: Dodać link "Mecze" obok istniejącego "Drużyna", w obu wariantach (desktop i mobile).

**Kontrakt**: Analogicznie do istniejących wpisów (`resources/views/layouts/navigation.blade.php:18-20` desktop, `:76-78` mobile) — nowy `<x-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">{{ __('Mecze') }}</x-nav-link>` (i odpowiednik `x-responsive-nav-link`), umieszczony zaraz po linku "Drużyna".

#### 4. Link z dashboardu

**Plik**: `resources/views/dashboard.blade.php`

**Cel**: Minimalny link do `/matches` z istniejącego placeholdera dashboardu — bez przebudowy dashboardu (to S-05).

**Kontrakt**: Dodać w istniejącym bloku treści link/przycisk `<a href="{{ route('matches.index') }}">{{ __('Zobacz mecze') }}</a>` obok obecnego tekstu "You're logged in!".

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Pełny zestaw testów przechodzi: `php artisan test`
- `./vendor/bin/pint --test` nie zgłasza błędów formatowania

#### Weryfikacja ręczna:

- `/matches` pokazuje wcześniej dodane mecze, posortowane od najnowszego
- Link "Mecze" w nawigacji działa i podświetla się jako aktywny na `/matches*`
- Link z dashboardu prowadzi na `/matches`
- Świeże konto bez meczów: pusty stan czytelny, nie błąd

---

## Strategia testowania

### Testy jednostkowe:

- `DistanceCalculator::kilometersBetween()` na znanej parze miast (Warszawa ↔ Kraków, współrzędne z realnego Nominatim: `52.2297, 21.0122` / `50.0647, 19.9450`) z asercją w rozsądnym przedziale tolerancji (ok. 240–265 km) — realizuje ryzyko z roadmapy ("wymaga testu na znanych parach miast, zanim uzna się to za zrobione")
- `DistanceCalculator::kilometersBetween()` dla identycznych współrzędnych zwraca `0`
- `GameMatch::result` accessor: gole 2:1 → `'win'`, 1:2 → `'loss'`, 1:1 → `'draw'`

### Testy integracyjne:

- Gość przekierowany na `/login` przy próbie wejścia na `/matches*`
- `/matches*` przekierowuje na `/team`, gdy drużyna nie jest ustawiona
- Mecz domowy: zapisuje się bez wywołania Nominatim (`Http::fake()` bez zdefiniowanej trasy nominatim — test failuje, jeśli kontroler jednak strzeli do API), `distance_km` jest `null`
- Mecz wyjazdowy: pełny przepływ `search` (kandydaci z `Http::fake`) → `store` (wybór kandydata) → rekord w bazie z policzonym dystansem
- Błąd geokodowania (pusta odpowiedź Nominatim / 500) przy wyjeździe: błąd walidacji na `city`, `assertDatabaseCount('game_matches', 0)`
- Odrzucenie `candidate` spoza zakresu przesłanych `candidates` (mirror testu z `TeamTest`)
- Data w przyszłości: `assertSessionHasErrors('played_on')`

### Kroki testowania ręcznego:

1. Zalogować się kontem z ustawioną drużyną, dodać mecz domowy — sprawdzić natychmiastowy zapis bez pytania o miasto
2. Dodać mecz wyjazdowy z prawdziwą polską miejscowością — potwierdzić kandydata, sprawdzić sensowny dystans w km
3. Spróbować wyjazdu z nieistniejącą nazwą miejscowości — sprawdzić czytelny błąd po polsku
4. Sprawdzić listę `/matches` i link w nawigacji

## Uwagi dotyczące wydajności

Zgodnie z ograniczeniem Nominatim (~1 zapytanie/s, nagłówek `User-Agent` wymagany — już obsłużone przez `NominatimGeocoder`), geokodowanie wywoływane jest wyłącznie przy zapisie meczu wyjazdowego (raz na mecz), nigdy przy wyświetlaniu listy. Mecze domowe nie generują żadnego zapytania do Nominatim.

## Uwagi dotyczące migracji

Nowa tabela, brak istniejących danych do migracji. `foreignId('user_id')->constrained()->cascadeOnDelete()` — usunięcie użytkownika kasuje jego mecze (spójne z `teams`).

## Referencje

- Wzorzec dwuetapowego formularza z geokodowaniem: `app/Http/Controllers/TeamController.php:21-53`
- Model współrzędnych "dom": `app/Models/Team.php`, `database/migrations/2026_07_19_123637_create_teams_table.php`
- Serwis geokodowania (reużywany bez zmian): `app/Services/NominatimGeocoder.php`
- Middleware wymuszający drużynę: `app/Http/Middleware/EnsureTeamIsSet.php`
- PRD: `context/foundation/prd.md` (FR-003, FR-006, FR-007, US-01)
- Roadmapa: `context/foundation/roadmap.md` (S-03, ryzyko dystansu i limitu Nominatim)

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Model danych i logika dystansu

#### Automatyczne

- [x] 1.1 Migracja stosuje się czysto: php artisan migrate --force na świeżej bazie SQLite
- [x] 1.2 Testy jednostkowe przechodzą: php artisan test tests/Unit/DistanceCalculatorTest.php
- [x] 1.3 ./vendor/bin/pint --test nie zgłasza błędów formatowania

#### Ręczne

- [x] 1.4 php artisan tinker: DistanceCalculator->kilometersBetween dla Warszawa-Kraków zwraca ok. 250-260 km

### Faza 2: Formularz dodawania meczu

#### Automatyczne

- [ ] 2.1 Pełny zestaw testów przechodzi: php artisan test
- [ ] 2.2 ./vendor/bin/pint --test nie zgłasza błędów formatowania
- [ ] 2.3 php artisan route:list pokazuje trasy matches.index, matches.create, matches.search, matches.store

#### Ręczne

- [ ] 2.4 Mecz domowy zapisuje się od razu, bez pytania o miejscowość, dystans pusty
- [ ] 2.5 Mecz wyjazdowy z realną miejscowością: kandydaci z prawdziwego Nominatim, zapis z dystansem > 0
- [ ] 2.6 Nieistniejąca nazwa miejscowości przy wyjeździe: czytelny błąd, nic nie zapisane
- [ ] 2.7 Wejście na /matches/create bez ustawionej drużyny przekierowuje na /team
- [ ] 2.8 Data w przyszłości: błąd walidacji, nic nie zapisane

### Faza 3: Lista meczów i integracja z nawigacją

#### Automatyczne

- [ ] 3.1 Pełny zestaw testów przechodzi: php artisan test
- [ ] 3.2 ./vendor/bin/pint --test nie zgłasza błędów formatowania

#### Ręczne

- [ ] 3.3 /matches pokazuje wcześniej dodane mecze, posortowane od najnowszego
- [ ] 3.4 Link "Mecze" w nawigacji działa i podświetla się jako aktywny
- [ ] 3.5 Link z dashboardu prowadzi na /matches
- [ ] 3.6 Świeże konto bez meczów: czytelny pusty stan
