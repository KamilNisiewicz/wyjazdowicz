## Overall concept

- GHA workflow uruchamiany na każdym pull requeście do `master` (`on: pull_request`), plus `workflow_dispatch` do ręcznego testu.
- Nie budujemy własnej composite action — Krok 0 (`10XCHAMPION-PLAN.md`) już rozstrzygnął: `anthropics/claude-code-action@v1` (przypięty do `@<sha>`, nie do ruchomego `@v1`) + reużycie kursowego skilla `10x-impl-review-ci`, skopiowanego do `$HOME/.claude/skills/` w dedykowanym kroku workflow.
- Skill trzeba najpierw pobrać: `npx @przeprogramowani/10x-cli@latest get m5l3` (potwierdzone researchem — nie jest jeszcze obecny lokalnie), i umieścić w `.github/skills/10x-impl-review-ci/` (commitowane — dozwolony wyjątek od reguły gitignorowania `.claude/skills` w tym projekcie, bo repo jest publiczne i lekcja to jawnie zezwala).

## Input parameters

- Tytuł i opis PR-a (`github.event.pull_request.title` / `.body`) — jako kontekst dla skilla, jeśli wskaże `plan.md` do porównania (Step 2 skilla).
- `git diff` względem `origin/${{ github.base_ref }}` — wymaga `fetch-depth: 0` w `actions/checkout` (płytki checkout daje pusty diff).
- Skill sam liczy diff i szuka planu na runnerze (mechanika w `SKILL.md`) — nie musimy ręcznie przekazywać diffa jako `inputs`, tak jak w wariancie hand-rolled z lekcji; to różnica względem przykładowego `requirements.md` z materiału kursowego.

## Code Review Criteria

**Korekta po pobraniu realnego skilla** (patrz `research.md` §Follow-up Research 2026-08-01): `10x-impl-review-ci` NIE ma trybu "ogólne review" — recenzuje wyłącznie względem `plan.md`. Dwa niezależne mechanizmy, dwa osobne joby w tym samym workflow, żaden nie modyfikuje drugiego:

1. **Job własny (zawsze uruchamiany, każdy PR)** — 5 kryteriów wypracowanych w Fazie B, każde 1-10, reużywa już przetestowany `tools/ai-review/review.ts` (Faza A, Claude Agent SDK, structured output przez zod):
   - Poprawność implementacji
   - Dyscyplina typowania (kompensacja braku PHPStan/Larastan)
   - Pokrycie testami proporcjonalne do ryzyka
   - Autoryzacja / scoping zasobów
   - Integralność build assetów (warunkowe — tylko gdy diff dotyka `resources/` lub `*.blade.php`)

   Pełny opis stanów "1" i "10" dla każdego: `10XCHAMPION-PLAN.md` Faza B. Komentarz + własne etykiety `ai-cr:passed`/`ai-cr:failed`.

2. **Job z oficjalnego kursowego skilla (opt-in, etykieta `impl-review`)** — niezmodyfikowana rubryka `10x-impl-review-ci` (7 wymiarów, `references/impl-review-instructions.md`, dryf od planu + bezpieczeństwo/jakość + pokrycie testami), tylko dla PR-ów, które PR-autor jawnie oznaczy etykietą `impl-review` i które wskazują plan. Werdykt `REJECTED` kończy job kodem 1, chyba że dodana etykieta `impl-review-override`.

## Parked for later

- Faza E: promptfoo — porównanie modeli evalami na tym samym zestawie diffów.
- Faza F (opcjonalna): dodatkowa sprawczość agenta poza tym, co daje `claude-code-action` z pudełka.
- Twarda konfiguracja branch protection (`Require status checks to pass`) — `master` dziś nie ma żadnej ochrony gałęzi; czy wchodzi w zakres Fazy D, rozstrzyga plan.

## Expected side-effects

- Job własny (5 kryteriów): komentarz z podsumowaniem + etykiety `ai-cr:passed` (zielona) / `ai-cr:failed` (czerwona), na każdym PR.
- Job oficjalnego skilla (plan-adherence, opt-in `impl-review`): commit raportu do brancha PR-a (`context/changes/<id>/reviews/impl-review.md`, `[skip ci]`), komentarz podsumowujący + inline, commit status `impl-review-ci/verdict`.

## Expected behavior

- Job własny: retriggeruje się naturalnie na każdy nowy push (`synchronize`) — brak potrzeby osobnej etykiety retry; ręczny retry przez natywne "Re-run job" w GitHub Actions, gdy potrzeba (np. przejściowy błąd API), zamiast własnej etykiety `ai-cr:review` (uproszczenie względem pierwszej wersji tego dokumentu).
- Job oficjalnego skilla: opt-in przez dodanie etykiety `impl-review` do PR-a; `REJECTED` blokuje job (kod 1), chyba że doda się `impl-review-override`.

## Known gaps confirmed by research (nie w materiale kursowym wprost)

- Repo ma `default_workflow_permissions: read` — oba joby muszą jawnie deklarować `permissions:`. Job oficjalnego skilla: `contents: write`, `pull-requests: write`, `statuses: write` (potwierdzone w realnym szablonie). Job własny (`review.ts`): `pull-requests: write` do komentarza + etykiet.
- Brak sekretu `ANTHROPIC_API_KEY` w repo — trzeba dodać przed pierwszym żywym przebiegiem (ręcznie, poza sesją agenta).
- `master` bez branch protection — świadoma decyzja: zostaje tak, przynajmniej na tę fazę. Czerwony job (kod 1 przy REJECTED z oficjalnego skilla) jest widoczny w PR Checks, ale nie blokuje fizycznie przycisku Merge.
- Skill `10x-impl-review-ci` musi trafić do **`.claude/skills/10x-impl-review-ci/`** (nie `.github/skills/...`) i być tam commitowany — wymaga wyjątku w `.gitignore` dla tej jednej ścieżki wewnątrz `.claude/skills/`.
