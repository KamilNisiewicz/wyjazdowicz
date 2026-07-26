# Panel statystyk (S-05) — Krótki plan

> Pełny plan: `context/changes/stats-dashboard/plan.md`

## Co i dlaczego

Dodajemy stronę `/stats`, która liczy i pokazuje zbiorcze statystyki
użytkownika na podstawie meczów już zapisanych w S-03/S-04: bilans W/D/L,
% zwycięstw, aktualną passę, wskaźnik "pechowy kibic" (FR-010) i łączny
przejechany dystans. To north-star cel roadmapy razem z S-03 — bez tego
panelu appka jest tylko listą meczów, nie narzędziem do zrozumienia własnej
historii kibicowania.

## Punkt wyjścia

Model `GameMatch` ma już gotowy accessor `result` (win/draw/loss) i
kolumnę `distance_km` policzoną raz przy dodaniu meczu. Dashboard jest
prawie pusty (jeden link do listy meczów), nie ma żadnego widoku
statystykowego ani komponentu kafelka statystyk w projekcie.

## Pożądany stan końcowy

Zalogowany użytkownik z meczami widzi pod `/stats` (i linkiem w nawigacji
oraz na dashboardzie) czytelny panel: wykres słupkowy W/D/L, % zwycięstw,
aktualną passę, łączny dystans i (jeśli dotyczy) etykietę "pechowy kibic".
Bez meczów widzi zachętę do dodania pierwszego, nie mylące zera.
Statystyki są zawsze aktualne — nic nie jest cache'owane.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| --- | --- | --- | --- |
| Łączny dystans w panelu | Tak, dodatkowy kafelek | PRD Business Logic wprost wymienia "zsumowany łączny dystans" jako wynik logiki FR-007 | Plan |
| Lokalizacja panelu | Dedykowana strona `/stats` | Spójne z resztą appki (każda funkcja = własna strona), łatwiej rozbudować w S-06 | Plan |
| Definicja "passy" (FR-009) | Długość serii tego samego wyniku od najnowszego meczu wstecz | Najbardziej intuicyjne znaczenie słowa "passa" | Plan |
| Styl wizualny | Kafelki liczbowe + prosty wykres słupkowy W/D/L | Realizuje wtórne kryterium sukcesu PRD (wizualizacja) już w S-05 | Plan |
| Zaokrąglenie % zwycięstw | Pełna liczba (np. 67%) | Mała próbka meczów nie uzasadnia precyzji dziesiętnej | Plan |
| Pusty stan (0 meczów) | Komunikat + link "Dodaj pierwszy mecz" | Unika mylących "0%"/dzielenia przez zero, prowadzi do akcji | Plan |
| Etykieta "pechowy kibic" | Tylko gdy reguła spełniona, brak przeciwnej etykiety | PRD nie definiuje wprost stanu "niepechowy" — nie wymyślamy nazewnictwa | Plan |
| Format passy w UI | "3× W" zamiast pełnego zdania | Unika pułapki polskiej odmiany liczebników (1/2-4/5+ mają różne końcówki) | Plan |
| Kolory wykresu W/D/L | Status: zielony=wygrana, szary=remis, czerwony=porażka (hex z palety dataviz, mode-invariant) | W/D/L to semantyka dobry/neutralny/zły, nie dowolna kategoria | Plan |
| Architektura backendu | Serwis `StatsCalculator` (jak `DistanceCalculator` z S-03) | Bezstanowa, testowalna klasa, łatwa do reużycia przez S-06 | Plan |

## Zakres

**W zakresie:**
- Serwis `StatsCalculator` liczący bilans/%/streak/dystans/pechowy kibic z kolekcji meczów.
- `StatsController` + trasa `GET /stats` (`stats.index`), middleware `['auth', 'team.set']`.
- Widok `/stats`: kafelki statystyk + wykres W/D/L (czyste HTML/CSS, bez JS) + pusty stan.
- Link w nawigacji (desktop + mobile) i na dashboardzie.
- Testy feature pokrywające wszystkie reguły biznesowe i izolację właściciela.

**Poza zakresem:**
- Podział dom/wyjazd w statystykach — to S-06 (`home-away-stats-split`).
- Cache'owanie/przechowywanie statystyk w bazie.
- Nowe kolumny/migracje.
- Biblioteka JS do wykresów.

## Architektura / Podejście

`StatsController::index()` pobiera mecze użytkownika przez
`$request->user()->gameMatches()->orderByDesc('played_on')->orderByDesc('id')->get()`
(ten sam wzorzec dostępu co `GameMatchController`), przekazuje kolekcję do
bezstanowego `StatsCalculator::forMatches()`, wynik trafia do widoku Blade.
Brak cache'owania oznacza, że statystyki są zawsze spójne z aktualnym stanem
meczów — edycja/usunięcie (S-04) odświeża panel automatycznie, bez logiki
inwalidacji.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| --- | --- | --- |
| 1. Backend | `StatsCalculator`, `StatsController`, trasa `/stats`, pełne testy edge-case'ów | Błędna kolejność sortowania psuje wyliczenie passy przy remisach dat |
| 2. UI | Widok `/stats` z kafelkami + wykresem W/D/L, link w nawigacji i na dashboardzie | Nowe klasy Tailwind niewkompilowane bez rebuildu Node 20 (znana pułapka z S-04) |

**Wymagania wstępne:** S-03 (już zrobione — mecze z dystansem istnieją).
**Szacowany nakład pracy:** ~1 sesja, 2 fazy (podobny rozmiar do S-04).

## Otwarte ryzyka i założenia

- Kontrakt sortowania między kontrolerem a serwisem (najnowszy mecz first,
  z deterministycznym tie-breakiem po `id`) musi być zachowany — złamanie go
  cicho psuje wyliczoną passę bez błędu w testach, jeśli test nie pokryje
  przypadku dwóch meczów tego samego dnia (plan to pokrywa jawnym testem).
- Wykres W/D/L to nowy wzorzec UI w projekcie (brak wcześniejszego
  komponentu kafelka/wykresu) — pierwsza implementacja tego typu w repo.

## Kryteria sukcesu (podsumowanie)

- Użytkownik z meczami widzi poprawny bilans W/D/L, %, passę, dystans i
  (jeśli dotyczy) etykietę pechowego kibica pod `/stats`.
- Użytkownik bez meczów widzi zachętę do dodania pierwszego, nie błąd ani
  mylące zera.
- Statystyki pozostają poprawne po edycji/usunięciu meczu, bez dodatkowej akcji.
