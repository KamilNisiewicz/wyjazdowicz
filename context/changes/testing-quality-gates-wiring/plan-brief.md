# Quality-gates wiring (test-plan.md Phase 4) — Krótki plan

> Pełny plan: `context/changes/testing-quality-gates-wiring/plan.md`
> Badania: `context/changes/testing-quality-gates-wiring/research.md`

## Co i dlaczego

Wpinamy dwie bramki jakości do `.github/workflows/deploy.yml` — jedynego pipeline'u CI/CD projektu, który dziś leci prosto z `npm run build` do `git pull && migrate --force` na produkcji, bez żadnej weryfikacji po drodze. Zamyka to Fazę 4 rollout-u testów (`test-plan.md`): Ryzyko #1 (zepsuty merge trafia na produkcję, bo nic nie uruchamia testów) i Ryzyko #4 (build frontendu cicho pomija klasę Tailwind i nikt tego nie łapie przed deployem).

## Punkt wyjścia

Jeden job `deploy`: checkout → PHP 8.5 → Node `lts/*` → `npm ci` → `npm run build` → rsync → blok SSH (`git pull`, `composer install`, `migrate --force`, cache rebuild) pod `set -e`. Zero kroków testowych. Zestaw testów (85 testów, SQLite `:memory:`, ~1.3s) jest w pełni gotowy pod CI bez żadnych zmian infrastruktury. Badanie skorygowało pierwotne założenie o mechanizmie Ryzyka #4: wersja Node w CI (`lts/*`) już jest bezpieczna — prawdziwa luka to brak sprawdzenia *zawartości* skompilowanego CSS.

## Pożądany stan końcowy

Push do `master` z czerwonym testem nigdy nie dociera do `migrate --force` — job `deploy` jest zablokowany przez `needs: test`. Build, który cicho nie skompilował używanej klasy responsywnej, jest wyłapywany deterministycznym grepem przed rsync/SSH, niezależnie od przyczyny (nie tylko wersji Node).

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
|---|---|---|---|
| Wersja PHP dla joba test | 8.5 (jak produkcja) | Najciaśniejszy sygnał — zielone CI = przejdzie na serwerze | Plan |
| Mechanizm sprawdzenia CSS | Grep stałej listy znanych klas | Prosty, deterministyczny, wystarczający po korekcie badania (mechanizm Node już zamknięty) | Plan |
| Naprawa `composer.json` scripts.test | Poza zakresem — użyj `php artisan test` bezpośrednio | Zero zależności od wersji Composera na runnerze | Plan |
| Mechanizm bramkowania | `needs: test` na jobie `deploy` (nie kolejność kroków w jednym jobie) | Jedyny sposób, żeby GitHub Actions faktycznie zablokował deploy | Badania |
| Escapowanie w grepie | `\:` zamiast `:` w klasach | Potwierdzone realnym buildem — Tailwind kompiluje `sm:hidden` jako `.sm\:hidden{...}` | Plan |

## Zakres

**W zakresie:**
- Nowy job `test` (PHP 8.5, `php artisan test`) + `needs: test` na jobie `deploy`
- Krok weryfikujący skompilowany CSS pod kątem 6 znanych klas responsywnych (escaped grep)
- Wpis §6.5 w `test-plan.md` + oznaczenie Fazy 4 jako `complete`

**Poza zakresem:**
- Naprawa zepsutego `composer.json` `scripts.test`
- Staging tier, atomic deploy/rollback, trigger `pull_request` jako stały element workflow
- Pełny diff źródło-vs-output wszystkich klas Tailwind (tylko stała lista)

## Architektura / Podejście

Dwie niezależne, addytywne zmiany w tym samym pliku (`deploy.yml`), każda weryfikowalna osobno: (1) nowy job + `needs:` dla bramki testowej, (2) nowy krok w istniejącym jobie `deploy` po `npm run build` dla bramki CSS. Trzecia faza zamyka pętlę dokumentacyjną w `test-plan.md`.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
|---|---|---|
| 1. Test-suite gate | Job `test` + `needs: test` na `deploy` | Weryfikacja wymaga tymczasowej zmiany triggera (`on.push.branches`), bo jedyny trigger dziś to push na `master` |
| 2. CSS class verification | Krok grep po `npm run build`, przed rsync | Błędne escapowanie (`:` zamiast `\:`) sprawiłoby, że check ZAWSZE fałszywie failuje |
| 3. Cookbook update | §6.5 wypełnione, Faza 4 `complete` w §3 | — |

**Wymagania wstępne:** brak — jeden plik do zmiany, brak zależności od wcześniejszych faz rollout-u
**Szacowany nakład pracy:** ~1 sesja, 3 fazy

## Otwarte ryzyka i założenia

- Weryfikacja ręczna wymaga tymczasowego dopisania branch roboczej do `on.push.branches` i jej usunięcia przed finalnym commitem na `master` — łatwo zapomnieć ten krok
- Stała lista klas w Fazie 2 wymaga ręcznej aktualizacji, gdy pojawi się nowa "krucha" klasa responsywna — świadomie zaakceptowany kompromis (patrz Kluczowe decyzje)

## Kryteria sukcesu (podsumowanie)

- Push z czerwonym testem na `master` nie odpala bloku SSH/`migrate --force`
- Build z brakującą znaną klasą responsywną failuje przed rsync, z jasnym komunikatem które klasy brakują
- `test-plan.md` §6.5 dokumentuje wzorzec na tyle dobrze, że dodanie trzeciej bramki nie wymaga ponownego odkrywania `needs:`/escapowania
