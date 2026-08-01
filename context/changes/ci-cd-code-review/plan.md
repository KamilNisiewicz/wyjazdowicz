# CI/CD wiring dla AI code review na PR (10xChampion Faza D) — Plan implementacji

## Przegląd

Wpinamy AI code review w GitHub Actions na każdym pull requeście do `master`, dwoma niezależnymi mechanizmami w jednym workflow: (1) własny, już przetestowany agent (`tools/ai-review/review.ts`, Faza A) oceniający 5 kryteriów Fazy B na każdym PR, i (2) niezmodyfikowany kursowy skill `10x-impl-review-ci` (pobrany przez `10x-cli get m5l3`), recenzujący zgodność z planem implementacji, opt-in przez etykietę `impl-review`.

## Analiza stanu obecnego

- Repo nie ma żadnego workflow triggerowanego przez `pull_request` — jedyny istniejący (`deploy.yml`) reaguje na `push` do `master`.
- Skill `10x-impl-review-ci` nie jest pobrany lokalnie (manifest 10x-cli wskazuje ostatnio `m3l1`).
- Repo ma `default_workflow_permissions: read` i **brak** sekretu `ANTHROPIC_API_KEY` (potwierdzone `gh api`/`gh secret list`).
- `master` nie ma branch protection.
- Z poprzedniej sesji leżą niecommitowane: `.gitignore` (dopisany `10XCHAMPION-PLAN.md` + `tools/ai-review/node_modules/`), cały folder `tools/ai-review/` (przetestowany lokalny agent Fazy A) i `.nvmrc`.

## Pożądany stan końcowy

