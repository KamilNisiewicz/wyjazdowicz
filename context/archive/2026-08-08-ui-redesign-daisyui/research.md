---
date: 2026-08-08T12:13:42+02:00
researcher: Claude
git_commit: e0dff7164466cac5f7a7bf6cd4b5e92f2f60f636
branch: master
repository: KamilNisiewicz/wyjazdowicz
topic: "UI redesign z DaisyUI — struktura obecnego UI, wzorce Alpine.js, kandydaci do migracji, ryzyka build/legacy"
tags: [research, codebase, tailwind, daisyui, blade, alpinejs, ui]
status: complete
last_updated: 2026-08-08
last_updated_by: Claude
---

# Research: UI redesign z DaisyUI

**Date**: 2026-08-08T12:13:42+02:00
**Researcher**: Claude
**Git Commit**: e0dff7164466cac5f7a7bf6cd4b5e92f2f60f636
**Branch**: master
**Repository**: KamilNisiewicz/wyjazdowicz

## Research Question

Jaka jest obecna struktura UI (build pipeline, layouty, komponenty Blade, wzorce Alpine.js) w całej aplikacji — auth, dashboard, mecze, statystyki, drużyna, layout/nawigacja, welcome — i co trzeba wiedzieć, żeby zaplanować pełną przebudowę na DaisyUI 4.x bez łamania istniejącej interaktywności ani konwencji ustalonych w poprzednich zmianach?

## Summary

- **Build pipeline jest gotowy na DaisyUI bez zmian**: Tailwind 3.4.19 przez klasyczny `postcss.config.js` (`tailwindcss` + `autoprefixer`), `tailwind.config.js` w stylu JS z jednym pluginem (`@tailwindcss/forms`). Dodanie DaisyUI 4.x to jedna linia w `plugins: []`. `@tailwindcss/vite@4` w `package.json` jest potwierdzonym martwym reliktem — nieużywany w `vite.config.js`, można usunąć.
- **13 komponentów Blade + 3 layouty** (Laravel Breeze defaults) niosą niemal całe stylowanie — każdy formularz w aplikacji korzysta z `x-text-input`/`x-input-label`/`x-input-error`/`x-primary-button`/`x-secondary-button`/`x-danger-button`, więc zmiana tych 13 plików daje efekt na całej aplikacji "za darmo".
- **Największe ryzyko migracji to globalny modal event-bus** (`components/modal.blade.php`) używany identycznie przez potwierdzenie usunięcia meczu (S-04, `matches/index.blade.php:98-123`) i usunięcia konta (`profile/partials/delete-user-form.blade.php`). Obecna implementacja to w 100% Alpine `x-show` + ręczny focus-trap + ręczne blokowanie scrolla — nie natywny `<dialog>`. DaisyUI zwykle stosuje `<dialog>` lub checkbox-hack; migracja wymaga świadomej decyzji (zachować Alpine i tylko przestylować, czy przepisać na `<dialog>` i stracić/odtworzyć event-bus `$dispatch('open-modal', name)`).
- **Powtarzający się build trap z S-04/S-05/S-06, nigdy nie zapisany w `lessons.md`**: CSS buduje się jednorazowo (`npm run build`, nie live Vite), a build pod Node 18 cicho pomija nowe klasy Tailwinda (np. `sm:block` nigdy się nie skompilował w S-04). Trzeba używać Node 20. Przy zmianie dotykającej wszystkich 23 widoków to ryzyko dużej skali — warto to nareszcie zapisać przez `/10x-lesson`.
- **Duplikacja markupu jest już zmapowana** — te same klasy karty (`p-4 sm:p-8 bg-white shadow sm:rounded-lg`, 10 wystąpień), ten sam wrapper strony, ten sam header slot w 8 plikach, ten sam toast "Saved." w 2 plikach — to naturalne kandydatury na komponenty DaisyUI (`card`, `alert`).
- **`welcome.blade.php` (223 linie) jest jakościowo inny** niż reszta aplikacji: niedotknięty Breeze/Laravel starter z inline'owanym całym skompilowanym CSS Tailwind 4 jako fallback i dziesiątkami arbitralnych hex-kolorów (`bg-[#FDFDFC]` itd.), które nie mapują się na żadne tokeny DaisyUI. Rekomendacja z badania: potraktować jako pełną wymianę na branded landing page, nie jako przestylowanie w miejscu.
- Konwencje projektu, które redesign musi respektować: pełna polska lokalizacja (`__()` + `lang/pl.json`), wzorzec mobile-card/desktop-table dla danych tabelarycznych, `x-app-layout` na każdej stronie, NFR "w pełni używalne z przeglądarki mobilnej" i "<1s p95" dla akcji CRUD.

