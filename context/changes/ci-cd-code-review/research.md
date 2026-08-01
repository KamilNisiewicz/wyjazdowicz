---
date: 2026-08-01T14:42:27+02:00
researcher: Claude
git_commit: 585d8eeff413e2ab35b722a7e1d2964a1460e109
branch: master
repository: KamilNisiewicz/wyjazdowicz
topic: "Faza D 10xChampion — CI/CD wiring dla AI code review na PR (M5L2+M5L3)"
tags: [research, codebase, ci-cd, github-actions, claude-code-action, 10x-impl-review-ci]
status: complete
last_updated: 2026-08-01
last_updated_by: Claude
last_updated_note: "Added follow-up research after fetching the real 10x-impl-review-ci artifact (--dry-run/--print), which diverges from the lesson's simplified prose example"
---

# Research: Faza D 10xChampion — CI/CD wiring dla AI code review na PR

**Date**: 2026-08-01T14:42:27+02:00
**Researcher**: Claude
**Git Commit**: 585d8eeff413e2ab35b722a7e1d2964a1460e109
**Branch**: master
**Repository**: KamilNisiewicz/wyjazdowicz

## Research Question

Co jest potrzebne, żeby wpiąć AI code review (5 kryteriów z Fazy B, `verdict: pass|fail`, komentarz + etykiety na PR, retry przez etykietę) w GitHub Actions na każdym pull requeście do `master`, zgodnie z decyzją z Kroku 0 (`anthropics/claude-code-action@v1` + reużycie skilla `10x-impl-review-ci`, przypięte do SHA, nie do `@v1`)?

Zakres celowo ograniczony do samej Fazy D (workflow + wpięcie skilla + sekrety + gałąź). Faza E (promptfoo) i Faza F (dodatkowa sprawczość agenta) — poza zakresem tej rundy.

## Summary

Repo jeszcze nie ma żadnego workflow triggerowanego przez `pull_request` — jedyny istniejący (`deploy.yml`) triggeruje się na `push` do `master`. Skill `10x-impl-review-ci` (kursowy, wersja `10x-impl-review` pod CI) **nie jest jeszcze pobrany lokalnie** — manifest 10x-cli (`.claude/.10x-cli-manifest.json`) wskazuje ostatnio zaaplikowaną lekcję `m3l1`; lekcja `m5l3` (ta, która niesie skill) nigdy nie była pobrana. Trzeba go ściągnąć (`npx @przeprogramowani/10x-cli@latest get m5l3`) i — inaczej niż reszta skilli (`.claude/skills`, gitignorowane per pamięć projektu) — umieścić w **`.github/skills/10x-impl-review-ci/`**, bo lekcja jawnie zaznacza to jako dozwolony wyjątek do commitowania w publicznym repo; workflow kopiuje go stamtąd do `$HOME/.claude/skills/` w dedykowanym kroku.

Dwa konkretne braki potwierdzone bezpośrednio w GitHub API tego repo (nie w dokumentacji ogólnej):
1. **Brak sekretu `ANTHROPIC_API_KEY`** — `gh secret list` pokazuje tylko `SSH_HOST`, `SSH_PORT`, `SSH_PRIVATE_KEY`, `SSH_USER`.
2. **`default_workflow_permissions: "read"`** na poziomie repo (`gh api repos/.../actions/permissions/workflow`) — domyślny `GITHUB_TOKEN` nie ma prawa komentować PR-a ani nadawać etykiet. Workflow musi jawnie zadeklarować `permissions: pull-requests: write` (komentarz) i `issues: write` (etykiety idą przez Issues API nawet na PR-ach) — lekcja kursowa (M5L3) tego bloku w ogóle nie pokazuje, więc to nie jest coś, co można skopiować 1:1 z materiału.

Repo jest **publiczne** (`isPrivate: false`) i `master` **nie ma branch protection** (404 na `GET .../branches/master/protection`) — runnery GHA są darmowe, ale bez reguły ochrony gałęzi nie da się dziś zablokować merge'a na `verdict: fail` przez wymagany status check; to osobna decyzja do podjęcia w planie (czy w ogóle w zakresie Fazy D, czy odłożona).

## Detailed Findings

### Istniejący CI w repo — konwencje do zachowania