Każdy PR do `master` dostaje automatyczny komentarz z werdyktem 5 kryteriów Fazy B (i etykietę `ai-cr:passed`/`ai-cr:failed`). PR oznaczony etykietą `impl-review` dostaje dodatkowo pełne review zgodności z planem od kursowego skilla, z blokadą joba (kod 1) przy werdykcie `REJECTED`, omijalną etykietą `impl-review-override`. Weryfikacja: żywy PR tej właśnie zmiany przechodzi przez oba mechanizmy, z zebranymi dowodami (zrzut pipeline'u, logi joba, komentarz AI) wymaganymi do zgłoszenia 10xChampion.

### Kluczowe odkrycia:

- `references/workflow-template.yml` dostarczony razem ze skillem (pobrany przez `10x get m5l3 --print --type skills --name 10x-impl-review-ci`, patrz `research.md` §Follow-up Research) to gotowy, przetestowany szablon — Faza 1 adaptuje go, nie pisze workflow od zera.
- Skill musi być commitowany w `.claude/skills/10x-impl-review-ci/` (nie `.github/skills/...`) — `claude-code-action@v1` traktuje głowę PR-a jako nieufną i podmienia `.claude/` na wersję z brancha bazowego, więc runtime i tak stage'uje skill do `$HOME/.claude/skills/` przez `git archive origin/<base-ref>`.
- Zweryfikowany empirycznie wzorzec negacji `.gitignore`: `.claude/skills/` (katalog z `/` na końcu) **nie da się** częściowo odnegować przez `!.claude/skills/10x-impl-review-ci/` — git nie schodzi do zignorowanego katalogu w ogóle. Działa dopiero `.claude/skills/*` (glob na dzieci) + `!.claude/skills/10x-impl-review-ci/` po nim.
- `permissions:` zadeklarowane jawnie w YAML obowiązują niezależnie od `default_workflow_permissions: read` repo — nie trzeba zmieniać ustawień repo w GitHub UI, wystarczy poprawny YAML.
- `tools/ai-review/review.ts` (Faza A) jest już gotowe i przetestowane (dwa smoke-testy, `REVIEW_SCHEMA` z 5 kryteriami, `verdict: pass|fail`) — Faza 1 tylko go wywołuje z CI, nie zmienia jego logiki.

## Czego NIE robimy

- Nie konfigurujemy branch protection / required status checks na `master` (świadoma decyzja — job i tak kończy się kodem 1 przy `REJECTED` z oficjalnego skilla, to wystarczający sygnał na tym etapie).
- Nie modyfikujemy rubryki ani mechaniki kursowego skilla `10x-impl-review-ci` (`SKILL.md`, `references/impl-review-instructions.md`) — zostaje dokładnie taki, jak dostarcza `10x-cli`.
- Nie dodajemy własnej etykiety retry (`ai-cr:review` z pierwszej wersji `requirements.md`) — własny job retriggeruje się naturalnie na `synchronize`; ręczny retry to natywne "Re-run job" w GitHub Actions.
- Nie budujemy Fazy E (promptfoo) ani Fazy F (dodatkowa sprawczość agenta) — osobne, przyszłe fazy 10xChampiona.
- Nie zmieniamy `deploy.yml` ani przepływu deployu.

## Podejście do implementacji

Dwa joby w jednym pliku `.github/workflows/review.yml`, oba triggerowane `pull_request` do `master`, każdy z własnym, minimalnym zestawem `permissions:`. Job `impl-review` to adaptacja oficjalnego `workflow-template.yml` (toolchain PHP/Node zamiast pnpm, reszta logiki — guard, staging skilla, walidacja kontraktu, gate na werdykcie — bez zmian). Job `faza-b-review` to nowy, prosty krok wołający już istniejący `tools/ai-review/review.ts`. Faza 1 tylko pisze kod i konfigurację; Faza 2 to pierwszy żywy branch+PR w historii repo (zgodnie z decyzją projektu o przejściu na PR-y na stałe), na którym obie ścieżki są obserwowane na żywo i z którego zbierane są dowody do zgłoszenia certyfikacji.

## Faza 1: Vendorowanie skilla, workflow, domknięcie zaległości z poprzedniej sesji

### Przegląd

Pobranie i commitowanie kursowego skilla, napisanie `.github/workflows/review.yml` (dwa joby), poprawka `.gitignore`, i domknięcie niecommitowanych plików z Fazy A-C (`tools/ai-review/`, `.nvmrc`). Bez żywego uruchomienia — to Faza 2.

### Wymagane zmiany:

#### 1. `.gitignore` — wyjątek dla skilla CI

**Plik**: `.gitignore`

**Cel**: Umożliwić commitowanie `.claude/skills/10x-impl-review-ci/`, zachowując gitignorowanie reszty `.claude/skills/` (reguła projektu, `feedback_10x_cli_tooling_not_committed`).

**Kontrakt**: Zamień linię `.claude/skills/` na:
```
.claude/skills/*
!.claude/skills/10x-impl-review-ci/
```
Wzorzec zweryfikowany empirycznie (patrz Kluczowe odkrycia) — samo dopisanie `!.claude/skills/10x-impl-review-ci/` pod istniejącą linią `.claude/skills/` NIE zadziała.

#### 2. Pobranie skilla `10x-impl-review-ci`

**Plik**: `.claude/skills/10x-impl-review-ci/` (nowy, generowany, nie ręcznie pisany)

**Cel**: Ściągnąć oficjalny, niezmodyfikowany skill (mechanika + kryteria + szablon workflow) z paczki kursowej.

**Kontrakt**: `npx @przeprogramowani/10x-cli@latest get m5l3 --type skills --name 10x-impl-review-ci` (bez `--dry-run`). Efekt: `SKILL.md`, `references/impl-review-instructions.md`, `references/workflow-template.yml` w `.claude/skills/10x-impl-review-ci/`. Nie edytuj żadnego z tych plików ręcznie.

#### 3. Workflow: `.github/workflows/review.yml`

**Plik**: `.github/workflows/review.yml` (nowy)

**Cel**: Dwa joby na `pull_request` do `master`.

**Kontrakt**:

- Trigger: `on: pull_request: { types: [opened, synchronize, reopened, labeled, unlabeled], branches: [master] }`. `permissions: {}` na poziomie workflow (zero domyślnie, jak w oficjalnym szablonie).

- **Job `impl-review`** — kopia `references/workflow-template.yml` z jedną zmianą: krok toolchain (`pnpm/action-setup` + `setup-node` + `pnpm install`) zastąp krokami z `deploy.yml`/`m1l5`-owego `test` joba — `setup-php@v2` (PHP 8.5) → `composer install` → `cp .env.example .env && php artisan key:generate` → `setup-node@v4` (`node-version-file: '.nvmrc'`) → `npm ci` → `npm run build` — bo skill uruchamia `php artisan test` (Automated Verification z planu) i potrzebuje działającej apki. Reszta joba (guard na commit bota, staging skilla przez `git archive origin/$BASE_REF .claude/skills/10x-impl-review-ci`, krok `claude-code-action@v1` z `claude_args`/promptem, walidacja kontraktu, "Check review verdict") — **bez zmian względem szablonu**, łącznie z `if:` na etykiecie `impl-review`, blokadą fork-PR-ów, `permissions: { contents: write, pull-requests: write, statuses: write }`.

- **Job `faza-b-review`** (nowy, nie z szablonu) — zawsze uruchamiany (bez warunku na etykietę), niezależny od `impl-review`:
  - `permissions: { pull-requests: write, issues: write }` (komentarz + własne etykiety; `issues: write` bo endpoint etykiet PR-a jest częścią Issues API).
  - `actions/checkout@v4` z `fetch-depth: 0`.
  - `actions/setup-node@v4` z `node-version-file: '.nvmrc'`.
  - `npm ci` w `tools/ai-review/` (`working-directory: tools/ai-review`).
  - Policz diff względem `origin/${{ github.base_ref }}...HEAD` i przepuść przez `npx tsx review.ts`, `ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}` w env.
  - Sparsuj `verdict` z JSON-a na wyjściu; zamieść komentarz (`gh pr comment --edit-last` albo nowy komentarz — decyzja implementatora, byle nie mnożyć komentarzy przy każdym pushu) i etykietę `ai-cr:passed`/`ai-cr:failed`.

### Krytyczne szczegóły implementacji

- **Etykiety muszą istnieć, zanim `gh pr edit --add-label`/`gh label add` ich użyje** — `gh` nie tworzy etykiety w locie, zwróci błąd na nieistniejącej etykiecie. Krok tworzący (`gh label create ai-cr:passed --color 2ea44f --force` itp., `--force` żeby nie wysypać się na re-run gdy etykieta już istnieje) musi wykonać się przed pierwszym użyciem — najprościej jako pierwszy krok joba `faza-b-review`, oraz analogicznie `impl-review`/`impl-review-override` przed Fazą 2 (patrz niżej).
- **Job `faza-b-review` powinien usuwać przeciwną etykietę przy zmianie werdyktu** (np. PR miał `ai-cr:failed`, po poprawce dostaje `ai-cr:passed`) — inaczej PR zbiera obie etykiety naraz i sygnał staje się mylący.

### Kryteria sukcesu:

#### Automatyczna:

- [ ] `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/review.yml'))"` parsuje się bez błędu
- [ ] `test -f .claude/skills/10x-impl-review-ci/SKILL.md`
- [ ] `git check-ignore .claude/skills/10x-impl-review-ci/SKILL.md` zwraca kod 1 (NIE jest ignorowany)
- [ ] `git check-ignore .claude/skills/10x-impl-review/SKILL.md` zwraca kod 0 (reszta `.claude/skills/` nadal ignorowana)
- [ ] `git status --porcelain` pokazuje `tools/ai-review/`, `.nvmrc`, `.github/workflows/review.yml`, `.gitignore`, `.claude/skills/10x-impl-review-ci/` jako gotowe do commitu (nic z tego nie jest przypadkowo ignorowane)

#### Ręczna:

- [ ] Przeczytany diff `.gitignore` — reszta `.claude/skills/` i `.claude/prompts/` nadal ignorowana
- [ ] Przeczytany `review.yml` — nazwy jobów, warunki `if:`, `permissions:` per job zgodne z tym planem
- [ ] `tools/ai-review/review.ts` nie zmieniony (Faza A zostaje nietknięta)

**Uwaga implementacyjna**: Po zakończeniu tej fazy i przejściu wszystkich automatycznych weryfikacji, zatrzymaj się tutaj po ręczne potwierdzenie, zanim przejdziesz do Fazy 2 — Faza 2 zaczyna się od realnego push/PR.

---

## Faza 2: Sekret, pierwszy żywy branch+PR, zebranie dowodów

### Przegląd

Manualny prerequisite (sekret), potem pierwszy w historii repo realny branch+PR — dokładnie ta zmiana z Fazy 1 — obserwowany na żywo na obu ścieżkach (własny job zawsze, oficjalny skill po dodaniu etykiety `impl-review`), z zebraniem dowodów wymaganych do zgłoszenia 10xChampion.

### Wymagane zmiany:

#### 1. Sekret `ANTHROPIC_API_KEY` (manualne, blokujące, poza sesją agenta)

**Cel**: Bez tego oba joby będą failować na starcie.

**Kontrakt**: Użytkownik dodaje sekret ręcznie (`gh secret set ANTHROPIC_API_KEY` albo GitHub UI) — potwierdzone wcześniej w tej sesji jako świadomie manualny krok, klucz nie przechodzi przez transkrypt agenta.

#### 2. Etykiety repo: `impl-review`, `impl-review-override`, `ai-cr:passed`, `ai-cr:failed`

**Cel**: Muszą istnieć w repo przed pierwszym użyciem (patrz Krytyczne szczegóły implementacji, Faza 1).

**Kontrakt**: `gh label create impl-review --color ... --force`, analogicznie dla pozostałych trzech, jednorazowo.

#### 3. Branch + PR + weryfikacja

**Cel**: Uruchomić `review.yml` na żywo, obserwować oba joby, zebrać dowody.

**Kontrakt**:
1. Commit wszystkich zmian z Fazy 1 (w tym zaległości: `tools/ai-review/`, `.nvmrc`) na nowym branchu (np. `ci-cd-code-review`).
2. Push + `gh pr create` do `master` (**wymaga jawnej zgody użytkownika w trakcie implementacji** — pierwszy realny push/PR w tym repo).
3. Dodaj etykietę `impl-review` do PR-a (żeby przetestować też oficjalny skill — ten PR ma realny `plan.md`, czyli dokładnie ten plik).
4. Obserwuj: `gh run list`, `gh run watch`, `gh pr view --json comments,labels`.
5. Zbierz dowody: zrzut ekranu Actions tab (≥1 job), `gh run view --log > logs.txt` (lub zrzut logów w UI), zrzut komentarza AI na PR.
6. Po zielonym przebiegu (lub świadomie zaakceptowanym `REJECTED`/`NEEDS ATTENTION`, jeśli to pierwszy plan tego kalibru) — decyzja o merge należy do użytkownika, nie jest automatyczna.

### Kryteria sukcesu:

#### Automatyczna:

- [ ] `gh secret list` pokazuje `ANTHROPIC_API_KEY`
- [ ] `gh label list` pokazuje `impl-review`, `impl-review-override`, `ai-cr:passed`, `ai-cr:failed`
- [ ] `gh run list --workflow=review.yml` pokazuje co najmniej jeden przebieg ze statusem `completed`

#### Ręczna:

- [ ] Job `faza-b-review` zamieścił komentarz z werdyktem 5 kryteriów i poprawną etykietę `ai-cr:*` na PR
- [ ] Job `impl-review` (po dodaniu etykiety `impl-review`) zamieścił komentarz podsumowujący + commit raportu (`context/changes/ci-cd-code-review/reviews/impl-review.md`) na branchu PR-a, z `[skip ci]`
- [ ] Zebrane trzy dowody 10xChampion: zrzut pipeline'u, logi joba, zrzut komentarza AI
- [ ] Brak nieskończonej pętli triggerów (commit bota nie odpalił kolejnego pełnego przebiegu poza re-ewaluacją gate'u)

---

## Strategia testowania

### Testy jednostkowe / integracyjne:

Brak nowego kodu aplikacji (PHP) — nic do pokrycia `php artisan test`. Weryfikacja to sam pipeline działający na żywym PR (Faza 2).

### Kroki testowania ręcznego:

1. Otwarcie PR-a z etykietą `impl-review` i obserwacja obu jobów w zakładce Actions.
2. Przegląd treści komentarzy AI pod kątem sensowności (nie tylko "czy się uruchomiło", ale "czy werdykt ma sens dla tego konkretnego diffa").
3. Ręczne dodanie/usunięcie `impl-review-override` na sztucznie spreparowanym `REJECTED` (opcjonalnie, jeśli pierwszy przebieg akurat da `REJECTED`) — potwierdzenie, że gate faktycznie się otwiera.

## Referencje

- Powiązane badania: `context/changes/ci-cd-code-review/research.md` (w tym §Follow-up Research 2026-08-01)
- Wymagania: `context/changes/ci-cd-code-review/requirements.md`
- Wzorzec bramki `needs:`/CI jako kod: `context/changes/testing-quality-gates-wiring/` (Faza 4 test-planu)
- Lokalny agent Fazy A: `tools/ai-review/review.ts`
- Kontekst 10xChampion: `10XCHAMPION-PLAN.md` (repo root, gitignorowany)

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Vendorowanie skilla, workflow, domknięcie zaległości z poprzedniej sesji

#### Automatyczne

- [x] 1.1 `review.yml` parsuje się jako poprawny YAML
- [x] 1.2 `.claude/skills/10x-impl-review-ci/SKILL.md` istnieje
- [x] 1.3 `.claude/skills/10x-impl-review-ci/` NIE jest ignorowany przez git
- [x] 1.4 Reszta `.claude/skills/` nadal jest ignorowana przez git
- [x] 1.5 `git status --porcelain` pokazuje wszystkie oczekiwane pliki jako gotowe do commitu

#### Ręczne

- [ ] 1.6 Diff `.gitignore` przejrzany — zakres wyjątku poprawny
- [ ] 1.7 `review.yml` przejrzany — nazwy jobów/warunki/permissions zgodne z planem
- [ ] 1.8 `tools/ai-review/review.ts` niezmieniony

### Faza 2: Sekret, pierwszy żywy branch+PR, zebranie dowodów

#### Automatyczne

- [ ] 2.1 `ANTHROPIC_API_KEY` obecny w sekretach repo
- [ ] 2.2 Cztery etykiety (`impl-review`, `impl-review-override`, `ai-cr:passed`, `ai-cr:failed`) istnieją w repo
- [ ] 2.3 Co najmniej jeden przebieg `review.yml` ma status `completed`

#### Ręczne

- [ ] 2.4 Job `faza-b-review` zamieścił komentarz + poprawną etykietę `ai-cr:*`
- [ ] 2.5 Job `impl-review` zamieścił komentarz + commit raportu z `[skip ci]` po dodaniu etykiety `impl-review`
- [ ] 2.6 Zebrane trzy dowody 10xChampion (zrzut pipeline'u, logi, zrzut komentarza AI)
- [ ] 2.7 Brak pętli triggerów po commicie bota
