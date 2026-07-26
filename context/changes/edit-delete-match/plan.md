# Edycja i usuwanie meczu — Plan implementacji

## Przegląd

Dodajemy dwie brakujące operacje CRUD na `GameMatch` (FR-004, FR-005): edycję zapisanego meczu (przeciwnik, data, wynik) oraz jego usunięcie, z ochroną przed dostępem do cudzych rekordów i UI spójnym z resztą aplikacji.

## Analiza stanu obecnego

`GameMatchController` (S-03) ma dziś tylko `index`, `create`, `search`, `store`. Trasy `matches.*` żyją w `routes/web.php:26-31` pod middleware `['auth', 'team.set']`. Nie istnieje żadna `Policy` w projekcie — `index()` scopuje dziś dane niejawnie przez `$request->user()->gameMatches()`. Model `GameMatch` (`app/Models/GameMatch.php`) ma pola `opponent`, `played_on`, `venue`, `city`, `lat`, `lng`, `distance_km`, `goals_for`, `goals_against` — wszystkie ustalane raz, w momencie tworzenia meczu, przez dwuetapowy flow `search`→`candidates`→`store` (dla wyjazdów, przez `NominatimGeocoder`).

Breeze zostawił gotowy wzorzec potwierdzenia akcji niszczącej w `resources/views/profile/partials/delete-user-form.blade.php`: przycisk `x-danger-button` z `x-on:click.prevent="$dispatch('open-modal', '<nazwa>')"` otwierający `x-modal` zawierający formularz `DELETE`.

### Kluczowe odkrycia:

- `app/Http/Controllers/GameMatchController.php:15-19` — `index()` już scopuje przez `$request->user()->gameMatches()->latest('played_on')->get()`; ten sam wzorzec scopingu ma zabezpieczyć `edit`/`update`/`destroy`.
- `routes/web.php:16-24` — `profile.*` routes używają już `PATCH`/`DELETE` na tym samym URI co `GET`/tworzenie (`/profile`), analogiczny wzorzec RESTful do powielenia dla `/matches/{match}`.
- `database/migrations/2026_07_23_175515_create_game_matches_table.php:23-25` — `distance_km` to `unsignedSmallInteger`, `goals_for`/`goals_against` to `unsignedTinyInteger` (max 255) — walidacja `UpdateRequest` musi powtórzyć te same granice co `StoreRequest`.
- `tests/Feature/GameMatchTest.php` — jeden plik testowy na wszystkie akcje kontrolera (nie osobny plik per akcja); nowe testy edit/update/destroy dopisujemy do tego samego pliku.

## Pożądany stan końcowy

Zalogowany użytkownik widzi na liście meczów (`/matches`) przy każdym wierszu linki „Edytuj” i „Usuń”. „Edytuj” prowadzi do formularza z polami przeciwnik/data/wynik wypełnionymi bieżącymi wartościami; zapis aktualizuje rekord i wraca na listę z komunikatem potwierdzającym. „Usuń” otwiera modal z prośbą o potwierdzenie; potwierdzenie trwale kasuje mecz i wraca na listę z komunikatem potwierdzającym. Próba edycji/usunięcia cudzego meczu (inny `user_id`) zwraca 404. Weryfikacja: `php artisan test --filter=GameMatchTest` zielone, ręczny test w przeglądarce (edycja wyniku, usunięcie meczu, próba wejścia na URL cudzego meczu).

## Czego NIE robimy

- Nie pozwalamy zmieniać `venue` (dom/wyjazd) ani `city`/`lat`/`lng`/`distance_km` przez edycję — to ustalone raz przy tworzeniu; poprawka błędnej miejscowości/venue to usunięcie meczu i dodanie go od nowa.
- Nie wprowadzamy soft-delete (`deleted_at`) — usunięcie jest trwałe, zgodnie z resztą projektu (brak soft-delete gdziekolwiek indziej).
- Nie tworzymy `GameMatchPolicy` — autoryzacja to query scoping przez `$request->user()->gameMatches()`, tak jak już robi `index()`.
- Nie dotykamy statystyk/bilansu (S-05 jeszcze nie istnieje) — nie ma żadnego cache'a do przeliczenia; przyszły panel statystyk będzie liczył się na żywo z zapisanych meczów.
- Nie tworzymy strony szczegółów meczu (`matches.show`) — akcje edycji/usuwania żyją bezpośrednio w wierszu listy.

