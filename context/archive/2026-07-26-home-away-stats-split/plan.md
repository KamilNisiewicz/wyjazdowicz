# Podział statystyk dom vs. wyjazd (S-06) — Plan implementacji

## Przegląd

Rozszerzamy istniejącą stronę `/stats` (S-05) o statystyki policzone osobno
dla meczów domowych i wyjazdowych (FR-012, must-have), obok już istniejących
statystyk ogółem. Zero nowych tras, modeli czy migracji — to filtr i
reużycie w pełni przetestowanego `StatsCalculator` na przefiltrowanych
podkolekcjach tej samej kolekcji meczów, prezentowane w trzech zakładkach
(Ogółem/Dom/Wyjazd) na jednej stronie.

## Analiza stanu obecnego

- `StatsCalculator::forMatches()` (`app/Services/StatsCalculator.php:13`) jest
  bezstanowy, przyjmuje dowolną `Collection<GameMatch>` posortowaną
  najnowszy→najstarszy i rzuca `InvalidArgumentException` na pustej
  kolekcji — guard dodany w triażu S-05 dokładnie z myślą o tym slice'u.
  Zero zmian w tym serwisie: wywołujemy go 3× (ogółem, dom, wyjazd) z
  różnymi podkolekcjami.
- `StatsController::index()` (`app/Http/Controllers/StatsController.php:11-21`)
  pobiera mecze przez
  `$request->user()->gameMatches()->orderByDesc('played_on')->orderByDesc('id')->get()`
  — to zwraca `Illuminate\Support\Collection` (już w pamięci, nie query
  builder), więc filtrowanie po `venue` to `$matches->where('venue', 'home')`
  / `'away'` — metoda kolekcji, bez dodatkowego zapytania do bazy. `->where()`
  na kolekcji zachowuje względną kolejność elementów, więc podkolekcje
  pozostają posortowane najnowszy→najstarszy bez ponownego sortowania.
- `GameMatch.venue` to `enum('home','away')` — jedyny dyskryminator, nie ma
  osobnej kolumny `is_home`.
- `resources/views/stats/index.blade.php:1-69` renderuje dziś jedną sekcję:
  4 kafelki (%, passa, dystans, opcjonalnie pechowy kibic) + wykres W/D/L,
  cała logika przygotowania danych (`$streakLetter`, `$maxCount`, `$bars`) w
  inline `@php` bloku. Nie ma partiala — trzeba go wydzielić, żeby nie
  duplikować tego bloku 3×.
- Alpine.js jest już częścią projektu i używane w
  `resources/views/components/dropdown.blade.php:16` (`x-data="{ open: false
  }"`, `x-show`, `style="display: none;"` jako fallback) oraz
  `resources/views/layouts/navigation.blade.php:1` — nowe zakładki mogą
  naśladować dokładnie ten wzorzec, zero nowej zależności frontendowej.
- `distance_km` jest zawsze `null` dla meczów domowych
  (`database/migrations/2026_07_23_175515_create_game_matches_table.php:14-26`)
  — sekcja "Dom" nie ma sensownego dystansu do pokazania.

### Kluczowe odkrycia:

- `app/Services/StatsCalculator.php:13-17` — guard przeciw pustej kolekcji
  już istnieje, gotowy do wywołania na podkolekcjach dom/wyjazd.
- `app/Http/Controllers/StatsController.php:13-16` — kolekcja w pamięci,
  filtrowanie `->where('venue', ...)` bez dodatkowego zapytania.
- `resources/views/stats/index.blade.php:19-27` — inline `@php` z
  `$streakLetter`/`$maxCount`/`$bars` do przeniesienia w całości do nowego
  partiala.
- `resources/views/components/dropdown.blade.php:16-30` — wzorzec Alpine
  (`x-data`, `x-show`, `style="display: none;"`) do naśladowania dla
  zakładek.

## Pożądany stan końcowy

Zalogowany użytkownik na `/stats` widzi trzy zakładki: **Ogółem** (aktywna
domyślnie, bez zmian względem S-05), **Dom** i **Wyjazd** — każda z własnymi
kafelkami (%, passa, pechowy kibic — liczony niezależnie per zakładka) i
wykresem W/D/L, policzonymi na podkolekcji meczów danego typu. Zakładka
"Dom" nie pokazuje kafelka dystansu (zawsze `null`/bez znaczenia dla meczów
domowych). Jeśli użytkownik nie ma żadnego meczu danego typu (np. same
wyjazdy), ta zakładka pokazuje krótki komunikat zamiast kafelków z zerami.
Jeśli użytkownik nie ma żadnych meczów w ogóle, strona zachowuje się
dokładnie jak dziś (pełnostronicowy CTA "dodaj pierwszy mecz", żadnych
zakładek).

