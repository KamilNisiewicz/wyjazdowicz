# CI/CD wiring dla AI code review na PR — Krótki plan

> Pełny plan: `context/changes/ci-cd-code-review/plan.md`
> Badania: `context/changes/ci-cd-code-review/research.md`
> Wymagania: `context/changes/ci-cd-code-review/requirements.md`

## Co i dlaczego

Wpinamy AI code review w GitHub Actions na PR do `master` — 10xChampion Faza D (M5L2+M5L3). Dwa niezależne mechanizmy w jednym workflow: własny agent (5 kryteriów Fazy B, zawsze) + kursowy skill `10x-impl-review-ci` (zgodność z planem, opt-in etykietą).

## Punkt wyjścia

Repo nie ma workflow na `pull_request` (tylko `deploy.yml` na `push`). Faza A-C 10xChampiona już dostarczyły przetestowany lokalny agent (`tools/ai-review/review.ts`) i 5 kryteriów, ale niecommitowane. Skill `10x-impl-review-ci` jeszcze nigdy nie pobrany.

## Pożądany stan końcowy

Każdy PR dostaje automatyczny komentarz + etykietę `ai-cr:passed`/`ai-cr:failed` od własnego agenta. PR oznaczony `impl-review` dostaje dodatkowo pełne review zgodności z planem, z realną blokadą joba przy `REJECTED` (omijalną `impl-review-override`).

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
|---|---|---|---|
| SDK / ścieżka Fazy D | Claude Agent SDK + `claude-code-action@v1` + reużycie skilla | Ustalone przed startem tej sesji (Krok 0 10XCHAMPION-PLAN.md) | Plan (poprzednia sesja) |
| Lokalizacja skilla | `.claude/skills/10x-impl-review-ci/` (commitowana, wyjątek od reguły) | Realny szablon stage'uje z `git archive origin/<base-ref>` — musi tam być | Badania |
| 5 kryteriów Fazy B | Osobny, zawsze uruchamiany job, reużywa `tools/ai-review/review.ts` | Oficjalny skill nie ma trybu "ogólne review" — tylko plan-adherence | Plan (korekta po badaniach) |
| Etykiety gate'u | `impl-review` / `impl-review-override` (schemat szablonu) | Realny szablon już to implementuje, zero rozjazdu z przyszłymi `10x get m5l3` | Plan |
| Branch protection | Brak — job i tak kończy się kodem 1 na `REJECTED` | Wystarczający sygnał na tę fazę, mniejszy zakres | Plan |
| Retry | Natywne "Re-run job" GitHub, nie własna etykieta `ai-cr:review` | Job własny i tak retriggeruje się na `synchronize` | Plan (uproszczenie `requirements.md`) |
| Weryfikacja na żywo | Realny PR tej właśnie zmiany, nie throwaway PR | PR ma realny `plan.md` — testuje też oficjalny skill sensownie | Plan |

## Zakres

**W zakresie:** workflow z dwoma jobami, vendorowanie skilla, `.gitignore`, commit zaległości z Fazy A-C, pierwszy żywy PR z dowodami.

**Poza zakresem:** branch protection, promptfoo (Faza E), dodatkowa sprawczość agenta (Faza F), modyfikacja rubryki kursowego skilla, etykieta retry.

## Architektura / Podejście

Jeden plik `.github/workflows/review.yml`, dwa joby: `impl-review` (adaptacja oficjalnego szablonu — toolchain PHP/Node zamiast pnpm, reszta bez zmian) i `faza-b-review` (nowy, prosty, woła `tools/ai-review/review.ts`).

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
|---|---|---|
| 1. Vendorowanie + YAML | Workflow gotowy, nic jeszcze nie uruchomione na żywo | Błąd w `.gitignore`-negacji lub YAML wykryty dopiero w Fazie 2 |
| 2. Sekret + żywy PR + dowody | Pierwszy realny branch+PR w repo, oba joby zweryfikowane na żywo, dowody 10xChampion zebrane | Żywe bugi w CI (jak Vite manifest w Fazie 4 test-planu) — pierwszy raz w tym repo z tym mechanizmem |

**Wymagania wstępne:** sekret `ANTHROPIC_API_KEY` (manualne dodanie), zgoda użytkownika na pierwszy realny push/PR.
**Szacowany nakład pracy:** ~1 sesja, 2 fazy (zgodne z szacunkiem 2.5–4.5h z `10XCHAMPION-PLAN.md`).

## Otwarte ryzyka i założenia

- Pierwszy żywy przebieg może ujawnić rozjazd między adaptowanym toolchainem (PHP/Node zamiast pnpm) a resztą szablonu — nieprzetestowana kombinacja.
- Werdykt pierwszego, realnego review od kursowego skilla na tym konkretnym planie jest nieznany z góry (`APPROVED`/`NEEDS ATTENTION`/`REJECTED`) — plan Fazy 2 świadomie nie zakłada konkretnego wyniku.

## Kryteria sukcesu (podsumowanie)

- PR do `master` dostaje automatyczny komentarz + etykietę od własnego agenta, bez ręcznej interwencji.
- PR z etykietą `impl-review` dostaje pełne review zgodności z planem, z realnym gate'em na `REJECTED`.
- Zebrane trzy dowody wymagane do zgłoszenia 10xChampion.
