<!-- IMPL-REVIEW-REPORT -->
# Przegląd implementacji: Ulubiona drużyna i lokalizacja "dom" (S-02)

- **Plan**: context/changes/team-and-home-profile/plan.md
- **Zakres**: Faza 1 z 2, Faza 2 z 2 (pełny plan)
- **Data**: 2026-07-19
- **Werdykt**: WYMAGA UWAGI
- **Ustalenia**: 0 krytycznych, 3 ostrzeżenia, 1 obserwacja

## Werdykty

| Wymiar | Werdykt |
|-----------|---------|
| Zgodność z planem | WARNING |
| Dyscyplina zakresu | PASS |
| Bezpieczeństwo i jakość | WARNING |
| Architektura | PASS |
| Spójność wzorców | WARNING |
| Kryteria sukcesu | PASS |

## Ustalenia

### F1 — Nowe stringi UI Fazy 2 pomijają `lang/pl.json`

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🔎 ŚREDNI — prawdziwy kompromis; zatrzymaj się, aby to przemyśleć
- **Wymiar**: Spójność wzorców
- **Lokalizacja**: resources/views/team/edit.blade.php, resources/views/team/candidates.blade.php, resources/views/layouts/navigation.blade.php, app/Http/Controllers/TeamController.php:27
- **Szczegóły**: Kontrakt Fazy 2 pkt 3 planu wprost mówił: "wszystkie widoczne stringi przechodzą przez `__()`, z tłumaczeniem dopisanym do `lang/pl.json` (kontynuacja wzorca lokalizacji z S-01)". S-01 ustanowił wzorzec: angielski klucz źródłowy → polska wartość w `lang/pl.json` (np. `"Dashboard": "Panel"`). Nowy kod S-02 zamiast tego wpisuje polski tekst bezpośrednio jako argument `__()` (np. `__('Ulubiona drużyna')`) bez żadnego wpisu w `lang/pl.json`. Działa poprawnie *wyłącznie* dlatego, że `APP_LOCALE=pl`, a brakujący klucz tłumaczenia zwraca się jako sam klucz — czyli już po polsku. Gdyby appka kiedyś potrzebowała fallbacku na `APP_FALLBACK_LOCALE=en` (już ustawiony w `.env.example`), widoki profilu/logowania przełączą się na angielski, a widoki drużyny — nie.
- **Poprawka A ⭐ Zalecane**: Zaakceptuj jako świadome uproszczenie, udokumentuj decyzję
  - Siła: Appka jest single-user, bez planów wielojęzyczności (żaden Non-Goal ani FR nie wspomina o EN) — dodawanie identycznościowych wpisów `"Ulubiona drużyna": "Ulubiona drużyna"` do słownika byłoby czystą papierologią bez realnej korzyści.
  - Kompromis: Kod ma teraz dwie konwencje lokalizacji (S-01: EN-klucz→PL-wartość, S-02: PL-klucz-wprost) — kolejny programista/agent może się pogubić, który wzorzec kontynuować.
  - Pewność: MED — słuszne dla obecnego zakresu MVP, ale zależy od tego, czy projekt rzeczywiście nigdy nie doda drugiego języka.
  - Martwy punkt: Nie sprawdzałem, czy `APP_FALLBACK_LOCALE=en` jest gdziekolwiek faktycznie wykorzystywany (np. czy jest scenariusz, w którym locale != pl).
- **Poprawka B**: Dopisz wpisy do `lang/pl.json` dla wszystkich nowych stringów, przywracając formalną zgodność z kontraktem planu i wzorcem S-01
  - Siła: Jedno źródło prawdy dla tłumaczeń, spójne z resztą aplikacji, tanie do zrobienia teraz (kilkanaście wpisów).
  - Kompromis: Wpisy byłyby tożsamościowe (`"X": "X"`) dla języka PL — nie dodają realnej wartości, dopóki nie pojawi się drugi język.
  - Pewność: MED — mechanicznie proste, ale pytanie czy warto.
  - Martwy punkt: Brak znaczących.
