# Podział statystyk dom vs. wyjazd (S-06) — Krótki plan

> Pełny plan: `context/changes/home-away-stats-split/plan.md`

## Co i dlaczego

Rozszerzamy stronę `/stats` (S-05) o statystyki policzone osobno dla meczów
domowych i wyjazdowych — FR-012, podniesione w PRD z nice-to-have do
must-have, bo podział dom/wyjazd to część tożsamości produktu
("Wyjazdowicz"). Bez tego panel pokazuje tylko zbiorczy bilans, nie
odpowiada na pytanie "czy jestem lepszym kibicem w domu, czy na wyjazdach?".

## Punkt wyjścia

`/stats` istnieje od S-05: kafelki (% zwycięstw, passa, dystans, pechowy
kibic) + wykres W/D/L, liczone przez bezstanowy `StatsCalculator` na całej
kolekcji meczów użytkownika. Guard przeciw pustej kolekcji został dodany w
triażu S-05 z myślą właśnie o tym slice'u — reużycie serwisu na
podkolekcjach jest już bezpieczne.

## Pożądany stan końcowy

Użytkownik na `/stats` widzi trzy zakładki (Ogółem/Dom/Wyjazd, Alpine.js —
technologia już obecna w projekcie), każda z własnym bilansem i wykresem.
Zakładka "Dom" nie pokazuje kafelka dystansu (zawsze bez znaczenia dla
meczów domowych). Pusta podkolekcja (np. same wyjazdy) pokazuje komunikat
zamiast zer. Konto bez żadnych meczów w ogóle zachowuje się jak dziś.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| --- | --- | --- | --- |
| Układ UI | Zakładki Alpine.js (Ogółem/Dom/Wyjazd) | Wybór użytkownika — Alpine już jest w projekcie (`dropdown.blade.php`), więc zero nowej zależności | Plan |
| Dystans w zakładce Dom | Pominięty kafelek | `distance_km` zawsze `null` dla meczów domowych — to strukturalny brak danych, nie przybliżenie | Plan |
| Pusta podkolekcja (np. brak meczów domowych) | Komunikat tekstowy w tej zakładce | Spójne z istniejącym wzorcem pustego stanu z S-05, unika dzielenia przez zero | Plan |
| Pechowy kibic (FR-010) | Liczony niezależnie w każdej zakładce | Przychodzi za darmo z reużycia `StatsCalculator` na podkolekcji, ujawnia insight typu "pechowy tylko na wyjazdach" | Plan |
| Duplikacja kodu Blade | Wspólny partial `stats-block.blade.php`, wywoływany 3× | Adresuje wprost ryzyko nazwane w roadmapie ("zduplikowana logika agregująca między widokami") | Plan |
| Struktura faz | Jedna faza (nie backend/UI osobno) | Widok bezpośrednio konsumuje nowe klucze kontrolera — rozdzielenie byłoby sztuczne przy tej złożoności | Plan |

## Zakres

**W zakresie:**
- Rozszerzenie `StatsController::index()` o `homeStats`/`awayStats` (filtr `venue` na już pobranej kolekcji).
- Partial `stats/partials/stats-block.blade.php` (kafelki + wykres W/D/L, reużywalny).
- Zakładki Alpine.js w `stats/index.blade.php`.
- Testy feature: podział, pusty stan per podkolekcja, pechowy kibic per zakładka, brak kafelka dystansu w Dom.

**Poza zakresem:**
- Zmiany w `StatsCalculator` (serwis się nie zmienia, tylko sposób wywołania).
- Nowa trasa, model, migracja.
- Cache'owanie wyników.
- Podsumowanie porównawcze "dom vs wyjazd w jednym zdaniu" (nie wymagane przez FR-012).
- Testy JS/Alpine (Dusk) — zakładki testowane przez asercje na wyrenderowanym HTML.

## Architektura / Podejście

`StatsController` filtruje już pobraną kolekcję meczów w pamięci
(`Collection::where('venue', ...)`, zachowuje sortowanie) i wywołuje
`StatsCalculator::forMatches()` trzykrotnie (ogółem/dom/wyjazd, `null` dla
pustych podkolekcji). Widok renderuje trzy panele Alpine (`x-show`) nad
wspólnym partialem, naśladując wzorzec `x-data`/`x-show` już użyty w
`dropdown.blade.php`.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| --- | --- | --- |
| 1. Podział dom/wyjazd | Kontroler + partial + zakładki + pełne testy | Testowanie widoczności per-zakładka bez wykonania JS — rozwiązane przez `substr_count` na wyrenderowanym HTML |

**Wymagania wstępne:** S-05 (już zrobione, `impl_reviewed`).
**Szacowany nakład pracy:** ~1 sesja, 1 faza (mniejsza niż S-05 — czysty filtr + UI, bez nowej logiki biznesowej).

## Otwarte ryzyka i założenia

- `Collection::where()` (metoda kolekcji) musi być użyta zamiast
  query-builderowego `->where()` — pomylenie tych dwóch nie da błędu
  kompilacji, tylko cichy błąd logiczny lub zbędne zapytanie SQL. Plan
  jawnie to nazywa w "Krytyczne szczegóły implementacji".
- Testowanie zawartości per-zakładka wymaga techniki `substr_count` zamiast
  prostego `assertSee` (Alpine chowa panele w przeglądarce, nie w
  wyrenderowanym HTML) — opisane w kontrakcie testów Fazy 1.

## Kryteria sukcesu (podsumowanie)

- Użytkownik z meczami obu typów widzi poprawny, niezależny bilans w każdej
  z trzech zakładek.
- Zakładka "Dom" nie pokazuje kafelka dystansu; "Ogółem"/"Wyjazd" pokazują.
- Pusta podkolekcja pokazuje komunikat, nie mylące zera.
- Konto bez żadnych meczów w ogóle zachowuje się identycznie jak przed tą
  zmianą (brak regresji na S-05).