## Podejście do implementacji

Powielamy istniejące konwencje S-03/Breeze zamiast wprowadzać nowe wzorce: RESTful trasy analogiczne do `profile.*` (`GET .../edit`, `PATCH`, `DELETE` na tym samym URI zasobu), `FormRequest` analogiczny do `StoreRequest`, i modal potwierdzenia usunięcia skopiowany ze struktury `delete-user-form.blade.php`. Scoping własności przez relację `$request->user()->gameMatches()` (zamiast implicit route-model-binding na gołym `GameMatch`) gwarantuje, że próba dostępu do cudzego meczu kończy się `ModelNotFoundException` → 404, zanim jakikolwiek cudzy rekord trafi do pamięci.

## Faza 1: Backend — trasy, kontroler, walidacja, testy

### Przegląd

Dodaje `edit`/`update`/`destroy` do `GameMatchController`, nowy `UpdateRequest`, trasy RESTful, i pokrycie testowe (w tym IDOR) — bez żadnych zmian w widokach.

### Wymagane zmiany:

#### 1. Trasy meczów

**Plik**: `routes/web.php`

**Cel**: Dodać trzy trasy RESTful do istniejącej grupy `['auth', 'team.set']` (linie 26-31), zaraz po `matches.store`.

**Kontrakt**: `GET /matches/{match}/edit` → `matches.edit`, `PATCH /matches/{match}` → `matches.update`, `DELETE /matches/{match}` → `matches.destroy`. Wzorzec identyczny do `profile.edit`/`profile.update`/`profile.destroy` (linie 17-19) — różne czasowniki HTTP na jednym URI zasobu.

#### 2. Walidacja edycji

**Plik**: `app/Http/Requests/GameMatch/UpdateRequest.php` (nowy)

**Cel**: Walidować pola edytowalne (przeciwnik, data, wynik), nie dopuszczać zmiany venue/city/dystansu.

**Kontrakt**: `authorize(): true`; `rules()` identyczne z częścią `StoreRequest` (`app/Http/Requests/GameMatch/StoreRequest.php:20-24`): `opponent` (`required|string|max:255`), `played_on` (`required|date|before_or_equal:today`), `goals_for`/`goals_against` (`required|integer|min:0|max:255`). Brak pól `venue`/`city`/`candidate*` — formularz edycji ich nie wysyła.

#### 3. Akcje kontrolera

**Plik**: `app/Http/Controllers/GameMatchController.php`

**Cel**: Dodać `edit(Request $request, int $match): View`, `update(UpdateRequest $request, int $match): RedirectResponse`, `destroy(Request $request, int $match): RedirectResponse`.

**Kontrakt**: Każda metoda zaczyna się od `$gameMatch = $request->user()->gameMatches()->findOrFail($match);` — identyczny wzorzec scopingu co `index()` (linia 18). `edit` zwraca `view('matches.edit', ['match' => $gameMatch])`. `update` woła `$gameMatch->update($request->validated())` i przekierowuje na `matches.index` z `session('status', 'match-updated')`. `destroy` woła `$gameMatch->delete()` i przekierowuje na `matches.index` z `session('status', 'match-deleted')` — te same klucze/konwencja co istniejące `match-created` (linia 45/88).

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- `php artisan route:list --name=matches` pokazuje nowe trasy `matches.edit`, `matches.update`, `matches.destroy`
- Nowe testy w `tests/Feature/GameMatchTest.php` przechodzą: `php artisan test --filter=GameMatchTest`
  - edycja własnego meczu aktualizuje pola i przekierowuje na listę
  - edycja z nieprawidłowymi danymi (np. `goals_for` ujemne, `played_on` w przyszłości) zwraca błędy walidacji, rekord niezmieniony
  - usunięcie własnego meczu usuwa rekord z bazy i przekierowuje na listę
  - próba `GET /matches/{cudzy-id}/edit`, `PATCH /matches/{cudzy-id}`, `DELETE /matches/{cudzy-id}` przez innego zalogowanego użytkownika zwraca 404, rekord ofiary niezmieniony/nieusunięty
  - gość (niezalogowany) na tych trasach jest przekierowany do logowania
