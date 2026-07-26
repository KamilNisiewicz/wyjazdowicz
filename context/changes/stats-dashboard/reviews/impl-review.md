<!-- IMPL-REVIEW-REPORT -->
# Przegląd implementacji: Panel statystyk (S-05)

- **Plan**: context/changes/stats-dashboard/plan.md
- **Zakres**: Faza 2 z 2 (pełny plan)
- **Data**: 2026-07-26
- **Werdykt**: ZAAKCEPTOWANO
- **Ustalenia**: 0 krytycznych, 1 ostrzeżenie, 1 obserwacja

## Werdykty

| Wymiar | Werdykt |
|-----------|---------|
| Zgodność z planem | PASS |
| Dyscyplina zakresu | PASS |
| Bezpieczeństwo i jakość | WARNING |
| Architektura | PASS |
| Spójność wzorców | PASS |
| Kryteria sukcesu | PASS |

## Dowody weryfikacji kryteriów sukcesu

- `php artisan test` → 69/69 przechodzi (0 błędów), potwierdzone ponownie po zakończeniu obu faz.
- `npm run build` (Node 20) → kompiluje, nowe klasy Tailwind (`grid-cols-2`, `rounded-t`, `bg-red-50` itd.) potwierdzone bezpośrednio w skompilowanym CSS.
- Wszystkie 7 wymaganych przez plan przypadków testowych pokryte 1:1 w `tests/Feature/StatsTest.php` (brak meczów, znany bilans/%, streak + przerwanie serii, remis dat `played_on`, pechowy kibic true/false, suma dystansu z `null`, izolacja właściciela) — zweryfikowane przez subagenta z konkretnymi numerami linii.
- Wszystkie pozycje ręczne w sekcji `## Postęp` są `[x]` z SHA (`07906bc`, `9cb3d56`) i mają odpowiadające potwierdzenie użytkownika w tej sesji (nie "podpisanie na ślepo").

## Ustalenia

### F1 — Brak wymuszonego w kodzie guardu przeciw pustej kolekcji w `StatsCalculator::forMatches`

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Bezpieczeństwo i jakość (Niezawodność)
- **Lokalizacja**: app/Services/StatsCalculator.php:20,28
- **Szczegóły**: `$matches->first()->result` (linia 20) i `$wins / $total * 100` (linia 28) zakładają niepustą kolekcję. Kontrakt jest udokumentowany tylko w docblocku i w treści planu — jedyny obecny wywołujący (`StatsController::index`) faktycznie go respektuje (`$matches->isEmpty() ? null : ...`), więc **dziś nie ma realnego ryzyka crasha**. Ryzyko jest jednak realne na przyszłość: S-06 (`home-away-stats-split`, `context/foundation/roadmap.md`) ma reużyć ten sam serwis do policzenia statystyk osobno dla meczów domowych/wyjazdowych — użytkownik bez żadnego meczu w jednej z tych dwóch kategorii da w przyszłości pustą podkolekcję przekazaną do `forMatches()`, co spowoduje `DivisionByZeroError`/`Error` (500) zamiast kontrolowanego zachowania.
- **Fix**: Dodaj jawny guard na początku `forMatches()` (np. `if ($matches->isEmpty()) { throw new \InvalidArgumentException('StatsCalculator::forMatches() requires a non-empty collection.'); }`), żeby kontrakt był wymuszany przez kod, a nie tylko przez dyscyplinę wywołującego.
- **Decyzja**: FIXED — guard dodany w `app/Services/StatsCalculator.php`, `php artisan test` (69/69) zielone po zmianie.

### F2 — Kolory wykresu W/D/L jako literały hex, nie zmienne CSS `--bar-win`/`--bar-loss`/`--bar-draw`

- **Ważność**: ℹ️ OBSERWACJA
- **Wpływ**: 🏃 NISKI
- **Wymiar**: Zgodność z planem
- **Lokalizacja**: resources/views/stats/index.blade.php:23-25,58
- **Szczegóły**: Plan sugerował zdefiniowanie kolorów jako CSS custom properties (`--bar-win: #0ca30c` itd., wzorem ogólnej wytycznej ze skilla dataviz). Implementacja użyła bezpośrednio literałów hex w tablicy PHP, wstrzykniętych inline przez `style="background-color: {{ $bar['color'] }}"`. Efekt końcowy jest identyczny — te same wartości hex, mode-invariant (nie zmieniają się między light/dark, co i tak było całym sensem tej sugestii w planie). Dodanie osobnych CSS custom properties dla trzech statycznych, nigdy niezmieniających się wartości byłoby czystą abstrakcją bez korzyści — to drobne odstępstwo formy od planu, nie od intencji, i nie wymaga naprawy.
- **Fix**: Brak — zachowanie zgodne z celem, nie warto dodawać niepotrzebnej abstrakcji.
- **Decyzja**: ZAAKCEPTOWANE (nie wymaga akcji)

## Dodatkowe obserwacje bez osobnego wpisu

- Tekst planu (`plan.md`, sekcja "Kluczowe odkrycia") twierdzi, że `StatsController` używa "tego samego wzorca co `GameMatchController::index()`" — w rzeczywistości `GameMatchController::index()` sortuje tylko przez `->latest('played_on')` (jedna kolumna), podczas gdy `StatsController` poprawnie dodaje `->orderByDesc('id')` jako tie-breaker (wymagany przez własny kontrakt planu dotyczący deterministycznej passy). To nieścisłość w opisie planu, nie defekt implementacji — sam kontrakt kontrolera statystyk został zaimplementowany poprawnie i przetestowany (`test_matches_with_same_played_on_date_are_ordered_deterministically_for_streak`).
- W `resources/views/layouts/navigation.blade.php` nadal występują nieprzetłumaczone angielskie stringi "Profile"/"Log Out" — potwierdzone przez `git log -p`, że to dziedzictwo Breeze sprzed tej zmiany, poza zakresem diffu `stats-dashboard`. Nie zgłoszone jako ustalenie tej zmiany, ale ten sam rodzaj problemu co incydent "You're logged in!" z S-01 — kandydat do osobnego zadania porządkowego, jeśli projekt zdecyduje się dociągnąć pełną polonizację UI.