## Detailed Findings

### 1. Build pipeline i konfiguracja Tailwind

- `tailwind.config.js:1-21` — jeden plugin (`@tailwindcss/forms`), jedyna customizacja to `fontFamily.sans = ['Figtree', ...defaultTheme.fontFamily.sans]`. Brak customowych kolorów w configu — cała paleta kolorów w widokach to stockowy Tailwind (`gray-*`, `indigo-*`, `red-*`, `green-*`).
- `postcss.config.js` — klasyczny v3 pipeline (`tailwindcss: {}`, `autoprefixer: {}`), potwierdza że `@tailwindcss/vite@4` (`package.json:11`) nie jest realnie użyty — brak importu w `vite.config.js`, brak referencji w `resources/`.
- `vite.config.js:1-11` — standardowy `laravel-vite-plugin`, wejścia `resources/css/app.css` i `resources/js/app.js`.
- `resources/css/app.css` (3 linie) — tylko `@tailwind base/components/utilities`, zero custom CSS/`@layer components` do pogodzenia z klasami DaisyUI.
- `package.json` — `tailwindcss: ^3.1.0` (aktywna wersja), `alpinejs: ^3.4.2`, `@tailwindcss/forms: ^0.5.2`, `@tailwindcss/vite: ^4.0.0` (martwy relikt, bezpieczny do usunięcia).
- Wniosek: dodanie `daisyui` do `plugins: []` w `tailwind.config.js` to jedyna zmiana build configu potrzebna do startu; brak konfliktów z PostCSS/Vite.

### 2. Layouty (`resources/views/layouts/`)

- `layouts/app.blade.php` (37 linii) — layout uwierzytelniony: `<div class="min-h-screen bg-gray-100">` (linia 18, kandydat na `bg-base-200`), `@include('layouts.navigation')`, opcjonalny slot `$header` w pasku `bg-white shadow` (linie 22-28), `<main>{{ $slot }}</main>`.
- `layouts/guest.blade.php` (31 linii) — layout dla auth: pełnoekranowe tło `bg-gray-100` + flex centering, karta `bg-white shadow-md ... sm:rounded-lg` (linia 25) — bezpośredni kandydat na DaisyUI `card`.
- `layouts/navigation.blade.php` (118 linii) — cały pasek nawigacji ręcznie budowany Tailwind + Alpine, zero komponentów DaisyUI (`navbar`/`menu`/`dropdown`) obecnie. `x-data="{ open: false }"` (linia 1) dla mobilnego menu — wzorzec zgodny z DaisyUI `drawer` lub responsywnym `navbar` sterowanym Alpine (bez konfliktu). Dropdown ustawień (linie 32-61) zbudowany na komponencie `<x-dropdown>`.

### 3. Komponenty Blade (`resources/views/components/`) — 13 plików

Każdy to cienki wrapper Tailwind na Breeze default; migracja tych 13 plików propaguje się na całą aplikację:

| Komponent | DaisyUI target |
|---|---|
| `auth-session-status.blade.php` | `alert alert-success` |
| `danger-button.blade.php` | `btn btn-error` |
| `dropdown.blade.php` | `dropdown` (patrz §4 — wymaga decyzji o mechanizmie) |
| `dropdown-link.blade.php` | `menu`/`dropdown-content li a` |
| `input-error.blade.php` | `text-error` / `label-text-alt text-error` |
| `input-label.blade.php` | `label`/`label-text` |
| `modal.blade.php` | `modal` (patrz §4 — największe ryzyko) |
| `nav-link.blade.php` | `menu`/`tab` active state |
| `primary-button.blade.php` | `btn btn-primary`/`btn-neutral` |
| `responsive-nav-link.blade.php` | `menu` vertical item |
| `secondary-button.blade.php` | `btn btn-outline`/`btn-secondary` |
| `text-input.blade.php` | `input input-bordered` |
| `application-logo.blade.php` | bez zmian (inline SVG, klasy podaje konsument) |

### 4. Wzorce Alpine.js — inwentaryzacja i ryzyko migracji

