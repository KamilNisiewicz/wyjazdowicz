# Dodanie meczu z automatycznym obliczeniem dystansu — Krótki plan

> Pełny plan: `context/changes/add-match-with-distance/plan.md`

## Co i dlaczego

Użytkownik zapisuje mecz, na którym był (przeciwnik, data, dom/wyjazd, wynik, miejscowość). Dla meczów wyjazdowych aplikacja automatycznie liczy dystans dom→miejscowość — to gwiazda przewodnia roadmapy i sedno wartości aplikacji (bez tego appka to pusty CRUD).

## Punkt wyjścia

Po S-02 istnieje tabela `teams` z lokalizacją "dom" (`home_lat`/`home_lng`) i gotowy, bezstanowy `NominatimGeocoder::search()`. Nie istnieje jeszcze żaden kod meczów ani biblioteka do liczenia dystansu — obie części budujemy od zera, ale ściśle wzorem sprawdzonego dwuetapowego formularza geokodowania z S-02 (`TeamController`).

## Pożądany stan końcowy

Zalogowany użytkownik z ustawioną drużyną dodaje mecz na `/matches/create`. Mecz domowy zapisuje się od razu (miejscowość = dom drużyny, bez zapytania do Nominatim). Mecz wyjazdowy przechodzi przez potwierdzenie miasta (jak przy ustawianiu drużyny) i zapisuje się z policzonym dystansem w km. `/matches` pokazuje listę wszystkich meczów, posortowaną od najnowszego, z linkiem w nawigacji.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| --- | --- | --- | --- |
| Model wyniku | Gole obu stron (`goals_for`/`goals_against`), W/D/L wyliczane | Zachowuje realny wynik ("2:1") zamiast tylko etykiety, bez ryzyka rozjazdu danych | Plan |
| Nazwa modelu | `GameMatch` / `game_matches` | `Match` to zarezerwowane słowo kluczowe PHP 8+ (wyrażenie `match`) | Plan |
| Miejscowość dla meczu domowego | Auto z `team.home_city`, bez geokodowania | Zero zbędnych zapytań do Nominatim (limit ~1/s), spójne z FR-007 (dystans tylko dla wyjazdów) | Plan |
| Data meczu | Nie może być w przyszłości | Appka śledzi historię obecności, nie planuje wyjazdów | Plan |
| Błąd geokodowania miasta wyjazdu | Twardy błąd walidacji, nic nie zapisane | Gwarantuje, że każdy zapisany mecz wyjazdowy ma policzony dystans (guardrail PRD) | Plan |
| Lista meczów | Osobna strona `/matches` | Dashboard zostaje wolny pod przyszły panel statystyk (S-05) bez przebudowy | Plan |
| Precyzja dystansu | Haversine, pełne km | Wystarczająca dla "ile km przejechałem", bez fałszywej precyzji linii "po ptaku" | Plan |
| Weryfikacja formuły dystansu | Test na znanej parze miast (Warszawa–Kraków) z tolerancją | Bezpośrednio realizuje ryzyko z roadmapy S-03 | Plan |

## Zakres

**W zakresie:**
- Formularz dodania meczu (przeciwnik, data, dom/wyjazd, wynik, miejscowość)
- Automatyczne liczenie dystansu dla wyjazdów (reużycie `NominatimGeocoder`)
- Lista meczów `/matches` + link w nawigacji i z dashboardu

**Poza zakresem:**
- Edycja/usuwanie meczu (S-04)
- Panel statystyk: bilans, %, passa, wskaźnik pecha (S-05)
- Podział dom/wyjazd w statystykach (S-06)
- Przebudowa dashboardu
- Baza drużyn/przeciwników

## Architektura / Podejście

Nowa tabela `game_matches` (1:N z `users`, wzorem kolumn lat/lng z `teams`). Nowy izolowany serwis `DistanceCalculator` (czysta funkcja haversine, testowalna bez sieci) — oddzielony od `NominatimGeocoder` (I/O). Kontroler `GameMatchController` powiela dwuetapowy wzorzec `TeamController` (search→candidates→store) dla wyjazdów, z rozgałęzieniem: mecz domowy zapisuje się bezpośrednio w kroku `search` (nic do potwierdzenia — dane już znane z `Team`).

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| --- | --- | --- |
| 1. Model danych i logika dystansu | Migracja, model `GameMatch`, `DistanceCalculator` + test na znanej parze miast | Błędna formuła dystansu unieważnia wartość appki (guardrail PRD) |
| 2. Formularz dodawania meczu | Kontroler, walidacja, dwuetapowe potwierdzenie geokodowania dla wyjazdów | Rozgałęzienie dom/wyjazd w jednej akcji może pomylić implementatora |
| 3. Lista meczów i integracja z nawigacją | `/matches`, link w nawigacji i dashboardzie, testy end-to-end | Brak — niskie ryzyko, głównie okablowanie UI |

**Wymagania wstępne:** S-02 ukończone (drużyna + `NominatimGeocoder` już istnieją — potwierdzone, zarchiwizowane).
**Szacowany nakład pracy:** ~2-3 sesje w 3 fazach, podobnie do S-02.

## Otwarte ryzyka i założenia

- Dystans po linii prostej ("po ptaku"), nie rzeczywista trasa drogowa — świadomie zaakceptowane przybliżenie, nazwane w roadmapie.
- Zakłada się, że drużyna zawsze gra mecze domowe w jednym mieście (`team.home_city`) — spójne z modelem `Team` z S-02.

## Kryteria sukcesu (podsumowanie)

- Mecz domowy i wyjazdowy da się dodać i oba pojawiają się na `/matches`
- Dystans meczu wyjazdowego jest policzony automatycznie i sensowny (test na znanej parze miast + ręczna weryfikacja)
- Błędna/nieznana nazwa miejscowości nie pozwala zapisać meczu bez dystansu
