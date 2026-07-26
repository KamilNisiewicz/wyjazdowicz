<!-- IMPL-REVIEW-REPORT -->
# Przegląd implementacji: Podział statystyk dom vs. wyjazd (S-06)

- **Plan**: context/changes/home-away-stats-split/plan.md
- **Zakres**: Faza 1 z 1 (jedyna faza, kompletna)
- **Data**: 2026-07-26
- **Werdykt**: ZAAKCEPTOWANO
- **Ustalenia**: 0 krytycznych, 0 ostrzeżeń, 1 obserwacja

## Werdykty

| Wymiar | Werdykt |
|-----------|---------|
| Zgodność z planem | PASS |
| Dyscyplina zakresu | PASS |
| Bezpieczeństwo i jakość | PASS |
| Architektura | PASS |
| Spójność wzorców | PASS |
| Kryteria sukcesu | PASS |

## Ustalenia

### F1 — Brak jawnego testu HTTP na izolację właściciela per-podkolekcja (dom/wyjazd)

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI — szybka decyzja, nie blokująca
- **Wymiar**: Kryteria sukcesu (pokrycie testami)
- **Lokalizacja**: tests/Feature/StatsTest.php
- **Szczegóły**: Istniejący `test_another_users_matches_do_not_affect_stats` (z S-05) pokrywa izolację właściciela tylko na poziomie bezpośredniego wywołania `StatsCalculator::forMatches()` na kolekcji ogólnej, nie przez pełny request HTTP i nie osobno dla zakładek Dom/Wyjazd. Mechanizm izolacji (`$request->user()->gameMatches()`) się nie zmienił w tej fazie, więc ryzyko jest praktycznie zerowe — filtr `venue` działa na kolekcji już zawężonej do właściciela — ale brak jawnego testu regresyjnego na ten konkretny, nowy przypadek (cudze mecze domowe nie wpływają na moją zakładkę "Dom").
- **Poprawka**: Opcjonalnie dodać test HTTP: drugi użytkownik z własnymi meczami domowymi/wyjazdowymi, potwierdzić że nie wpływają na żadną z trzech zakładek pierwszego użytkownika.
- **Decyzja**: FIXED — dodano `test_another_users_home_and_away_matches_do_not_affect_my_tabs` (tests/Feature/StatsTest.php), commit 776cc44.

## Podsumowanie agentów przeglądowych

**Zgodność z planem** (agent 1): Wszystkie 4 zaplanowane pliki (`StatsController.php`, `stats-block.blade.php`, `stats/index.blade.php`, `StatsTest.php`) zaimplementowane dokładnie zgodnie z kontraktem Fazy 1 — MATCH bez wyjątku. Sekcja "Czego NIE robimy" przestrzegana (zero zmian w `routes/web.php`, `StatsCalculator.php`, modelu, migracjach). "Krytyczne szczegóły implementacji" (Collection::where zamiast query buildera, zachowanie sortowania po filtrze, technika `substr_count` w testach) potwierdzone w kodzie.

**Bezpieczeństwo i wzorce** (agent 2): Brak ryzyk bezpieczeństwa (filtr w pamięci, brak `{!! !!}` na danych użytkownika, brak dodatkowych zapytań SQL). Obsługa pustych podkolekcji poprawna (guard przed wywołaniem `StatsCalculator` na pustej kolekcji). Izolacja właściciela niezmieniona. Wzorzec Alpine.js (`x-show`, `style="display: none;"` fallback) spójny z `dropdown.blade.php`. Testy stylistycznie spójne z istniejącą suitą.