- Jedyny workflow: `.github/workflows/deploy.yml` — trigger `push: branches: [master]`, dwa joby: `test` (PHP 8.5 + `npm ci && npm run build` + `php artisan test`) i `deploy` (`needs: test`), używa `secrets.SSH_*`.
- **Wzorzec bramki jakości** już ustalony w tym repo: `needs: <job>` blokuje kolejny job (Faza 4 test-planu, `context/changes/testing-quality-gates-wiring/`). Nowy workflow AI-review powinien iść tą samą logiką dla merge gate, jeśli Faza D go obejmie.
- **Żadna z akcji w `deploy.yml` nie jest przypięta do SHA** (`actions/checkout@v4`, `shivammathur/setup-php@v2`, `burnett01/rsync-deployments@7.0.1`, `appleboy/ssh-action@v1` — wszystkie na ruchome tagi). Krok 0 świadomie decyduje inaczej dla `claude-code-action` ("przypięty do konkretnego `@<sha>`, nie ruchomego tagu") — czyli nowy workflow wprowadzi *ściślejszy* standard niż istniejący `deploy.yml`, nie identyczny z nim. Warto to nazwać wprost w planie, żeby nie było niespójności bez uzasadnienia.
- **Lekcja z Fazy 4 test-planu** (krótkie pipeline'y ~30s nie dają się bezpiecznie przerwać ręcznym pollowaniem) dotyczyła `push`-workflow na `deploy.yml`. Workflow AI-review triggerowany `pull_request` nie dotyka produkcji/sekretów SSH, więc ten konkretny incydent się nie powtórzy — ale sam fakt "pierwszy żywy branch+PR w tym repo" pozostaje realnym ryzykiem operacyjnym per `10XCHAMPION-PLAN.md` (Faza D szacunek czasu).

### Wzorzec z lekcji M5L3 — `anthropics/claude-code-action@v1`

Źródło: `~/Dokumenty/nauka/10xdevs-markdowns/modul5/code-review-w-erze-ai-standardy-dod-i-agent-w-pipeline.md` (linie 269–589).

- Minimalny trigger: `on: pull_request: branches: [master]` (+ opcjonalnie `workflow_dispatch` do ręcznego testu bez czekania na PR).
- Do policzenia `git diff` względem brancha bazowego **wymagany `fetch-depth: 0`** w `actions/checkout` — domyślny checkout jest płytki i diff wychodzi pusty (linia 249, 263).
- Wzorzec dwukrokowy dla ścieżki "gotowy agent" (ta, którą wybrał Krok 0):
  ```yaml
  - name: Udostępnij skill agentowi
    run: |
      mkdir -p "$HOME/.claude/skills"
      cp -r .github/skills/10x-impl-review-ci "$HOME/.claude/skills/"

  - name: Uruchom review w CI
    uses: anthropics/claude-code-action@v1
    with:
      anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
      prompt: |
        /10x-impl-review-ci
        Działasz w CI na pull requeście. Wykonaj skill: porównaj PR
        z jego planem, zapisz raport i opublikuj komentarz w PR.
  ```
  (linie 546–564). Ten fragment **nie zawiera bloku `permissions:`** ani `github_token:` — oba przykłady interaktywne w tej samej lekcji (linie 291–296, 312–318) dodają `github_token: ${{ secrets.GITHUB_TOKEN }}` jawnie, więc prawdopodobnie trzeba to dodać też tutaj razem z `permissions:` (patrz sekcja niżej — potwierdzone bezpośrednio w tym repo, nie założone z dokumentacji).
- Zasada bezpieczeństwa wymieniona dwa razy w lekcji (linia 162, 328): akcje z dostępem do sekretów przypinać do `@<sha>`, nigdy do ruchomego tagu.
- `requirements.md` to nieformalny artefakt kursowy — notatka brainstormingowa w `context/changes/<id>/requirements.md`, pisana **przed** `/10x-research`, cytowana wprost w komendzie badawczej (`/10x-research ci-cd-code-review based on requirements from '@context/changes/ci-cd-code-review/requirements.md'`, linia 221). `10XCHAMPION-PLAN.md` (Faza D, pierwszy punkt) już zakłada ten plik — w tej sesji nie istniał jeszcze w momencie startu researchu, dopisany równolegle z tym dokumentem (patrz `requirements.md` w tym samym folderze).

### Skill `10x-impl-review-ci` — mechanika i status w tym repo

Źródło: ta sama lekcja, sekcja Deep Dive (linie 531–589) + stan lokalny repo.

- **Nie jest pobrany.** `.claude/skills/` zawiera 19 skilli 10x-workflow (w tym `10x-impl-review`, wersja interaktywna dla planów implementacji), ale **brak `10x-impl-review-ci`**. Manifest `.claude/.10x-cli-manifest.json` → `"lessonId": "m5l3"` się nie pojawia; ostatnia zaaplikowana to `m3l1`. `npx @przeprogramowani/10x-cli@latest list 5` potwierdza, że `m5l3` niesie właśnie ten skill ("Deep Dive covers the `10x-impl-review-ci` skill…"). Trzeba go pobrać przed implementacją Fazy D.
- Skill dzieli się na dwie warstwy: `SKILL.md` (mechanika — jak znaleźć plan, policzyć diff, rozdzielić analizę na podagentów, zapisać raport, wystawić status check) i `references/impl-review-instructions.md` (kryteria — plan jako źródło prawdy, 3 analizy: dryf od planu / bezpieczeństwo i jakość / pokrycie testami, 7 wymiarów, werdykt `APPROVED`/`NEEDS ATTENTION`/`REJECTED`).
- **To jest inna rubryka niż 5 kryteriów z Fazy B** tego projektu. Faza B (`10XCHAMPION-PLAN.md`) wypracowała ogólne review (Step 1 skilla — zawsze uruchamiane, niezależne od planu). Zgodność z planem implementacji to Step 2 skilla, warunkowe (gdy PR wskazuje `plan.md`) — **już istniejąca rubryka w `10x-impl-review-ci`, nie do zdublowania**. Prompt w kroku workflow (linie 559–562) musi więc nieść **obie** informacje: (a) wywołanie `/10x-impl-review-ci` dla mechaniki + Step 2, (b) 5 kryteriów z Fazy B jako dodatkowy kontekst dla Step 1 — dokładny sposób wstrzyknięcia (inline w prompcie vs osobny plik czytany przez skill) to decyzja dla `/10x-plan`.
- Skill jest jawnie oznaczony jako dozwolony wyjątek do trzymania w publicznym repo (`> Skill 10x-impl-review-ci może być przechowywany w publicznym repozytorium`, linia 537) — w przeciwieństwie do reguły z pamięci projektu (`feedback_10x_cli_tooling_not_committed`: gitignorować `.claude/skills`). To repo jest zresztą publiczne, więc nie ma tu dodatkowego ryzyka ekspozycji.

### Sekrety i uprawnienia — potwierdzone bezpośrednio w GitHub API tego repo

```
$ gh secret list
SSH_HOST         2026-07-18
SSH_PORT         2026-07-18
SSH_PRIVATE_KEY  2026-07-18
SSH_USER         2026-07-18
# brak ANTHROPIC_API_KEY

$ gh api repos/KamilNisiewicz/wyjazdowicz/actions/permissions/workflow
{"default_workflow_permissions":"read","can_approve_pull_request_reviews":false}

$ gh repo view --json isPrivate,defaultBranchRef
{"defaultBranchRef":{"name":"master"},"isPrivate":false}

$ gh api repos/KamilNisiewicz/wyjazdowicz/branches/master/protection
404 Branch not protected
```

Konsekwencje:
- `ANTHROPIC_API_KEY` trzeba dodać jako repo secret (`gh secret set` lub UI) — płatny, komercyjny klucz, zgodnie z Krokiem 0 (sesja Pro nie działa na izolowanym runnerze GHA).
- Domyślny `GITHUB_TOKEN` na tym repo ma tylko `read`. Bez jawnego bloku `permissions:` w workflow (`pull-requests: write` do komentarza, `issues: write` do etykiet PR-a — GitHub obsługuje etykiety PR-ów przez Issues API), krok komentowania/etykietowania dostanie `403`. To nie jest teoretyczne ryzyko — to zmierzone ustawienie tego konkretnego repo.
- Brak branch protection na `master` oznacza, że nawet gdy skill wystawi status check, nic dziś nie wymusza jego zielonego stanu przed merge'em — "twarda bramka merge'a" z `10XCHAMPION-PLAN.md` (Faza C, punkt otwarty) wymaga albo reguły branch protection (`Require status checks to pass`, ręczna konfiguracja w GitHub UI/API, poza samym YAML), albo świadomej decyzji, że w Fazie D bramka jest tylko sygnałem (etykieta/komentarz), a wymuszenie merge'a zostaje odłożone.

## Code References

- `.github/workflows/deploy.yml:1-113` — jedyny istniejący workflow, wzorzec `needs:` jako bramka, brak SHA-pinningu akcji, sekrety SSH_*.
- `tools/ai-review/review.ts:1-107` — lokalny agent (Faza A), 5 kryteriów Fazy B jako `REVIEW_SCHEMA`, `SYSTEM_PROMPT` z pełnym opisem kryteriów, `touchesBladeOrResources()` — deterministyczna detekcja warunku dla kryterium #5, wzorzec do ewentualnego reużycia treści promptu w kroku Claude Code Action.
- `.claude/skills/10x-impl-review/SKILL.md:1-60` — interaktywna wersja skilla (rozwiązanie wejścia, wykrywanie zakresu git), punkt odniesienia dla tego, co `-ci` modyfikuje.
- `.claude/.10x-cli-manifest.json` — potwierdza brak `m5l3` w historii pobrań (`lessonId: m3l1`).
- `10XCHAMPION-PLAN.md:113-125` (repo root, gitignorowany) — checklist Fazy D, już zakłada dokładnie te kroki potwierdzone tu badawczo.

## Architecture Insights

- Ten projekt konsekwentnie stosuje wzorzec "CI/CD jako kod aplikacji" — sam kurs to nazywa wprost (linia 228: "scenariusz CI/CD traktujemy jak każdą inną funkcjonalność do zaimplementowania"), a repo już to udowodniło w Fazie 4 test-planu (`.github/workflows/deploy.yml` przeszło przez pełny `/10x-new`→`/10x-research`→`/10x-plan`→`/10x-implement`).
- Rozdział mechaniki od kryteriów (skill `SKILL.md` vs `references/*.md`) to ten sam ruch architektoniczny, co rozdział `StatsCalculator` (bezstanowy serwis) od kontrolera w S-05 — kryteria/logika domenowa oddzielone od integracji.
- Decyzja Kroku 0 (SHA-pinning dla `claude-code-action`, mimo że `deploy.yml` tego nie robi dla żadnej ze swoich akcji) wprowadza świadomą niespójność standardu bezpieczeństwa między workflow'ami — uzasadnioną tym, że nowy workflow dostaje bezpośredni dostęp do `ANTHROPIC_API_KEY` i pełnego harnessu Claude Code, więc wyższe ryzyko niż `checkout`/`setup-php`.

## Historical Context (from prior changes)

- `context/changes/testing-quality-gates-wiring/` (Faza 4 test-planu, zarchiwizowane w historii commitów `d402596`/`585d8ee`) — jedyny wcześniejszy przykład w tym repo modyfikacji `.github/workflows/`; ustalił wzorzec `needs:` jako bramka i pokazał żywy bug (Vite manifest) wykryty dopiero w CI, nie lokalnie — ostrzeżenie, żeby planować weryfikację nowego workflow na realnym PR, nie tylko lokalną lekturą YAML.
- `10XCHAMPION-PLAN.md` (repo root, gitignorowany, niecommitowany razem z `tools/ai-review/`) — Krok 0 już rozstrzygnął SDK (Claude Agent SDK) i ścieżkę Fazy D (`claude-code-action@v1` + `10x-impl-review-ci`, nie composite action od zera). Ten research go nie podważa, tylko uszczegóławia brakujące elementy (sekret, uprawnienia, brak lokalnego skilla).
- `context/foundation/lessons.md` — brak wpisu o GHA/CI wykraczającego poza symlink build assets; nic bezpośrednio przenośnego na workflow AI-review poza ogólną zasadą "AGENTS.md jako źródło prawdy".

## Related Research

Brak wcześniejszych dokumentów `research.md` dotyczących CI/CD w tym repo — to pierwszy.

## Follow-up Research 2026-08-01 (podczas /10x-plan)

Podczas planowania pobrałem realną zawartość skilla `10x-impl-review-ci` (`npx @przeprogramowani/10x-cli@latest get m5l3 --dry-run/--print --type skills --name 10x-impl-review-ci`, bez zapisu na dysk). **Realny, produkcyjny szablon (`references/workflow-template.yml` dostarczony razem ze skillem) różni się istotnie od uproszczonego przykładu z prozy lekcji (linie 546–564 głównego dokumentu)** — poniższe koryguje odpowiednie sekcje wyżej.

### Lokalizacja skilla — korekta

`workflow-template.yml` commituje skill w **`.claude/skills/10x-impl-review-ci/`** (nie `.github/skills/...`) i w runtime "stage'uje" go do `$HOME/.claude/skills/` przez `git archive origin/<base-ref>` — celowo, bo `claude-code-action@v1` traktuje głowę PR-a jako nieufną i podmienia `.claude/` repo na wersję z brancha bazowego przed uruchomieniem; katalog user-level leży poza drzewem repo, więc nie jest tym mechanizmem dotykany. Wymaga to jawnego wyjątku w `.gitignore` dla `.claude/skills/10x-impl-review-ci/` (reszta `.claude/skills/` zostaje gitignorowana, per `feedback_10x_cli_tooling_not_committed`).

### Skill jest wyłącznie plan-adherence — korekta założenia Fazy B

`references/impl-review-instructions.md` (pełna treść pobrana) opisuje TYLKO porównanie implementacji z `plan.md` (7 wymiarów: Plan Adherence, Scope Discipline, Safety & Quality, Architecture, Pattern Consistency, Test Coverage, Success Criteria → `APPROVED`/`NEEDS ATTENTION`/`REJECTED`). Nie istnieje żaden tryb "Step 1 ogólne review, zawsze uruchamiane" wewnątrz tego skilla. `SKILL.md` opisuje wprost "grzeczne wyjście, gdy nie znaleziono planu" — neutralny komentarz + `exit 0`, bez żadnej oceny. Wcześniejsze założenie z `10XCHAMPION-PLAN.md`/Fazy B ("Step 1 ogólne, zawsze uruchamiane; Step 2 zgodność z planem, warunkowe") nie odpowiada rzeczywistej mechanice tego konkretnego kursowego skilla — to były dwie niezależne rzeczy, które lekcja opisała razem, ale nie zaimplementowała razem w jednym artefakcie.

**Decyzja podjęta z użytkownikiem** (patrz `plan.md`): 5 kryteriów Fazy B jedzie jako **osobny, zawsze uruchamiany job** w tym samym workflow, reużywający już przetestowany `tools/ai-review/review.ts` (Faza A) zamiast prompt-based podejścia w `claude-code-action`. Oficjalny skill zostaje niezmodyfikowany i obsługuje wyłącznie PR-y z planem.

### Realny gate i etykiety — korekta `requirements.md`

- Trigger: `pull_request: types: [opened, synchronize, reopened, labeled, unlabeled]`, ale sam krok `claude-code-action` odpala się TYLKO gdy PR ma etykietę **`impl-review`** (opt-in, nie "każdy PR") — `if: contains(github.event.pull_request.labels.*.name, 'impl-review') && github.event.pull_request.head.repo.full_name == github.repository` (drugi warunek blokuje fork PR-y, bo dostają `contents: write` + sekrety).
- Krok "Check review verdict" **faktycznie kończy job kodem 1** przy `REJECTED`, chyba że PR ma etykietę **`impl-review-override`** — to już jest realna blokada na poziomie joba (czerwony X w PR Checks), nie tylko "sygnał". Bez branch protection na `master` sam przycisk Merge nadal będzie klikalny mimo czerwonego checka — użytkownik świadomie zaakceptował to ograniczenie na tym etapie (patrz `plan.md`, decyzja "Gate v2").
- `ai-cr:passed`/`ai-cr:failed`/`ai-cr:review` z `requirements.md` **nie istnieją w oficjalnym szablonie** — to były etykiety z uproszczonego przykładu w prozie lekcji, nigdy nie zaimplementowane w prawdziwym skillu. Zostają jednak żywe jako etykiety WŁASNEGO joba (reużycie `review.ts`), bo to nasz mechanizm, nie kursowy.
- Publikowany jest manualny commit status `impl-review-ci/verdict` (POST na SHA bota, bo bot commituje z `[skip ci]`, więc natywny GHA check nie odświeża się na właściwym SHA) — nie standardowy check run.

### Realne uprawnienia — korekta przypuszczenia

Szablon deklaruje `permissions: {}` na poziomie workflow (domyślnie zero) i **`contents: write`, `pull-requests: write`, `statuses: write`** na poziomie joba `impl-review` — nie `issues: write`, jak przypuszczałem wcześniej (labels na PR-ach w tym szablonie są zarządzane ręcznie przez ludzi — `gh pr edit --add-label`/`--add-label impl-review-override` — skill sam nie nadaje etykiet). Osobny job z `review.ts` (Faza A, własny mechanizm) będzie potrzebował własnego, mniejszego zestawu uprawnień do komentowania i nadawania SWOICH etykiet (`ai-cr:*`) — prawdopodobnie `pull-requests: write` + `issues: write` tam, gdzie `gh pr comment`/`gh label add` faktycznie działają na tym joba.

### Dodatkowe operacyjne detale z realnego szablonu (nowe, nie w `research.md` v1)

- **Ochrona przed pętlą**: guard krok sprawdza `git log -1 --author='claude[bot]'` i pomija uruchomienie Claude'a na commitach bota lub gdy zdarzenie to samo `labeled`/`unlabeled` bez nowego kodu (re-ewaluacja tylko gate'u werdyktu).
- **Kontrakt wyjścia**: raport musi zaczynać się od `<!-- IMPL-REVIEW-REPORT -->` — krok "Validate" łapie halucynację formatu i psuje build, jeśli model zboczy z kontraktu.
- **`[skip ci]` w commicie bota** jest wymagany (inaczej nieskończona pętla triggerów) — krok walidacyjny to sprawdza.
- **Fork PR-y są jawnie wykluczone** (`head.repo.full_name == github.repository`) — nie dostają `contents: write` ani dostępu do `ANTHROPIC_API_KEY`.
- **`claude_args`** (nie `allowed_tools`/`max_turns` jak sugerowałaby starsza dokumentacja) niesie `--model`, `--max-turns 60`, `--allowedTools` (w tym `mcp__github_inline_comment__create_inline_comment` do komentarzy inline na konkretnych liniach).