- **Decyzja**: ACCEPTED — świadome uproszczenie dla single-user MVP bez planów i18n (Poprawka A). Odejście od wzorca S-01 pozostaje w kodzie bez zmian.

### F2 — Brak walidacji istnienia wybranego indeksu `candidate` w `StoreTeamRequest`

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Bezpieczeństwo i jakość
- **Lokalizacja**: app/Http/Controllers/TeamController.php:40, app/Http/Requests/Team/StoreTeamRequest.php:20-25
- **Szczegóły**: `StoreTeamRequest` waliduje `candidate` tylko jako `integer`, nie sprawdza czy ten indeks realnie istnieje w przesłanej tablicy `candidates`. `TeamController::store()` (linia 40) robi `$validated['candidates'][$validated['candidate']]` bez zabezpieczenia — przy niespójnym payloadzie (np. zmanipulowane ukryte pola formularza, albo błąd JS) skończy się to nieobsłużonym "Undefined array key" (500) zamiast czytelnego błędu walidacji (422). Ryzyko bezpieczeństwa jest niskie (appka single-user, `store()` poprawnie zapisuje tylko do własnego rekordu przez relację `$request->user()->team()`), ale to realna luka niezawodności.
- **Poprawka**: Dodać w `StoreTeamRequest::rules()` regułę sprawdzającą, że `candidate` jest kluczem istniejącym w `candidates`, np. `Rule::in(array_keys($this->input('candidates', [])))` dla pola `candidate`.
- **Decyzja**: FIXED — dodano `Rule::in(array_keys($this->input('candidates', [])))` do `StoreTeamRequest`, plus test regresyjny `test_store_rejects_candidate_index_outside_submitted_candidates`.

### F3 — Mapowanie odpowiedzi Nominatim poza blokiem `try/catch`

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Bezpieczeństwo i jakość
- **Lokalizacja**: app/Services/NominatimGeocoder.php:33-38
- **Szczegóły**: `try/catch (Throwable)` w `search()` obejmuje tylko samo wywołanie HTTP. Późniejsze `collect($response->json())->map(...)` zakłada sztywny kształt odpowiedzi (klucze `display_name`/`lat`/`lon` zawsze obecne w każdym elemencie) i jest poza `try/catch`. Nietypowa odpowiedź Nominatim (zmieniony format, brakujący klucz przy HTTP 200) rzuci nieobsłużony wyjątek zamiast zwrócić `[]`, jak reszta metody deklaruje w kontrakcie ("nie rzuca wyjątku").
- **Poprawka**: Przenieść blok `collect(...)->map(...)` do tego samego `try/catch`, albo użyć `data_get($result, 'display_name')` z bezpiecznym fallbackiem i pominąć rekordy bez wymaganych pól.
- **Decyzja**: FIXED — mapowanie przeniesione do tego samego `try/catch`, dodano `->filter()` odrzucający rekordy bez `display_name`/`lat`/`lon`, plus test regresyjny `test_search_skips_malformed_records_instead_of_throwing`.

### F4 — Brak walidacji zakresu współrzędnych `lat`/`lon`

- **Ważność**: ℹ️ OBSERWACJA
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Bezpieczeństwo i jakość
- **Lokalizacja**: app/Http/Requests/Team/StoreTeamRequest.php:24-25
- **Szczegóły**: `candidates.*.lat`/`candidates.*.lon` mają tylko regułę `numeric`, bez zakresu (-90..90 / -180..180). Ryzyko praktyczne jest niskie dziś (dane pochodzą z Nominatim, user może zepsuć tylko własny rekord), ale te współrzędne staną się podstawą liczenia dystansu w S-03 — błędna wartość wejściowa zepsułaby logikę biznesową głębiej w aplikacji, nie tylko UI tego formularza.
- **Poprawka**: Dodać `between:-90,90` do `candidates.*.lat` i `between:-180,180` do `candidates.*.lon`.
- **Decyzja**: FIXED — dodano `between:-90,90` / `between:-180,180` do reguł walidacji.