8 plików korzysta z Alpine: `components/dropdown.blade.php`, `components/modal.blade.php`, `layouts/navigation.blade.php`, `matches/index.blade.php`, `profile/partials/delete-user-form.blade.php`, `profile/partials/update-password-form.blade.php`, `profile/partials/update-profile-information-form.blade.php`, `stats/index.blade.php`. Reszta widoków (welcome, dashboard, matches/create|edit|candidates, team/*, auth/*) jest statyczna/bez JS.

**Dwa strukturalnie różne wzorce:**

1. **Lokalne, samodzielne widgety** (dropdown, mobile nav toggle, tabs w stats, transient toasty) — proste do zachowania: logika Alpine `x-data`/`x-show` zostaje, zmieniają się tylko klasy na DaisyUI.
   - `components/dropdown.blade.php:16-30` — `x-data="{ open: false }"`, `@click.outside`, `x-show` + `x-transition:*`, inline `style="display: none;"` jako SSR fallback.
   - `stats/index.blade.php:19-52` — `x-data="{ tab: 'overall' }"`, `:class` dla aktywnego taba, `x-show="tab === '...'"` na 3 panelach — DaisyUI `tabs`/`tabs-bordered` to czysty CSS na `<a>`/`<button>`, więc logika przełączania zostaje, restylowane są tylko klasy przycisków taba.
   - `layouts/navigation.blade.php:66-77` — `:class` toggle `hidden`/`block`/`inline-flex` dla hamburgera i mobile menu — DaisyUI nie ma dedykowanego prymitywu na to, wzorzec zostaje bez zmian.
   - `profile/partials/update-password-form.blade.php:39-42` i `profile/partials/update-profile-information-form.blade.php:33-36` — identyczny toast "Saved." (`x-data="{ show: true }"`, `x-init="setTimeout(() => show = false, 2000)"`) zduplikowany 1:1 w obu plikach — kandydat na wspólny komponent `alert`/`toast`.

2. **Globalny modal event-bus** (`components/modal.blade.php`) — **najwyższe ryzyko w całym zakresie zmiany**.
   - Mechanizm: root `x-data` z boolean `show` + metody focus-trapu (`focusables()`, `nextFocusable()`...), otwierany/zamykany przez **globalne eventy window**: `x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"`, dowolny komponent wywołuje `$dispatch('open-modal', 'name')` bez bezpośredniego sprzężenia rodzic/dziecko.
   - `x-on:keydown.escape.window` zamyka na Escape; ręczny focus-trap przez `x-on:keydown.tab.prevent`/`shift.tab.prevent`.
   - `x-init` + `$watch('show', ...)` ręcznie przełącza `document.body.classList` (`overflow-y-hidden`) — DaisyUI/natywny `<dialog>` robi to za darmo przez `showModal()`.
   - Widoczność to `x-show` na 3 zagnieżdżonych warstwach + inline `style="display: ..."` fallback + transition classes — nie natywny `<dialog>`, nie checkbox-hack.
   - Dwa konkretne wywołania współdzielą ten sam prymityw: usuwanie meczu (`matches/index.blade.php:98-123`, z S-04, N dynamicznych modali w `@foreach` po `$match->id`, wyzwalanych z `matches/index.blade.php:52-53` mobile i `:86-87` desktop) oraz usuwanie konta (`profile/partials/delete-user-form.blade.php:17-54`).
   - **Decyzja do podjęcia w planie**: (a) zachować Alpine `x-data`/`$dispatch` event-bus i tylko przestylować klasy na `modal-open`/DaisyUI skin, albo (b) przepisać na natywny `<dialog>` i zastąpić `$dispatch('open-modal', name)` wywołaniami `document.getElementById(id).showModal()` — traci się cross-component event-bus, ale zyskuje darmowy focus-trap/Escape/backdrop z przeglądarki. Opcja (a) jest bezpieczniejsza behawioralnie (mniej do przepisania, mniej ryzyka regresji na N dynamicznych modali per mecz).
   - `resources/js/app.js` (7 linii) — tylko `Alpine.start()`, brak customowych pluginów/store'ów — zero konfliktu z DaisyUI (który nie ma własnego JS runtime).

### 5. Pełna inwentaryzacja 23 widoków wg obszaru

**auth/** (login, register, confirm-password) — proste, 1-4 pola przez `x-text-input`. Wyjątki wymagające ręcznej klasy DaisyUI: surowy checkbox w `auth/login.blade.php:30` (→ `checkbox`), link-jako-przycisk w `auth/register.blade.php:43` (→ `btn btn-link`/`link`).

**dashboard.blade.php** (19 linii) — najprostszy plik, jedna karta, ale wariant karty (`bg-white overflow-hidden shadow-sm sm:rounded-lg`) różni się od dominującego wzorca `p-4 sm:p-8 bg-white shadow sm:rounded-lg` używanego wszędzie indziej — istniejąca niespójność do znormalizowania przy okazji redesignu.

**matches/** — najbardziej złożony obszar:
- `matches/index.blade.php` (128 linii) — jedyna prawdziwa `<table>` w całej aplikacji (linie 62-95) sparowana z równoległym mobile-card view (36-58); 3 warianty alertu sukcesu (`p-4 bg-green-50 text-green-700 rounded-lg`, linie 11/15/19 — duplikat DaisyUI `alert alert-success`); przycisk-jako-link z ręcznym stylem (linia 27); N dynamicznych modali usuwania (S-04). Warto w planie rozważyć, czy DaisyUI `table` + `overflow-x-auto` pozwala zwinąć osobny mobile-card markup do jednego źródła.
- `matches/create.blade.php` (70 linii), `matches/edit.blade.php` (55 linii), `matches/candidates.blade.php` (52 linii) — surowe, niestylowane radio buttony (venue w create.blade.php:31,35; kandydat lokalizacji w candidates.blade.php:33) → `radio`.
- Zduplikowana logika ternary `venue === 'home' ? 'Dom' : 'Wyjazd'` w 3 miejscach (`index.blade.php:44,78`, `edit.blade.php:13`) — kandydat na `badge badge-outline`.

**profile/** — `edit.blade.php` to trywialna powłoka 3 kart wokół `@include`; `delete-user-form.blade.php` dzieli wzorzec modala z S-04 (patrz §4); toast "Saved." zduplikowany w 2 plikach (patrz §4).

**stats/** — `stats/index.blade.php` (57 linii, taby Alpine, patrz §4); `stats/partials/stats-block.blade.php` (52 linii) — jedyne miejsce, które **nie** da się przestylować samą podmianą klas: wykres słupkowy z kolorami zakodowanymi jako surowy hex w PHP (`'#0ca30c'`/`'#898781'`/`'#d03b3b'`, linie 8-10) i wysokościami liczonymi inline (`style="height: {{ ... }}px"`, linia 45) — wymaga decyzji, czy zostają surowe hexy, czy migrują na CSS-variable theme colors DaisyUI (`--color-success`/`-neutral`/`-error`).

**team/** — `team/edit.blade.php`, `team/candidates.blade.php` — te same wzorce co matches/ (surowe radio w candidates.blade.php:30, hint text).

**welcome.blade.php** (223 linii) — jakościowo inny niż reszta: niedotknięty Breeze starter z inline'owanym skompilowanym CSS Tailwind 4 jako fallback (linie 12-18) i dziesiątkami arbitralnych hex-utilities (`bg-[#FDFDFC]`, `text-[#1b1b18]`, `text-[#F53003]` itd.) plus dekoracyjne SVG — żaden z tych wzorców nie powtarza się nigdzie indziej w aplikacji. Rekomendacja: pełna wymiana na branded landing page zamiast przestylowania w miejscu.

### 6. Kandydaci do wspólnych komponentów (duplikacja między plikami)

1. Karta `p-4 sm:p-8 bg-white shadow sm:rounded-lg` — 10 wystąpień (matches/candidates|create|edit|index, profile/edit ×3, stats/index, team/candidates|edit).
2. Wrapper strony `py-12` > `max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6` — identyczny we wszystkich 8 konsumentach `x-app-layout`.
3. Header slot `<x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">` — bajt-identyczny (poza tytułem) we wszystkich 8 stronach.
4. Nagłówek sekcji `<header><h2 class="text-lg font-medium text-gray-900">...<p class="mt-1 text-sm text-gray-600">` — 6 wystąpień.
5. Toast "Saved." — 2 wystąpienia (patrz §4).
6. Dispatch modala otwórz/zamknij — 2 wystąpienia (patrz §4).
7. Hint text `<p class="mt-1 text-sm text-gray-500">` pod polem — 2 wystąpienia.
8. Ternary/logika venue — 3 wystąpienia (matches/index.blade.php ×2, matches/edit.blade.php).

## Code References

- `tailwind.config.js:1-21` — jedyny plugin i customizacja fontu do zachowania w motywie DaisyUI
- `postcss.config.js` — potwierdza pipeline v3, brak `@tailwindcss/vite`
- `package.json:11` — martwy relikt `@tailwindcss/vite@4`
- `resources/css/app.css` — tylko dyrektywy `@tailwind`, zero custom CSS
- `resources/views/layouts/app.blade.php:18` — `bg-gray-100` do zmapowania na `bg-base-200`
- `resources/views/layouts/guest.blade.php:25` — karta auth, kandydat `card`
- `resources/views/layouts/navigation.blade.php:1,32-61,66-77` — Alpine mobile toggle + dropdown ustawień
- `resources/views/components/modal.blade.php` — globalny event-bus modala, najwyższe ryzyko migracji
- `resources/views/components/dropdown.blade.php:16-30` — lokalny wzorzec dropdown, niższe ryzyko
- `resources/views/matches/index.blade.php:11,15,19` — 3 warianty alertu sukcesu
- `resources/views/matches/index.blade.php:27` — przycisk-jako-link z ręcznym stylem
- `resources/views/matches/index.blade.php:36-58,61-95` — dual mobile/desktop markup, jedyna `<table>`
- `resources/views/matches/index.blade.php:98-123` — N dynamicznych modali usuwania (S-04)
- `resources/views/profile/partials/delete-user-form.blade.php:17-54` — modal usuwania konta, ten sam prymityw co S-04
- `resources/views/profile/partials/update-password-form.blade.php:38-44` i `update-profile-information-form.blade.php:32-38` — zduplikowany toast
- `resources/views/stats/partials/stats-block.blade.php:8-10,39,45` — surowe hexy i inline style w wykresie słupkowym
- `resources/views/stats/index.blade.php:19-52` — Alpine tabs
- `resources/views/auth/login.blade.php:30` — surowy checkbox
- `resources/views/matches/create.blade.php:31,35`, `matches/candidates.blade.php:33`, `team/candidates.blade.php:30` — surowe radio buttony
- `resources/views/welcome.blade.php:12-18,20` — inline compiled-CSS fallback i arbitralne hex-utilities

## Architecture Insights

- Stylowanie aplikacji jest **wysoko scentralizowane w 13 komponentach + 3 layoutach** — to sprawia, że przebudowa jest bardziej "zmień fundament, propaguj" niż "przepisz 23 pliki od zera". Większość z 17 widoków w `auth/dashboard/matches/profile/stats/team` niesie tylko wrappery/nagłówki/spacing, nie własne stylowanie kontrolek formularzy.
- Brak customowej palety kolorów w `tailwind.config.js` oznacza, że nie ma "brand tokenu" do migracji — obecna de facto paleta (`indigo` jako akcent, `gray` jako neutral, `red`/`green` jako error/success) to rozsądny seed dla customowego motywu DaisyUI zamiast wybierania gotowego motywu z biblioteki.
- Jedyne miejsce z logiką niesprowadzalną do podmiany klas to wykres słupkowy w `stats/partials/stats-block.blade.php` — kolory i wysokości są liczone w PHP i wstrzykiwane jako inline style, nie klasy Tailwind.
- `welcome.blade.php` żyje poza konwencjami reszty aplikacji (arbitralne hexy, brak `x-app-layout`, brak lokalizacji `__()`) — to jedyny plik, gdzie "migracja" praktycznie oznacza "napisz od nowa".

## Historical Context (from prior changes)

- **S-04** = `context/archive/2026-07-26-edit-delete-match/` — źródło wzorca modala usuwania (skopiowany z Breeze `delete-user-form.blade.php`), wzorca mobile-card/desktop-table split, i **powtarzającej się pułapki buildu**: build CSS pod Node 18 cicho pomija nowe klasy Tailwind (np. `sm:block` nigdy się nie skompilował) — trzeba `PATH="$HOME/.nvm/versions/node/v20.20.2/bin:$PATH" npm run build`. Flagowane do zapisania w `lessons.md` już w impl-review S-04, ale **nigdy tam nie trafiło** (potwierdzone też przez `context/changes/testing-quality-gates-wiring/research.md:33,86`) — `lessons.md` ma dziś tylko 2 niepowiązane wpisy. Przy zmianie dotykającej wszystkich 23 widoków ryzyko materializuje się na dużo większą skalę niż w S-04/S-05/S-06 — warto to nareszcie zapisać przez `/10x-lesson` jako część tej zmiany.
- **S-05** = `context/archive/2026-07-26-stats-dashboard/` — ustalił idiom karty `p-4 sm:p-8 bg-white shadow sm:rounded-lg`, wykres W/D/L jako czysty HTML/CSS bez biblioteki JS z hex-kolorami niezależnymi od trybu (`--bar-win`/`--bar-loss`/`--bar-draw`), unikanie polskiej fleksji w tekstach ("3× W" zamiast "3 zwycięstwa z rzędu"), ten sam Node 20 build checklist.
- **S-06** = `context/archive/2026-07-26-home-away-stats-split/` — **bezpośredni precedens cytowany w `change.md` tej zmiany** dla decyzji "jeden research.md + jeden plan.md zamiast wielu folderów zmian": *"Backend i UI są na tyle sprzężone... że rozbijanie na osobne fazy byłoby sztuczne — potwierdzone z użytkownikiem przy planowaniu."* Ustalił też wzorzec Alpine tabs, naśladując istniejący `dropdown.blade.php` zamiast wymyślać nowy wzorzec interakcji, oraz ekstrakcję powtarzającego się markupu do partiala (`stats/partials/stats-block.blade.php`) sparametryzowanego przez `@include`.
- **S-01** (`context/archive/2026-07-19-user-registration-login/`) — bootstrap całego stacku UI przez `laravel/breeze --install blade`; źródło `x-app-layout`, `layouts/*`, `components/*`, Alpine. Ustalił **niepodlegającą negocjacji konwencję lokalizacji**: każdy widoczny string przez `__()`, tłumaczenia w `lang/pl.json`/`lang/pl/*.php`.
- `context/archive/2026-07-19-team-and-home-profile/` — wzmacnia konwencję `x-app-layout` + `__()`, wzorzec duplikowania linków nawigacji w blokach desktop i mobile w `layouts/navigation.blade.php`.
- `context/changes/testing-quality-gates-wiring/` — dodał bramkę CI grepującą skompilowany CSS pod kątem klas faktycznie użytych w Blade (żeby łapać S-04-owy fail mode na poziomie CI, nie tylko lokalnie); zwraca uwagę, że glob `content` w `tailwind.config.js` łapie też vendor pagination Blade z Laravela, więc część klas (np. `sm:hidden`) zawsze pojawi się w skompilowanym CSS niezależnie od użycia w aplikacji — pułapka fałszywie pozytywna dla przyszłych narzędzi weryfikujących build.
- **Ograniczenia z `context/foundation/prd.md`** (linie ~151-155): akcje CRUD muszą być odczuwalnie natychmiastowe (**<1s p95**), aplikacja musi być **w pełni używalna z przeglądarki mobilnej** (stąd manualny test wąskiego viewportu w każdej dotychczasowej zmianie UI), ścisła izolacja danych per użytkownik. Brak explicit wymagań WCAG/accessibility w PRD.
- `context/foundation/lessons.md` — obecnie tylko 2 wpisy, żaden UI-specyficzny; pułapka Node 18/Node 20 nigdy nie została tam zapisana mimo rekomendacji z S-04 impl-review.

## Related Research

Brak wcześniejszych `research.md` dotyczących UI/frontendu — S-04/S-05/S-06/S-01 zostały zaplanowane bezpośrednio z `plan.md` bez osobnej fazy `/10x-research` (mniejsze zmiany, niższe ryzyko architektoniczne w momencie planowania).

## Open Questions

1. Mechanizm modala: zachować Alpine `x-data`/`$dispatch('open-modal', name)` event-bus (bezpieczniejsze, mniej przepisywania) czy migrować na natywny `<dialog>` (czystsze pod DaisyUI, ale wymaga przepisania trigger-side kodu w `matches/index.blade.php` i `delete-user-form.blade.php`)? → do rozstrzygnięcia w `/10x-plan`.
2. Wykres słupkowy w `stats/partials/stats-block.blade.php`: zostają surowe hexy PHP, czy migrują na CSS custom properties motywu DaisyUI (`--color-success` itd.), żeby kolory reagowały na zmianę motywu/dark mode?
3. `welcome.blade.php`: pełna wymiana na branded landing page (rekomendowane) czy przestylowanie istniejącego markupu w miejscu? Ma to wpływ na wielkość fazy w planie.
4. Czy `matches/index.blade.php` może skonsolidować dual mobile-card/desktop-table markup do jednego źródła po przejściu na DaisyUI `table` + `overflow-x-auto`, czy responsywne wymaganie (NFR mobile-first) wymusza zachowanie osobnych bloków?
5. Czy ta zmiana ma też zamknąć dług `lessons.md` (zapisać pułapkę Node 18/20) jako podfazę, skoro dotyczy bezpośrednio bramki jakości, którą ta zmiana będzie wielokrotnie uruchamiać?