## Czego NIE robimy

- Brak nowej trasy — wszystko na istniejącym `GET /stats` (`stats.index`).
- Brak zmian w `StatsCalculator` — reużywamy metodę bez modyfikacji.
- Brak cache'owania wyników per zakładka — liczone na żywo przy każdym
  wejściu, tak jak S-05.
- Brak zmian w modelu/migracji/`venue` enum.
- Brak porównawczego podsumowania "dom vs wyjazd" (np. zdanie "lepszy bilans
  na wyjazdach") — FR-012 wymaga podziału statystyk, nie dodatkowej analizy
  porównawczej; to nie jest wymagane i byłoby rozszerzeniem zakresu.
- Brak testów JS/Alpine (np. Dusk) — zakładki są testowane przez asercje na
  wyrenderowanym HTML (patrz sekcja Testy), zgodnie z tym, że projekt nie ma
  dziś żadnych testów przeglądarkowych.

## Podejście do implementacji

`StatsController::index()` pobiera mecze raz (bez zmian), następnie filtruje
w pamięci na dwie podkolekcje (`home`, `away`) i wywołuje
`StatsCalculator::forMatches()` trzykrotnie: raz na pełnej kolekcji
(ogółem — bez zmian względem S-05), raz na każdej podkolekcji (`null` gdy
podkolekcja pusta, analogicznie do istniejącej obsługi całkowicie pustego
stanu). Widok wydziela wspólny blok kafelki+wykres do partiala
`resources/views/stats/partials/stats-block.blade.php`, wywoływanego 3× z
różnymi danymi wewnątrz trzech paneli zakładek sterowanych Alpine.js
(`x-data="{ tab: 'overall' }"`, `x-show`), naśladując istniejący wzorzec z
`dropdown.blade.php`.

## Krytyczne szczegóły implementacji

- **`->where()` na Collection, nie na query builderze**: `$matches` w
  `StatsController` to już pobrany `Illuminate\Support\Collection` (wynik
  `->get()`), więc `$matches->where('venue', 'home')` to filtr w pamięci
  (metoda `Collection::where`), a nie kolejne zapytanie SQL. Ważne, żeby nie
  pomylić z query-builderowym `->where()` i nie odpalić dodatkowego zapytania
  do bazy per zakładka.
- **Zachowanie sortowania po filtrze**: `Collection::where()` zachowuje
  oryginalne klucze i względną kolejność elementów — podkolekcje `home`/
  `away` pozostają posortowane najnowszy→najstarszy bez ponownego
  `orderByDesc`, więc kontrakt sortowania wymagany przez `StatsCalculator`
  (patrz `app/Services/StatsCalculator.php:9-10`) jest zachowany automatycznie.
- **Testowanie zakładek bez JS**: `assertSee`/odpowiedź HTTP w testach
  feature nie wykonuje Alpine.js — wszystkie trzy panele są obecne w
  wyrenderowanym HTML jednocześnie (ukryte tylko przez `style="display:
  none"` ustawiane w runtime przez Alpine, `x-show` samo w sobie nie usuwa
  elementu z odpowiedzi serwera). To pozwala testować zawartość wszystkich
  trzech zakładek jedną asercją na pełnej treści odpowiedzi, ale wymaga
  ostrożności przy asercjach "X nie występuje w sekcji Y" — patrz kontrakt
  testów w Fazie 1, punkt 4, dla konkretnej techniki (`substr_count`).

## Faza 1: Podział statystyk dom/wyjazd — kontroler, partial, zakładki, testy

### Przegląd

Jedyna faza tej zmiany: rozszerzenie kontrolera o statystyki dom/wyjazd,
wydzielenie partiala, dodanie zakładek Alpine.js do widoku i pełne pokrycie
testami. Backend i UI są na tyle sprzężone (widok bezpośrednio konsumuje
nowe klucze z kontrolera), że rozbijanie na osobne fazy byłoby sztuczne —
potwierdzone z użytkownikiem przy planowaniu.

### Wymagane zmiany:

#### 1. Kontroler statystyk

**Plik**: `app/Http/Controllers/StatsController.php`

**Cel**: Obok istniejących statystyk ogółem, policzyć statystyki osobno dla
podkolekcji meczów domowych i wyjazdowych, z tą samą obsługą pustego stanu
co dziś (per podkolekcja, nie tylko całościowo).

**Kontrakt**: `index()` przekazuje do widoku, obok istniejącego `stats`
(bez zmiany nazwy ani zachowania — ogółem), dwa nowe klucze: `homeStats` i
`awayStats`, każdy `StatsCalculator::forMatches()` na
`$matches->where('venue', 'home')` / `'away'`, albo `null` gdy dana
podkolekcja jest pusta (ten sam wzorzec `isEmpty() ? null : ...` co dziś dla
`stats`). Gałąź, w której `$matches` (pełna kolekcja) jest puste, zostaje
całkowicie bez zmian — przekazuje `stats: null` i **nie** przekazuje
`homeStats`/`awayStats` (widok w tej gałęzi ich nie używa, patrz punkt 3).

#### 2. Partial kafelki + wykres W/D/L

**Plik**: `resources/views/stats/partials/stats-block.blade.php`

**Cel**: Wydzielić istniejący blok kafelków (%, passa, dystans, pechowy
kibic) i wykresu W/D/L z `stats/index.blade.php` do reużywalnego partiala,
tak żeby trzy zakładki (ogółem/dom/wyjazd) wywoływały ten sam kod zamiast
duplikować go 3×. Logika przygotowania danych do wykresu
(`$streakLetter`, `$maxCount`, `$bars`) przenosi się razem z blokiem — cały
`@php` z obecnego `index.blade.php:19-27` staje się częścią partiala,
liczony z parametru `$stats` przekazanego przez `@include`.

**Kontrakt**: Partial przyjmuje trzy zmienne przez `@include(..., [...])`:
`$stats` (array jak zwraca `StatsCalculator::forMatches()`, lub `null`),
`$showDistance` (bool — `false` tylko dla wywołania z zakładki "Dom"),
`$emptyMessage` (string — tekst pokazywany, gdy `$stats === null`, np.
"Brak zapisanych meczów domowych." / "Brak zapisanych meczów
wyjazdowych."). Gdy `$stats === null` → renderuj tylko `$emptyMessage` (bez
linku "dodaj mecz" — to zachowanie zarezerwowane dla całkowicie pustego
konta na poziomie strony, nie dla pustej podkolekcji). Gdy `$stats` nie jest
`null` → renderuj kafelki dokładnie jak dziś (%, passa, opcjonalnie
"Łączny dystans" tylko gdy `$showDistance` jest `true`, opcjonalnie
"Pechowy kibic" gdy `$stats['is_unlucky_fan']`) + wykres W/D/L bez zmian
wizualnych względem obecnej implementacji.

#### 3. Widok — zakładki Alpine.js

**Plik**: `resources/views/stats/index.blade.php`

**Cel**: Zastąpić pojedynczą sekcję statystyk trzema zakładkami
(Ogółem/Dom/Wyjazd) przełączanymi Alpine.js, każda wywołująca partial z
punktu 2 z odpowiednimi danymi. Gałąź całkowicie pustego stanu
(`$stats === null` na poziomie strony, czyli zero meczów w ogóle) zostaje
bez zmian — pełnostronicowy komunikat + link "Dodaj swój pierwszy mecz",
bez żadnych zakładek.

**Kontrakt**: Gdy `$stats` nie jest `null` (użytkownik ma co najmniej jeden
mecz), renderuj kontener `x-data="{ tab: 'overall' }"` z paskiem trzech
przycisków zakładek (`@click="tab = 'home'"` itd., podświetlenie aktywnej
przez `:class` analogicznie do wzorca `:active="request()->routeIs(...)"`
używanego już w nawigacji, tylko po stronie klienta zamiast serwera) i trzema
panelami `x-show="tab === 'overall'"` / `'home'` / `'away'` (`style="display:
none;"` jako fallback SSR, wzorem `dropdown.blade.php:21-29`), z których
każdy zawiera jedno wywołanie partiala z punktu 2:
- Ogółem: `@include('stats.partials.stats-block', ['stats' => $stats, 'showDistance' => true, 'emptyMessage' => ''])` (nigdy `null` w tej gałęzi, `emptyMessage` nieużywane).
- Dom: `['stats' => $homeStats, 'showDistance' => false, 'emptyMessage' => __('Brak zapisanych meczów domowych.')]`.
- Wyjazd: `['stats' => $awayStats, 'showDistance' => true, 'emptyMessage' => __('Brak zapisanych meczów wyjazdowych.')]`.

Etykiety zakładek: "Ogółem", "Dom", "Wyjazd" — krótkie, spójne z istniejącym
nazewnictwem `venue` w reszcie appki (formularz dodawania meczu).

#### 4. Testy

**Plik**: `tests/Feature/StatsTest.php`

**Cel**: Rozszerzyć istniejący plik testów (nie nowy plik — to ta sama
funkcjonalność, S-06 dokłada przypadki do istniejącej suity) o pokrycie
podziału dom/wyjazd, zgodnie ze stylem istniejących testów w tym pliku
(`User::factory()`, `Team::factory()->for($user)`,
`GameMatch::factory()->for($user)->create([...])`,
`actingAs($user)->get(route('stats.index'))`).

**Kontrakt**: Przypadki testowe:
- Użytkownik z meczami domowymi i wyjazdowymi widzi w odpowiedzi treść dla
  wszystkich trzech zakładek jednocześnie (Alpine chowa je dopiero w
  przeglądarce) — `assertSee` na etykietach "Ogółem", "Dom", "Wyjazd" oraz na
  poprawnie policzonych wartościach `%`/passy dla każdej sekcji z osobna
  (skonstruować dane testowe tak, by dom i wyjazd miały różne, jednoznacznie
  odróżnialne wartości `win_percentage`, żeby test faktycznie odróżniał
  sekcje, a nie tylko sprawdzał obecność etykiety).
- Kafelek "Łączny dystans" pominięty w sekcji Dom: policz
  `substr_count($response->getContent(), 'Łączny dystans')` i
  zweryfikuj, że wynosi dokładnie 2 (Ogółem + Wyjazd), nie 3 — patrz
  "Krytyczne szczegóły implementacji" po wyjaśnienie, dlaczego `assertSee`
  samo w sobie nie wystarcza do odróżnienia obecności per-sekcja.
- Użytkownik z tylko meczami wyjazdowymi (zero domowych) →
  `assertSee(__('Brak zapisanych meczów domowych.'))`, zakładka Wyjazd
  pokazuje normalne statystyki. Symetryczny przypadek dla tylko meczów
  domowych.
- Pechowy kibic liczony niezależnie: skonstruować mecze domowe z przewagą
  porażek (`is_unlucky_fan` true dla Dom) i mecze wyjazdowe z przewagą
  zwycięstw (`is_unlucky_fan` false dla Wyjazd) tak, by zsumowany bilans
  ogółem **nie** spełniał reguły FR-010 (np. remis liczników wygranych i
  porażek w sumie) — zweryfikować przez `substr_count` na unikalnym
  fragmencie znaczników kafelka pechowego kibica (np. klasa
  `border-red-200` z `stats/index.blade.php:43`), że wynosi dokładnie 1
  (tylko Dom), nie 2 i nie 0.
- Regresja: istniejące przypadki z S-05 (bilans ogółem, %, passa, pusty
  stan całkowity, izolacja właściciela) nadal przechodzą bez zmian — nie
  usuwać, tylko dodać nowe.

### Kryteria sukcesu:

#### Automatyczne

- [ ] `php artisan test --filter=StatsTest` przechodzi (wszystkie istniejące przypadki z S-05 + nowe z tej fazy).
- [ ] `php artisan test` (pełna suita) przechodzi bez regresji.
- [ ] `npm run build` (pod Node 20 — `PATH="$HOME/.nvm/versions/node/v20.20.2/bin:$PATH" npm run build`, patrz pułapka z S-04) kompiluje bez błędów i wkompilowuje wszystkie nowe klasy Tailwind użyte w zakładkach.

#### Ręczne

- [ ] Na `php artisan serve`, użytkownik z meczami domowymi i wyjazdowymi widzi trzy zakładki, przełączanie działa (klik zmienia widoczną zawartość bez przeładowania strony), zakładka "Ogółem" aktywna domyślnie po wejściu.
- [ ] Zakładka "Dom" nie pokazuje kafelka "Łączny dystans"; zakładki "Ogółem" i "Wyjazd" pokazują go poprawnie.
- [ ] Konto z meczami tylko jednego typu (np. same wyjazdy) pokazuje komunikat "Brak zapisanych meczów domowych." w pustej zakładce, normalne statystyki w pozostałych dwóch.
- [ ] Konto bez żadnych meczów w ogóle zachowuje się identycznie jak przed tą zmianą (pełnostronicowy CTA, brak zakładek) — brak regresji na S-05.
- [ ] Wąski viewport (telefon): zakładki i ich zawartość nie wychodzą poza ekran, przyciski zakładek pozostają klikalne/czytelne.
- [ ] Kafelek "Pechowy kibic" pojawia się niezależnie w każdej zakładce zgodnie z bilansem tej konkretnej podkolekcji (przetestować ręcznie kontem z przewagą porażek tylko na wyjeździe).

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich
automatycznych i ręcznych weryfikacji, zmiana jest gotowa do
`/10x-impl-review`.

---

## Strategia testowania

### Testy jednostkowe/feature:

- Wszystkie nowe reguły biznesowe (filtrowanie per `venue`, pusty stan per
  podkolekcja, pechowy kibic per podkolekcja) — patrz Faza 1, sekcja Testy.
- Brak nowych testów jednostkowych na `StatsCalculator` — serwis się nie
  zmienia, jego istniejące testy (jeśli są) i zachowanie pozostają bez zmian;
  nowość to sposób, w jaki kontroler go wywołuje (3× zamiast 1×).

### Testy integracyjne:

- Pełny przepływ HTTP: `actingAs($user)->get(route('stats.index'))` →
  `assertOk()` + asercje opisane w kontrakcie testów Fazy 1, dla kombinacji:
  (a) mecze obu typów, (b) tylko domowe, (c) tylko wyjazdowe, (d) brak
  meczów w ogóle (regresja S-05).

### Kroki testowania ręcznego:

1. Zalogować się na konto z meczami domowymi i wyjazdowymi o różnych
   bilansach, przełączyć się między trzema zakładkami, ręcznie zweryfikować
   liczby w każdej.
2. Sprawdzić, że zakładka "Dom" nie ma kafelka dystansu.
3. Sprawdzić konto z tylko jednym typem meczów — pusta zakładka pokazuje
   komunikat, nie zera.
4. Sprawdzić konto bez żadnych meczów — strona wygląda dokładnie jak przed
   tą zmianą (brak zakładek).
5. Sprawdzić wąski viewport (telefon) — przyciski zakładek i zawartość
   mieszczą się na ekranie.

## Uwagi dotyczące wydajności

Brak nowego ryzyka — filtrowanie po `venue` to operacja w pamięci nad już
pobraną kolekcją (bez dodatkowych zapytań SQL), a `StatsCalculator` jest
wywoływany maksymalnie 3× zamiast 1× nad tym samym, znikomym wolumenem
danych jednego użytkownika.

## Uwagi dotyczące migracji

Nie dotyczy — brak zmian w schemacie bazy danych, brak zmian w
`StatsCalculator`.

## Referencje

- Plan i implementacja bazowa: `context/changes/stats-dashboard/plan.md` (S-05).
- Wzorzec Alpine.js do naśladowania: `resources/views/components/dropdown.blade.php:16-30`.
- Reguła FR-012: `context/foundation/prd.md:145`.
- Reguła FR-010 (pechowy kibic, reużyta per podkolekcja): `context/foundation/prd.md:134`.
- Ryzyko roadmapy dla S-06 (zduplikowana logika agregująca): `context/foundation/roadmap.md:126`.

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Podział statystyk dom/wyjazd — kontroler, partial, zakładki, testy

#### Automatyczne

- [x] 1.1 `php artisan test --filter=StatsTest` przechodzi — adefc67
- [x] 1.2 `php artisan test` (pełna suita) przechodzi bez regresji — adefc67
- [x] 1.3 `npm run build` (Node 20) kompiluje i wkompilowuje nowe klasy Tailwind — adefc67

#### Ręczne

- [x] 1.4 Trzy zakładki, przełączanie działa, "Ogółem" domyślnie aktywna — adefc67
- [x] 1.5 Zakładka "Dom" bez kafelka dystansu, "Ogółem"/"Wyjazd" z kafelkiem — adefc67
- [x] 1.6 Pusta podkolekcja pokazuje komunikat, nie zera — adefc67
- [x] 1.7 Konto bez żadnych meczów — brak regresji względem S-05 (brak zakładek) — adefc67
- [x] 1.8 Wąski viewport — zakładki nie wychodzą poza ekran — adefc67
- [x] 1.9 Pechowy kibic liczony niezależnie per zakładka — adefc67