## Open Questions

1. **Dokładny blok `permissions:`** — `pull-requests: write` + `issues: write` wystarczą, czy `claude-code-action` potrzebuje więcej (np. `contents: write` do commitowania raportu z `[skip ci]`, wspomnianego w linii 541 lekcji: "podbić go commitem z `[skip ci]`")? Do zweryfikowania przy `/10x-plan` / pierwszym żywym przebiegu.
2. **Merge gate vs sygnał** — czy Faza D obejmuje też ręczną konfigurację branch protection (`Require status checks to pass before merging`) na `master`, czy zostaje to poza zakresem YAML (świadomie odłożone, tak jak Faza C zostawiła to pytanie otwarte)?
3. **Wstrzyknięcie 5 kryteriów Fazy B w prompt** — inline w kroku `prompt:` obok wywołania `/10x-impl-review-ci`, czy osobny plik w `.github/skills/10x-impl-review-ci/references/` czytany przez skill? Skill sam ma już swoją rubrykę (7 wymiarów, Step 2) — trzeba zdecydować mechanikę złożenia obu zestawów kryteriów bez duplikacji.
4. **Retry przez etykietę `ai-cr:review`** — lekcja go wspomina jako "Zachowanie" w przykładowym `requirements.md` (linia 211), ale nie pokazuje implementacji (osobny trigger `on: pull_request: types: [labeled]`, czy warunek `if:` w tym samym workflow). Do rozstrzygnięcia w planie.
5. **Koszt/limit GHA** — repo publiczne, więc runnery same są darmowe; jedyny realny koszt to `ANTHROPIC_API_KEY` per przebieg (Faza A zmierzyła ~0.03–0.10 USD na diff) — pomnożone przez liczbę PR-ów i retry przez etykietę. Warto to nazwać w planie jako świadomy koszt operacyjny, nie ryzyko blokujące.