- `vendor/bin/pint --test` (jeśli projekt używa Pint — sprawdzić istniejący `composer.json`) lub istniejący linter przechodzi bez zmian stylu

#### Weryfikacja ręczna:

- Brak — ta faza nie dotyka UI; weryfikacja ręczna następuje w Fazie 2 razem z widokami

---

## Faza 2: UI — formularz edycji i akcje na liście

### Przegląd

Dodaje widok edycji i akcje „Edytuj”/„Usuń” w wierszach listy meczów, spinając je z trasami/kontrolerem z Fazy 1.

### Wymagane zmiany:

#### 1. Formularz edycji

**Plik**: `resources/views/matches/edit.blade.php` (nowy)

**Cel**: Formularz analogiczny do `resources/views/matches/create.blade.php`, ale tylko z polami przeciwnik/data/wynik (bez venue/city), wypełniony wartościami z `$match`, wysyłany jako `PATCH` na `matches.update`.

**Kontrakt**: Pola `opponent`, `played_on`, `goals_for`, `goals_against` — te same komponenty (`x-input-label`, `x-text-input`, `x-input-error`) i te same reguły `:value="old('pole', $match->pole)"` (fallback na stan zapisany, nie na `create`'owe puste `old()`). Nad polami krótka informacyjna linia pokazująca nieedytowalne dom/wyjazd + miejscowość (np. `{{ $match->venue === 'home' ? __('Dom') : __('Wyjazd') }} · {{ $match->city }}`), żeby użytkownik widział kontekst bez możliwości edycji. Formularz: `<form method="post" action="{{ route('matches.update', $match) }}">` + `@csrf` + `@method('patch')`.

#### 2. Akcje na liście meczów

**Plik**: `resources/views/matches/index.blade.php`

**Cel**: Dodać kolumnę „Akcje” do tabeli (nagłówek + komórka per wiersz) z linkiem „Edytuj” do `matches.edit` i przyciskiem „Usuń” otwierającym modal potwierdzenia, wzorem `resources/views/profile/partials/delete-user-form.blade.php:12-54`.

**Kontrakt**: Nagłówek `<th>{{ __('Akcje') }}</th>` po istniejącej kolumnie „Dystans” (linia 34); komórka per wiersz z linkiem `matches.edit` + `x-danger-button` (`x-on:click.prevent="$dispatch('open-modal', 'confirm-match-deletion-{{ $match->id }}')"`) + `<x-modal :name="'confirm-match-deletion-'.$match->id">` zawierający `<form method="post" action="{{ route('matches.destroy', $match) }}">` z `@csrf` `@method('delete')`. Nazwa modala musi być unikalna per wiersz (`{{ $match->id }}` w nazwie), inaczej wszystkie modały na stronie współdzielą stan otwarcia. Dodać też obsługę `session('status') === 'match-updated'` / `'match-deleted'` obok istniejącego bloku `match-created` (linie 10-14) dla komunikatów potwierdzających.

### Kryteria sukcesu:

#### Weryfikacja automatyczna:

- Nowe testy feature w `tests/Feature/GameMatchTest.php` przechodzą: `matches.edit` renderuje formularz z wypełnionymi wartościami (`assertSee` na wartość przeciwnika/daty), `matches.index` zawiera linki `matches.edit`/formularze `matches.destroy` per mecz
- `php artisan test --filter=GameMatchTest` w całości zielone

#### Weryfikacja ręczna:

- W przeglądarce (`php artisan serve`): otworzyć `/matches`, kliknąć „Edytuj” przy meczu, zmienić wynik, zapisać — lista pokazuje zaktualizowany wynik i komunikat potwierdzający
- Kliknąć „Usuń” przy meczu — modal się otwiera, „Anuluj” zamyka bez usuwania, potwierdzenie usuwa mecz i pokazuje komunikat
- Spróbować wejść na `/matches/{id}/edit` z ID meczu należącego do innego konta (drugie konto testowe) — 404
- Sprawdzić responsywność nowej kolumny „Akcje” na wąskim viewporcie (telefon) — zgodnie z NFR „w pełni używalna z przeglądarki mobilnej”

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj, aby uzyskać ręczne potwierdzenie od człowieka, że testy ręczne zakończyły się sukcesem.

---

## Strategia testowania

### Testy jednostkowe:

- Brak nowej logiki domenowej wymagającej testów jednostkowych poza istniejącym `Tests/Unit/GameMatchTest.php` (accessor `result` — niezmieniony w tym planie)

### Testy integracyjne:

- Pełny cykl: utworzenie meczu → edycja → weryfikacja zmiany na liście
- Pełny cykl: utworzenie meczu → usunięcie → weryfikacja zniknięcia z listy i `assertDatabaseMissing`
- IDOR: drugi użytkownik nie może odczytać/edytować/usunąć formularza ani rekordu pierwszego użytkownika (404 na wszystkich trzech trasach)
- Walidacja: edycja z `played_on` w przyszłości / ujemnym wynikiem odrzucona, rekord niezmieniony

### Kroki testowania ręcznego:

1. Zalogować się, wejść na `/matches`, sprawdzić że każdy wiersz ma „Edytuj” i „Usuń”
2. Edytować wynik istniejącego meczu, zapisać, sprawdzić że lista pokazuje nową wartość
3. Spróbować zapisać edycję z datą w przyszłości — oczekiwany błąd walidacji, brak zapisu
4. Usunąć mecz przez modal, potwierdzić zniknięcie z listy
5. Otworzyć modal usuwania i kliknąć „Anuluj” — mecz ma pozostać na liście
6. Zalogować się na drugie konto testowe, spróbować wejść na URL edycji meczu pierwszego konta — 404

## Uwagi dotyczące wydajności

Brak — operacje na pojedynczych rekordach, bez nowych zapytań N+1 (kontroler nadal robi jedno `findOrFail` per żądanie).

## Uwagi dotyczące migracji

Brak nowej migracji — żadna kolumna się nie zmienia, edycja dotyczy tylko istniejących pól `opponent`/`played_on`/`goals_for`/`goals_against`.

## Referencje

- Poprzedni plan (wzorzec do naśladowania): `context/changes/add-match-with-distance/plan.md`
- Trasy destrukcyjne z tym samym wzorcem RESTful: `routes/web.php:17-19` (`profile.*`)
- Wzorzec modala potwierdzenia: `resources/views/profile/partials/delete-user-form.blade.php`

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków. Zobacz `references/progress-format.md`.

### Faza 1: Backend — trasy, kontroler, walidacja, testy

#### Automatyczne

- [x] 1.1 `php artisan route:list --name=matches` pokazuje nowe trasy
- [x] 1.2 Testy feature edit/update/destroy + IDOR + walidacja przechodzą
- [x] 1.3 Linter/formatter przechodzi bez zmian stylu

### Faza 2: UI — formularz edycji i akcje na liście

#### Automatyczne

- [ ] 2.1 Testy feature dla widoku edycji i akcji na liście przechodzą

#### Ręczne

- [ ] 2.2 Edycja meczu w przeglądarce działa i pokazuje komunikat potwierdzający
- [ ] 2.3 Usuwanie meczu przez modal (potwierdzenie i anulowanie) działa w przeglądarce
- [ ] 2.4 Próba edycji/usunięcia cudzego meczu przez URL zwraca 404
- [ ] 2.5 Kolumna „Akcje” jest używalna na wąskim viewporcie (telefon)
