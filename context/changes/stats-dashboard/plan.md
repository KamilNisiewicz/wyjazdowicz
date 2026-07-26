# Panel statystyk (S-05) — Plan implementacji

## Przegląd

Dodajemy stronę `/stats` pokazującą zbiorcze statystyki użytkownika liczone na
podstawie już istniejących meczów (`GameMatch`): bilans W/D/L, % zwycięstw,
aktualną passę, wskaźnik "pechowy kibic" (FR-010) i łączny przejechany
dystans. To czysto odczytowy widok — żadna nowa tabela, kolumna ani zapis nie
powstaje; wszystko liczone jest na żywo z danych zapisanych już przez S-03/S-04.

## Analiza stanu obecnego

- `GameMatch` (`app/Models/GameMatch.php:35-44`) ma już accessor `result`
  zwracający `'win'`/`'draw'`/`'loss'` na podstawie `goals_for`/`goals_against`
  — to gotowy budulec do liczenia bilansu, nie trzeba go reimplementować.
- Dystans jest już policzony i zapisany per-mecz w kolumnie `distance_km`
  (nullable, `null` dla meczów domowych, liczba dla wyjazdowych) —
  `database/migrations/2026_07_23_175515_create_game_matches_table.php:14-26`.
  Łączny dystans to prosta suma tej kolumny, bez ponownego liczenia haversine.
  Brak też osobnej kolumny `is_home` — dyskryminatorem jest
  `venue` (`enum('home','away')`).
- `GameMatchController::index()` (`app/Http/Controllers/GameMatchController.php:16-21`)
  pokazuje ustalony wzorzec dostępu do danych użytkownika:
  `$request->user()->gameMatches()->...->get()` — bez surowego
  `GameMatch::query()`. Nowy kontroler statystyk ma iść tym samym wzorcem.
- `resources/views/dashboard.blade.php` jest niemal pustym scaffoldem Breeze —
  jedyna zawartość to tekst "Jesteś zalogowany!" i link do `matches.index`.
  Nic statystykowego jeszcze tam nie ma.
- W `resources/views/components/` nie ma żadnego komponentu kafelka
  statystyk — trzeba go zbudować od zera (nie reużywamy istniejącego).
- `routes/web.php` grupuje trasy meczów pod middleware `['auth', 'team.set']`
  (linie 26-34) — `team.set` (`app/Http/Middleware/EnsureTeamIsSet.php`)
  wymusza istnienie `Team` przed dostępem, co ma sens też dla statystyk (bez
  drużyny nie ma "domu", więc nie ma dystansu do policzenia — choć bilans
  teoretycznie mógłby istnieć bez drużyny, spójność z resztą appki wygrywa).
- Baza `game_matches` jest per-user przez `foreignId('user_id')` — izolacja
  między kontami to kwestia zapytania (`$request->user()->gameMatches()`), nie
  osobnej `Policy` — dokładnie jak w S-03/S-04.

### Kluczowe odkrycia:

- `app/Models/GameMatch.php:35-44` — accessor `result` (win/draw/loss), gotowy do reużycia.
- `database/migrations/2026_07_23_175515_create_game_matches_table.php:14-26` — `distance_km` nullable, `venue` enum('home','away'), brak osobnej kolumny is_home.
- `app/Http/Controllers/GameMatchController.php:16-21` — wzorzec `$request->user()->gameMatches()->get()` do naśladowania.
- `resources/views/matches/index.blade.php:24` — jedyny istniejący "card" idiom w projekcie: `class="p-4 sm:p-8 bg-white shadow sm:rounded-lg"`.
- `routes/web.php:26-34` — grupa tras meczów z middleware `['auth', 'team.set']`, wzorzec dla nowej trasy `/stats`.
- `database/factories/GameMatchFactory.php:19-33` — losowy `distance_km` niezależnie od `venue`; testy dla meczów domowych muszą jawnie nadpisać `distance_km => null`.

## Pożądany stan końcowy

