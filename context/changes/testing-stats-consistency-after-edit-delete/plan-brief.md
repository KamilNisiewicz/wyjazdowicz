# Stats consistency after edit/delete — Krótki plan

> Pełny plan: `context/changes/testing-stats-consistency-after-edit-delete/plan.md`
> Badania: `context/changes/testing-stats-consistency-after-edit-delete/research.md`

## Co i dlaczego

Dodajemy testy dowodzące, że `/stats` (bilans, passa/streak, flaga
"pechowy kibic") aktualizuje się natychmiast po edycji lub usunięciu
meczu, bez żadnej dodatkowej akcji użytkownika. To zamyka lukę pokrycia
zidentyfikowaną dla Ryzyka #3 z `test-plan.md` — dziś `StatsTest.php` nie
ma ani jednego testu edycji/usunięcia.

## Punkt wyjścia

Statystyki nie mają żadnego cache'a ani zapisanego agregatu — każde
żądanie `/stats` liczy wszystko od nowa z bazy (świadoma decyzja
projektowa z S-05). Realne ryzyko nie jest więc "stary cache", tylko
niejawny kontrakt: `StatsCalculator` ufa, że otrzymana kolekcja jest
posortowana od najnowszego meczu, i nigdy tego nie weryfikuje. Dziś
`StatsController` zawsze dostarcza poprawną kolejność — ale nic tego nie
udowadnia pod kątem edycji/usunięcia.

## Pożądany stan końcowy

`php artisan test --filter=StatsTest` zawiera 4 nowe, zielone testy. Plik
`test-plan.md` ma zaktualizowany §6 (cookbook) i wiersz Fazy 2 w §3
oznaczony jako `complete`. Zero zmian w kodzie produkcyjnym.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
|---|---|---|---|
| Defensywny test kontraktu sortowania | Dodać jako osobny test jednostkowy `StatsCalculator` | Zamyka jedyną realną lukę wskazaną przez badanie, tanim kosztem (bez HTTP) | Plan (potwierdzone przez użytkownika) |
| Zakres scenariuszy must-have | Edycja zmieniająca wynik + edycja zmieniająca `played_on`/streak + usunięcie meczu | Wprost pokrywają brzmienie Ryzyka #3 | Plan (potwierdzone przez użytkownika) |
| Usunięcie jedynego meczu danej lokalizacji | Odroczone, poza zakresem tej fazy | Przypadek brzegowy spoza dosłownego brzmienia Ryzyka #3 | Plan (potwierdzone przez użytkownika) |
| Zakres sprawdzanych zakładek | Wszystkie 3 (Ogółem/Dom/Wyjazd) dla scenariuszy edycji; tylko ogólna dla usunięcia | Równowaga sygnał/koszt — edycja to bardziej krucha ścieżka | Plan (potwierdzone przez użytkownika) |
| Struktura plików testowych | Rozszerzenie `tests/Feature/StatsTest.php` w miejscu | Zgodne z istniejącą konwencją projektu (tak samo zrobiono w Fazie 1) | Badania |

## Zakres

**W zakresie:**
- Test: edycja zmieniająca wynik meczu → bilans + flaga "pechowy kibic" na wszystkich 3 zakładkach
- Test: edycja `played_on` przestawiająca kolejność → streak na wszystkich 3 zakładkach (z zakładką "wyjazd" jako kontrolą, która ma pozostać bez zmian)
- Test: usunięcie meczu → bilans + flaga "pechowy kibic" na zakładce ogólnej
- Test jednostkowy: `StatsCalculator::forMatches()` zależy wyłącznie od kolejności podanej kolekcji

**Poza zakresem:**
- Usunięcie jedynego meczu danej lokalizacji (dom/wyjazd) i sprawdzenie powrotu do stanu pustego
- Ponowne testowanie izolacji między użytkownikami (już pokryte gdzie indziej)
- Jakiekolwiek zmiany w `StatsCalculator`, `StatsController`, `GameMatchController` lub widokach

## Architektura / Podejście

Obie fazy rozszerzają istniejący `tests/Feature/StatsTest.php`, używając
utrwalonego wzorca projektu: ręcznie wyliczone oczekiwane wartości w
komentarzach (nigdy niepoliczone przez wywołanie testowanej klasy),
`actingAs($user)->patch(...)`/`->delete(...)` na trasach meczów, a
następnie świeże `GET /stats`, z asercjami przez `assertSee`/`substr_count`
(bo wszystkie 3 zakładki renderują się po stronie serwera w jednej
odpowiedzi — Alpine tylko ukrywa nieaktywne po stronie klienta).

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
|---|---|---|
| 1. Edit-driven stats consistency | 2 testy: flip wyniku + przestawienie streak, oba na 3 zakładkach | Fixture z 4 meczami i konkretnymi datami — łatwo pomylić oczekiwaną kolejność |
| 2. Delete-driven consistency + test kontraktu + cookbook | Test usunięcia, test jednostkowy kontraktu sortowania, aktualizacja §6/§3 | Cookbook musi wskazywać realne referencje, nie placeholdery |

**Wymagania wstępne:** Brak — Faza 1 test-planu (`testing-geocoding-distance-coverage`) już `complete`, ustala wzorzec rozszerzania istniejących plików testowych.
**Szacowany nakład pracy:** ~1 sesja, 2 fazy, 4 nowe metody testowe.

## Otwarte ryzyka i założenia

- Fixture Fazy 1 (test przestawienia streak) używa stałych dat w styczniu
  2026 — pozostają poprawne dopóki projekt jest rozwijany w 2026 roku i
  później (walidacja to `before_or_equal:today`, nie okno czasowe).

## Kryteria sukcesu (podsumowanie)

- `php artisan test --filter=StatsTest` przechodzi z 4 nowymi testami (18 → 22)
- `php artisan test` (pełny zestaw) pozostaje zielony
- `test-plan.md` §6.2/§6.5 wskazują na realne testy, §3 Faza 2 = `complete`
