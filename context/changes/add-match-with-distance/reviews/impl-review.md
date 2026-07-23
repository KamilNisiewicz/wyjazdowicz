<!-- IMPL-REVIEW-REPORT -->
# Przegląd implementacji: Dodanie meczu z automatycznym obliczeniem dystansu

- **Plan**: context/changes/add-match-with-distance/plan.md
- **Zakres**: Faza 3 z 3 (pełny przegląd)
- **Data**: 2026-07-23
- **Werdykt**: WYMAGA UWAGI
- **Ustalenia**: 0 krytycznych, 2 ostrzeżenia, 3 obserwacje

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

### F1 — Walidacja goals_for/goals_against bez górnej granicy (ryzyko na produkcyjnym MySQL)

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Bezpieczeństwo i jakość
- **Lokalizacja**: app/Http/Requests/GameMatch/SearchCityRequest.php:23-24, app/Http/Requests/GameMatch/StoreRequest.php:23-24
- **Szczegóły**: Kolumny `goals_for`/`goals_against` to `unsignedTinyInteger` (max 255) w migracji, ale walidacja to tylko `required|integer|min:0`, bez górnej granicy. Na SQLite (testy lokalne) niegroźne, ale produkcja projektu to MySQL (cyberFolks) — wartość >255 przejdzie walidację i wywali nieobsłużony `QueryException` (500) zamiast czytelnego błędu walidacji.
- **Poprawka**: Dodać `'max:255'` do reguł `goals_for`/`goals_against` w obu klasach FormRequest.
  - Siła: Jednowierszowa zmiana w dwóch plikach, zero ryzyka regresji, eliminuje możliwość 500 na produkcji.
  - Kompromis: Brak realnego kompromisu.
  - Pewność: WYSOKA — kolumna DB jest jawnie tinyint, produkcja to MySQL (potwierdzone w infrastructure.md).
  - Martwy punkt: Brak znaczących.
- **Decyzja**: FIXED — dodano max:255 do goals_for/goals_against w obu FormRequest.

### F2 — Test meczu domowego bez Http::fake() jako strażnika przed przypadkowym wywołaniem Nominatim

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Zgodność z planem
- **Lokalizacja**: tests/Feature/GameMatchTest.php:31-55
- **Szczegóły**: Plan (## Strategia testowania) mówi wprost: "Mecz domowy: zapisuje się bez wywołania Nominatim (Http::fake() bez zdefiniowanej trasy nominatim — test failuje, jeśli kontroler jednak strzeli do API)". Obecny test (`test_home_match_is_created_immediately_without_calling_nominatim`) nie ustawia `Http::fake()` wcale — gdyby kontroler przypadkiem strzelił do Nominatim, test wykonałby prawdziwe zapytanie sieciowe zamiast zawieść deterministycznie.
- **Poprawka**: Dodać `Http::fake()` (pusta konfiguracja) na początku testu.
  - Siła: Dokładnie realizuje zamiar planu, jednowierszowa zmiana, nie zmienia dzisiejszego wyniku testu.
  - Kompromis: Brak.
  - Pewność: WYSOKA.
  - Martwy punkt: Brak znaczących.
- **Decyzja**: FIXED — dodano Http::fake() + Http::assertNothingSent() w teście meczu domowego.

### F3 — Brak testu awarii Nominatim (5xx) dla przepływu meczów wyjazdowych

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI
- **Wymiar**: Spójność wzorców
- **Lokalizacja**: tests/Feature/GameMatchTest.php (brak odpowiednika TeamTest::test_search_shows_validation_error_when_nominatim_fails)
- **Szczegóły**: `TeamTest` ma dedykowany test dla odpowiedzi 500 z Nominatim; `GameMatchTest` tylko dla pustej listy (200 z `[]`). Ścieżka kodu w kontrolerze jest identyczna dla obu przypadków (`NominatimGeocoder` zwraca `[]` w obu), więc ryzyko praktyczne jest niskie — to luka względem ustalonego wzorca testowego, nie luka funkcjonalna.
- **Poprawka**: Dodać analogiczny test z `Http::response(null, 500)`.
- **Decyzja**: FIXED — dodano test_away_match_search_shows_validation_error_when_nominatim_fails.

### F4 — Angielski string "You're logged in!" obok nowego polskiego linku na dashboardzie

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI
- **Wymiar**: Spójność wzorców
- **Lokalizacja**: resources/views/dashboard.blade.php:12
- **Szczegóły**: Plik był dotknięty w tej zmianie (dodano link obok), ale oryginalny angielski string z Breeze scaffoldu pozostał — reszta UI jest w pełni spolszczona.
- **Poprawka**: Przetłumaczyć na polski przy okazji.
- **Decyzja**: FIXED — "You're logged in!" → "Jesteś zalogowany!".

### F5 — Współrzędne kandydata pochodzą od klienta, bez ponownej weryfikacji serwerowej

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI
- **Wymiar**: Bezpieczeństwo i jakość
- **Lokalizacja**: app/Http/Requests/GameMatch/StoreRequest.php:27-29, app/Http/Controllers/GameMatchController.php:76-83
- **Szczegóły**: `candidates[].lat/lon` to wartości z ukrytych pól formularza, kontrolowane przez klienta, nigdy nieweryfikowane ponownie względem Nominatim przed zapisem — użytkownik teoretycznie mógłby sfałszować współrzędne i wymyślony dystans dla własnych danych. Identyczny, świadomie zaakceptowany wzorzec z S-02 (`Team`/`StoreTeamRequest`) — nie regresja wprowadzona tą zmianą, brak akcji potrzebnej dla aplikacji jednoosobowej.
- **Poprawka**: Brak — zaakceptowane ryzyko, spójne z istniejącym wzorcem.
- **Decyzja**: SKIPPED — zaakceptowane jako świadome ryzyko, spójne z S-02, brak akcji dla aplikacji jednoosobowej.