Zalogowany użytkownik z ustawioną drużyną i co najmniej jednym meczem widzi
pod `/stats` (i linkiem w nawigacji oraz na dashboardzie): wykres słupkowy
W/D/L z liczbami, % zwycięstw, aktualną passę, łączny przejechany dystans, a
w razie spełnienia reguły FR-010 — kafelek "Pechowy kibic". Bez żadnego
meczu widzi komunikat zachęcający do dodania pierwszego meczu zamiast
mylących zer. Statystyki są zawsze aktualne — nic nie jest cache'owane, więc
edycja/usunięcie meczu (S-04) automatycznie odświeża panel przy następnym
wejściu, bez żadnej logiki inwalidacji (to bezpośrednio rozwiązuje ryzyko
opisane w roadmapie dla S-05: "bilans i wskaźnik pecha muszą pozostać
spójne po edycji/usunięciu meczu").

## Czego NIE robimy

- Brak podziału dom/wyjazd w statystykach — to S-06 (`home-away-stats-split`),
  osobny, kolejny slice.
- Brak cache'owania/przechowywania wyliczonych statystyk w bazie — liczone
  na żywo przy każdym wejściu na `/stats` (wolumen danych jednego
  użytkownika jest znikomy, więc wydajność nie jest problemem).
- Brak nowych kolumn/migracji — wszystkie dane potrzebne do statystyk już
  istnieją w `game_matches`.
- Brak biblioteki JS do wykresów — wykres W/D/L to czyste HTML/CSS (flexbox +
  Tailwind), zero nowych zależności frontendowych.
- Brak zmian w formularzu dodawania/edycji meczu — to czysto odczytowy widok.

## Podejście do implementacji

Nowa logika liczenia statystyk trafia do dedykowanego serwisu
`app/Services/StatsCalculator.php` — dokładnie ten sam wzorzec co
`App\Services\DistanceCalculator` z S-03 (mała, bezstanowa klasa z jedną
metodą wejściową, łatwa do przetestowania w izolacji i do reużycia przez
S-06). `StatsController::index()` pobiera mecze użytkownika (ten sam wzorzec
dostępu co `GameMatchController`), przekazuje kolekcję do
`StatsCalculator`, a wynik do widoku. Widok renderuje kafelki statystyk +
wykres W/D/L albo pusty stan, w zależności od tego, czy użytkownik ma
jakiekolwiek mecze.

## Krytyczne szczegóły implementacji

- **Kontrakt sortowania między kontrolerem a serwisem**: "aktualna passa"
  wymaga policzenia serii od najnowszego meczu wstecz. `StatsCalculator`
  zakłada, że przekazana kolekcja jest już posortowana od najnowszego do
  najstarszego meczu — to kontroler musi pobrać mecze przez
  `->orderByDesc('played_on')->orderByDesc('id')->get()` (drugi klucz
  sortowania rozstrzyga remisy dat — kilka meczów tego samego dnia — bo
  `orderByDesc('played_on')` samo w sobie nie gwarantuje deterministycznej
  kolejności przy remisie). Bez tego serwis policzy błędną passę przy dwóch
  meczach w jednym dniu.
- **Pułapka polskiej odmiany liczebników w UI passy**: "3 zwycięstwa z rzędu"
  wymaga innej końcówki niż "1 zwycięstwo" czy "5 zwycięstw" (polska
  liczba mnoga ma trzy formy zależne od liczby). Żeby uniknąć tej klasy
  błędu w widoku, passa jest prezentowana jako zwarty, nieodmienny format
  "3× W" / "2× P" / "1× R" (W/R/P = skróty Wygrana/Remis/Porażka) zamiast
  pełnego zdania — zero odmiany do przetestowania.
- **Wykres W/D/L nie liczy się jako "seria" wymagająca legendy**: to jeden
  wymiar (liczba meczów) rozbity na 3 skategoryzowane słupki, z których
  każdy ma już własną etykietę osi (Wygrane/Remisy/Porażki) i wartość
  bezpośrednio nad słupkiem — kolor tylko wzmacnia znaczenie (zielony=dobrze,
  czerwony=źle), nigdy nie jest jedynym nośnikiem informacji. Dlatego bez
  osobnego boksu legendy.

## Faza 1: Backend — StatsCalculator, StatsController, trasa, testy

### Przegląd

Cała logika liczenia statystyk plus trasa `/stats` zwracająca dane do
widoku (na tym etapie widok może być gołym `dd()`/tymczasowym szkicem —
prawdziwy Blade przychodzi w Fazie 2). Wszystkie przypadki brzegowe reguł
biznesowych (streak, pechowy kibic, dystans, izolacja właściciela) są
pokryte testami zanim UI w ogóle powstanie.

### Wymagane zmiany:

#### 1. Serwis liczący statystyki

**Plik**: `app/Services/StatsCalculator.php`

**Cel**: Policzyć z kolekcji meczów użytkownika: liczbę wygranych/remisów/
porażek, % zwycięstw (zaokrąglone do pełnej liczby), długość i typ aktualnej
passy, łączny przejechany dystans (suma `distance_km`, ignorując `null` z
meczów domowych) oraz flagę "pechowy kibic" wg FR-010 (`losses > wins &&
losses > draws`).

**Kontrakt**: Statyczna (lub instancyjna, bez zależności) metoda
`forMatches(Collection $matches): array` zwracająca klucze: `wins`, `draws`,
`losses`, `total`, `win_percentage` (int), `streak_length` (int ≥ 1),
`streak_result` (`'win'|'draw'|'loss'`), `total_distance_km` (int),
`is_unlucky_fan` (bool). Zakłada niepustą kolekcję posortowaną od
najnowszego meczu (patrz "Krytyczne szczegóły implementacji") — wywołujący
(kontroler) odpowiada za sortowanie i za niewywoływanie serwisu na pustej
kolekcji.

#### 2. Kontroler statystyk

**Plik**: `app/Http/Controllers/StatsController.php`

**Cel**: Pobrać mecze zalogowanego użytkownika, obsłużyć pusty stan (brak
meczów → widok bez wywoływania kalkulatora) i przekazać wynik do widoku
`stats.index`.

**Kontrakt**: `index(Request $request)` — mecze przez
`$request->user()->gameMatches()->orderByDesc('played_on')->orderByDesc('id')->get()`
(ten sam wzorzec dostępu co `GameMatchController::index()`, patrz "Kluczowe
odkrycia"). Jeśli kolekcja jest pusta, przekaż do widoku `stats: null`;
w przeciwnym razie `stats: StatsCalculator::forMatches($matches)`.

#### 3. Trasa

**Plik**: `routes/web.php`

**Cel**: Nowa trasa GET pod tą samą grupą middleware co mecze.

**Kontrakt**: `GET /stats` → `StatsController::index`, `name('stats.index')`,
w tej samej grupie `Route::middleware(['auth', 'team.set'])` co istniejąca
grupa `matches.*` (linie 26-34) — nie osobna grupa.

#### 4. Testy

**Plik**: `tests/Feature/StatsTest.php`

**Cel**: Pokryć wszystkie reguły biznesowe i przypadki brzegowe zanim
powstanie UI, wzorem `tests/Feature/GameMatchTest.php` (ten sam styl:
`User::factory()->create()` + `Team::factory()->for($user)->create()` +
`GameMatch::factory()->for($user)->create([...])`, asercje przez
`actingAs($user)->get(route('stats.index'))`).

**Kontrakt**: Przypadki testowe:
- Brak meczów → strona pokazuje CTA "dodaj pierwszy mecz" (`assertSee`),
  nie renderuje kafelków statystyk (brak dzielenia przez zero).
- Znany zestaw meczów (np. 3W/1D/2L) → poprawny `win_percentage` (50%),
  poprawne liczniki wygranych/remisów/porażek.
- Streak: seria kilku identycznych najnowszych wyników → poprawna
  `streak_length`/`streak_result`; osobny przypadek gdzie najnowszy mecz
  przerywa wcześniejszą serię → `streak_length === 1`.
- Dwa mecze z tą samą datą `played_on` → deterministyczny wynik streak
  (regres test na kontrakt sortowania z "Krytyczne szczegóły implementacji").
- Pechowy kibic: przypadek `true` (więcej porażek niż zwycięstw i remisów)
  i przypadek `false` (remis liczników lub przewaga zwycięstw/remisów).
- Łączny dystans: suma `distance_km` po kilku meczach wyjazdowych, mecz
  domowy (`distance_km => null`) nie psuje sumy ani nie rzuca wyjątku.
- Izolacja właściciela: mecze drugiego użytkownika (z własną drużyną) nie
  wpływają na statystyki pierwszego — analogiczny wzorzec do
  `test_user_cannot_edit_view_or_delete_another_users_match`
  (`tests/Feature/GameMatchTest.php:320-344`).

### Kryteria sukcesu:

#### Automatyczne

- [ ] `php artisan test --filter=StatsTest` przechodzi (wszystkie przypadki z sekcji Testy powyżej).
- [ ] `php artisan test` (pełna suita) przechodzi bez regresji.

#### Ręczne

- [ ] Na lokalnym `php artisan serve`, zalogowany użytkownik z meczami wchodzi na `/stats` i widzi surowe dane (nawet bez stylowania) zgodne z ręcznie policzonym bilansem dla jego testowych meczów.

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich
automatycznych weryfikacji, zatrzymaj się tutaj po ręczne potwierdzenie
zanim przejdziesz do Fazy 2.

---

## Faza 2: UI — widok /stats, wykres W/D/L, nawigacja

### Przegląd

Prawdziwy widok Blade z kafelkami statystyk, wykresem słupkowym W/D/L,
pustym stanem i integracją z nawigacją/dashboardem.

### Wymagane zmiany:

#### 1. Widok statystyk

**Plik**: `resources/views/stats/index.blade.php`

**Cel**: Wyrenderować panel statystyk albo pusty stan, w tym samym stylu
wizualnym co reszta appki (`<x-app-layout>`, karta
`p-4 sm:p-8 bg-white shadow sm:rounded-lg` jak w `matches/index.blade.php:24`).

**Kontrakt**: Gałąź `@if($stats === null)` → komunikat + link do
`route('matches.create')` (zamiast kafelków z zerami/NaN). Gałąź `@else` →
kafelki: "% zwycięstw" (`$stats['win_percentage']`), "Aktualna passa"
(format "3× W" — patrz "Krytyczne szczegóły implementacji", bez odmiany
liczebników), "Łączny dystans" (`$stats['total_distance_km']` + " km"),
warunkowy kafelek "Pechowy kibic" tylko gdy `$stats['is_unlucky_fan']` jest
`true` (bez przeciwnej etykiety — patrz decyzja użytkownika), oraz wykres
W/D/L (patrz punkt 2 niżej). Cała zawartość widoku po polsku (projekt jest
w pełni PL, patrz `lessons.md`/wzorzec S-01).

#### 2. Wykres słupkowy W/D/L

**Plik**: `resources/views/stats/index.blade.php` (sekcja/partial w tym
samym pliku, np. `@include('stats.partials.wdl-chart', ...)` jeśli
czytelniej jako osobny plik)

**Cel**: Czysty HTML/CSS (bez JS/biblioteki wykresów) słupkowy wykres 3
kategorii: Wygrane/Remisy/Porażki, kolor koduje status (zielony=dobrze,
szary=neutralnie, czerwony=źle), wartość liczbowo nad każdym słupkiem,
etykieta kategorii pod słupkiem.

**Kontrakt**: Kolory z palety design-systemu (`good`/`critical`/`muted` ze
skilla dataviz) — te same hex w light i dark mode (mode-invariant, nie
wymaga osobnej wartości pod `prefers-color-scheme: dark`):
`--bar-win: #0ca30c`, `--bar-loss: #d03b3b`, `--bar-draw: #898781`. Słupek
≤24px szerokości, zaokrąglony górny róg (4px), kwadratowa podstawa
(baseline), wysokość proporcjonalna do `max(wins, draws, losses)` (najwyższy
słupek = pełna wysokość kontenera, reszta skalowana relatywnie — bez osi Y z
liczbami, bo wartość jest już bezpośrednio podpisana nad słupkiem). Bez
legendy (patrz "Krytyczne szczegóły implementacji" — kolor tylko wzmacnia,
etykieta osi już identyfikuje kategorię). Prosty `title="..."` atrybut HTML
na każdym słupku jako minimalny, bezkosztowy hover-affordance (bez JS).

#### 3. Nawigacja

**Plik**: `resources/views/layouts/navigation.blade.php`

**Cel**: Dodać link "Statystyki" do trasy `stats.index`, spójnie z
istniejącym wzorcem linków `team.edit`/`matches.index`.

**Kontrakt**: Nowy `<x-nav-link :href="route('stats.index')"
:active="request()->routeIs('stats.*')">{{ __('Statystyki') }}</x-nav-link>`
zaraz po linku "Mecze" w bloku desktopowym (obecnie linie ~15-23) oraz
odpowiadający mu `<x-responsive-nav-link>` w bloku mobilnym (obecnie linie
~76-84) — dokładnie ten sam idiom `:active="request()->routeIs(...)"` co
`team.*`/`matches.*`.

#### 4. Link z dashboardu

**Plik**: `resources/views/dashboard.blade.php`

**Cel**: Dashboard już linkuje do `matches.index` jednym linkiem — dodać
analogiczny link do `route('stats.index')`, żeby funkcja nie była osiągalna
tylko przez nawigację górną.

**Kontrakt**: Drugi link obok istniejącego linku do meczów, ten sam styl
wizualny/tag co istniejący.

### Kryteria sukcesu:

#### Automatyczne

- [ ] `php artisan test` przechodzi (żadna istniejąca funkcjonalność się nie psuje).
- [ ] `npm run build` (pod Node 20 — `PATH="$HOME/.nvm/versions/node/v20.20.2/bin:$PATH" npm run build`, patrz pułapka z S-04) kompiluje bez błędów i wkompilowuje wszystkie nowe klasy Tailwind użyte w widoku.

#### Ręczne

- [ ] Ręczna weryfikacja na `php artisan serve`: link "Statystyki" widoczny i aktywny (podświetlony) na `/stats`, w obu blokach nawigacji (desktop i mobile — otworzyć w wąskim viewporcie, patrz pułapka `sm:` z S-04).
- [ ] Wykres W/D/L renderuje się poprawnie zarówno w light, jak i dark mode (jeśli przeglądarka/OS wspiera `prefers-color-scheme`), kolory zgodne z kontraktem (zielony/szary/czerwony).
- [ ] Pusty stan (świeże konto z drużyną, bez meczów) pokazuje komunikat + link do dodania pierwszego meczu, nie kafelki z zerami.
- [ ] Kafelek "Pechowy kibic" pojawia się tylko dla konta z przewagą porażek, znika (nie pokazuje przeciwnej etykiety) dla innych kont testowych.
- [ ] Link z dashboardu prowadzi na `/stats` i działa tak samo jak link nawigacyjny.

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich
automatycznych i ręcznych weryfikacji, zmiana jest gotowa do
`/10x-impl-review`.

---

## Strategia testowania

### Testy jednostkowe/feature:

- Wszystkie reguły biznesowe (bilans, %, streak, pechowy kibic, dystans) —
  patrz Faza 1, sekcja Testy, dla pełnej listy przypadków.

### Testy integracyjne:

- Pełny przepływ HTTP: `actingAs($user)->get(route('stats.index'))` →
  `assertOk()` + `assertSee()` na kluczowych wartościach, dla obu gałęzi
  (pusty stan i stan z meczami).

### Kroki testowania ręcznego:

1. Zalogować się na konto z drużyną i kilkoma meczami o różnych wynikach,
   sprawdzić `/stats` pokazuje poprawny bilans (ręczne przeliczenie).
2. Zmienić wynik jednego meczu przez `/matches/{id}/edit` (S-04), wrócić na
   `/stats`, potwierdzić że statystyki się zaktualizowały bez żadnej
   dodatkowej akcji (dowód na to, że brak cache'owania działa poprawnie).
3. Usunąć mecz, potwierdzić że statystyki spadły o dokładnie ten mecz.
4. Sprawdzić konto bez żadnych meczów — pusty stan, nie błąd 500.
5. Sprawdzić wąski viewport (telefon) — panel i wykres nie wychodzą poza
   ekran, nawigacja mobilna pokazuje link "Statystyki".

## Uwagi dotyczące wydajności

Brak realnego ryzyka — wolumen danych jednego użytkownika jest znikomy
(dziesiątki/setki meczów maksymalnie), a agregacja to prosta pętla w PHP nad
już pobraną kolekcją, bez dodatkowych zapytań do bazy.

## Uwagi dotyczące migracji

Nie dotyczy — brak zmian w schemacie bazy danych.

## Referencje

- Podobna implementacja (wzorzec serwisu): `app/Services/DistanceCalculator.php` (S-03).
- Podobna implementacja (wzorzec kontrolera/testów): `app/Http/Controllers/GameMatchController.php`, `tests/Feature/GameMatchTest.php` (S-03/S-04).
- Reguła FR-010: `context/foundation/prd.md:134`.
- Ryzyko roadmapy dla S-05 (spójność po edycji/usunięciu): `context/foundation/roadmap.md` — sekcja S-05.

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Backend — StatsCalculator, StatsController, trasa, testy

#### Automatyczne

- [x] 1.1 `php artisan test --filter=StatsTest` przechodzi
- [x] 1.2 `php artisan test` (pełna suita) przechodzi bez regresji

#### Ręczne

- [x] 1.3 Ręczna weryfikacja surowych danych `/stats` zgodnych z ręcznym przeliczeniem

### Faza 2: UI — widok /stats, wykres W/D/L, nawigacja

#### Automatyczne

- [ ] 2.1 `php artisan test` przechodzi bez regresji
- [ ] 2.2 `npm run build` (Node 20) kompiluje i wkompilowuje nowe klasy Tailwind

#### Ręczne

- [ ] 2.3 Link "Statystyki" widoczny i aktywny w nawigacji desktop i mobile
- [ ] 2.4 Wykres W/D/L poprawny w light i dark mode
- [ ] 2.5 Pusty stan pokazuje CTA, nie zera
- [ ] 2.6 Kafelek "Pechowy kibic" pojawia się/znika poprawnie
- [ ] 2.7 Link z dashboardu do `/stats` działa
