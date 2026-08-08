# UI redesign z DaisyUI — Plan implementacji

## Przegląd

Przebudowujemy cały UI aplikacji (23 pliki Blade: layouty, komponenty, auth, dashboard, mecze, drużyna, statystyki, strona główna) na DaisyUI 4.x jako plugin Tailwind 3.4.19, z nową custom paletą kolorów (biały/niebieski/czerwony) zamiast dzisiejszego domyślnego indigo/gray z Breeze. Jedna zmiana, jeden plan, jeden merge do mastera na końcu — zgodnie z decyzją potwierdzoną z użytkownikiem przed startem tej zmiany (precedens S-06).

## Analiza stanu obecnego

Stylowanie aplikacji jest wysoko scentralizowane: 13 komponentów Blade (`resources/views/components/`) + 3 layouty (`resources/views/layouts/`) niosą niemal całe stylowanie formularzy, przycisków i nawigacji. Reszta z 17 widoków w `auth/`, `dashboard`, `matches/`, `profile/`, `stats/`, `team/` w większości tylko konsumuje te komponenty — restyling fundamentu propaguje się na całą aplikację "za darmo". `welcome.blade.php` jest wyjątkiem: niedotknięty Breeze/Laravel starter z inline'owanym skompilowanym CSS Tailwind 4 jako fallback i dziesiątkami arbitralnych hex-utilities, niepowiązany z resztą aplikacji.

Build pipeline (Tailwind 3.4.19 przez klasyczny `postcss.config.js`) jest gotowy na DaisyUI 4.x bez migracji — dodanie pluginu to jedna zmiana w `tailwind.config.js`. Alpine.js jest jedynym JS runtime i zostaje bez zmian architektonicznych; DaisyUI (zero własnego JS) nie koliduje.

Pełne badanie: `context/changes/ui-redesign-daisyui/research.md`.

### Kluczowe odkrycia:

- 13 komponentów Blade → tabela mapowania na klasy DaisyUI już ustalona w badaniu (`research.md` §3).
- Globalny modal event-bus (`components/modal.blade.php`, `x-on:open-modal.window`/`$dispatch('open-modal', name)`) współdzielony przez usuwanie meczu (S-04, `matches/index.blade.php:98-123`) i usuwanie konta (`profile/partials/delete-user-form.blade.php:17-54`) — zostaje architektonicznie bez zmian, restylujemy tylko warstwę wizualną.
- **Gate deploy CI się złamie w Fazie 5**: `.github/workflows/deploy.yml` (`Verify compiled CSS contains known responsive classes`) sprawdza cztery zaszyte na sztywno klasy: `sm:block`, `sm:grid-cols-4`, `lg:block`, `lg:flex-row`. Dwie z nich (`lg:block`, `lg:flex-row`) żyją wyłącznie w `welcome.blade.php:51,220`, które w Fazie 5 zostaje w całości zastąpione — gate przestanie znajdować te klasy w skompilowanym CSS i zablokuje deploy, dopóki lista nie zostanie zaktualizowana.
- `tests/Feature/StatsTest.php:271,335` zawiera strukturalną asercję `assertSame(1, substr_count($response->getContent(), 'border-red-200'))` powiązaną z kartą "Pechowy kibic" (`stats/partials/stats-block.blade.php:30`) — musi zostać zaktualizowana w tej samej fazie, w której zmieniamy klasę tej karty (Faza 4).
- `tests/Feature/StatsTest.php` ma też asercje na dokładne sąsiedztwo tagów (`>100%<`, `>1× W<` itd.) — przetrwają zmianę klas, ale złamią się, jeśli restyling doda dodatkowy element inline wokół tekstu procentowego/streaku.
- CI (`deploy.yml`, `review.yml`) używa `node-version: lts/*` — pułapka Node 18/20 (patrz niżej) dotyczy wyłącznie lokalnego builda deweloperskiego, nie CI.
- `routes/web.php:9-11` — `/` zwraca `view('welcome')` bez żadnej logiki; żadna zmiana routingu nie jest potrzebna w Fazie 5.
- Konwencja lokalizacji: nowe stringi w Blade idą przez `__('Polski tekst')` — kod źródłowy jest już po polsku, `lang/pl.json` służy do nadpisań, nie jest wymagany dla nowych stringów, których treść = klucz (potwierdzone wzorcem w `matches/index.blade.php`).

## Pożądany stan końcowy

Cała aplikacja (23 pliki) używa spójnego motywu DaisyUI 4.x o nazwie `wyjazdowicz` (biały/niebieski/czerwony), zdefiniowanego jako custom theme w `tailwind.config.js`. Wszystkie 13 komponentów i 3 layouty korzystają z klas DaisyUI (`btn`, `input`, `modal`, `dropdown`, `card`, `alert`, `badge`, `radio`, `checkbox`, `tabs`, `stat`). Strona główna to branded landing page zamiast domyślnego startera Laravela. Modal event-bus i logika Alpine (dropdown, taby statystyk, mobile nav, toasty) działają bez zmian architektonicznych. Pułapka builda Node 18/20 jest zapisana w `context/foundation/lessons.md`. Gate CI `deploy.yml` odzwierciedla nową rzeczywistość skompilowanego CSS. Cały istniejący test suite (`php artisan test`) przechodzi. Weryfikacja: pełny `npm run build` pod Node 20 + `php artisan test` zielone, ręczny przegląd mobile-viewport na matches/stats/dashboard/landing, jeden merge do mastera.

## Czego NIE robimy

- Tryb ciemny (dark mode) — poza zakresem tej zmiany.
- Migracja kolorów wykresu W/D/L (`app/Services/StatsCalculator.php`, hexy `#0ca30c`/`#898781`/`#d03b3b`) na zmienne motywu DaisyUI — zostają nietknięte (decyzja S-05 o kolorach niezależnych od trybu).
- Konsolidacja dual mobile-card/desktop-table markupu w `matches/index.blade.php` do jednej tabeli z `overflow-x-auto` — zostaje osobny markup mobile/desktop.
- Przepisanie modala na natywny `<dialog>` — zostaje Alpine `x-data`/`$dispatch` event-bus, restylowana tylko warstwa wizualna.
- Migracja z DaisyUI 4.x na 5.x / Tailwind 3 → 4 — poza zakresem (potwierdzone w `change.md`).
- Pełna strona marketingowa z feature-highlightami i zrzutami ekranu — landing page to minimalny branded hero.
- Przyrostowy merge/deploy między fazami — jeden merge na koniec, po Fazie 6.
- Wyodrębnianie nowego współdzielonego komponentu Blade dla zduplikowanego toastu "Saved." (`update-password-form.blade.php`/`update-profile-information-form.blade.php`) — tylko 2 wystąpienia po kilka linii, restylowane osobno bez nowej abstrakcji.

## Podejście do implementacji

Sześć faz w kolejności zależności: najpierw fundament (plugin + motyw + lesson), potem współdzielone komponenty/layout (propagują się na całą aplikację), potem widoki wg priorytetu ustalonego z użytkownikiem (core CRUD przed statystykami przed landing page), na końcu regresja i jeden merge. Każda faza aktualizuje powiązane testy strukturalne w tej samej fazie, w której zmienia odpowiadający markup — nie na końcu w jednej fazie "napraw wszystko".

## Krytyczne szczegóły implementacji

- **Kontrakt eventów modala musi zostać bit-identyczny**: `matches/index.blade.php:52-53,86-87` i `profile/partials/delete-user-form.blade.php` wywołują `$dispatch('open-modal', '<name>')` i `$dispatch('close')` na konkretne nazwy stringów. Restyling `components/modal.blade.php` w Fazie 2 nie może zmienić nazw tych eventów ani sygnatury `x-on:open-modal.window`/`x-on:close-modal.window` — Faza 3 (matches/profile) zależy od tego kontraktu pozostając niezmienionym.
- **Gate `deploy.yml` na pewno się złamie po Fazie 5** (patrz Kluczowe odkrycia) — nie jest to regresja do naprawienia w panice, to oczekiwany, udokumentowany efekt uboczny zastąpienia `welcome.blade.php`. Faza 6 jawnie aktualizuje listę klas w gate.
- **`StatsTest.php` `substr_count` na dokładnym sąsiedztwie tagów** (`>100%<`, `>1× W<`) — Faza 4 nie może owinąć tekstu procentowego/streaku w dodatkowy element (np. `<span>` do stylowania), bo to złamie te asercje niezależnie od nazwy klasy. Jeśli DaisyUI wymaga dodatkowego wrappera dla stylu, wrapper musi być na zewnątrz najbliższego taga zawierającego tekst, nie między nim a tekstem.

## Faza 1: Fundament

### Przegląd

Instalacja DaisyUI 4.x jako pluginu Tailwind, zdefiniowanie custom motywu kolorystycznego, usunięcie martwego relictu, zapisanie pułapki build Node 18/20 do `lessons.md`. Zero zmian widocznych w UI — czysto konfiguracyjna faza, na której opierają się wszystkie kolejne.

### Wymagane zmiany:

#### 1. Instalacja DaisyUI i usunięcie martwego pakietu

**Plik**: `package.json`

**Cel**: Dodać `daisyui` (v4.x, kompatybilny z Tailwind 3) jako dev dependency; usunąć nieużywany `@tailwindcss/vite@4` (potwierdzony w badaniu jako martwy relikt bez żadnych referencji w `vite.config.js`/`resources/`).

**Kontrakt**: `npm install -D daisyui@^4` + `npm uninstall @tailwindcss/vite`. Po zmianie `npm install` musi przejść czysto i `node_modules/daisyui` musi istnieć.

#### 2. Custom motyw DaisyUI w konfiguracji Tailwind

**Plik**: `tailwind.config.js`

**Cel**: Zarejestrować plugin `daisyui` i zdefiniować jeden custom motyw o nazwie `wyjazdowicz`, budując paletę wokół trzech dominujących kolorów ustalonych z użytkownikiem (biały/niebieski/czerwony), z zachowaniem istniejącej customizacji fontu (`Figtree`) i bez naruszania semantyki `success`/`warning` używanej gdzie indziej (np. przez wykres statystyk, który zostaje nietknięty).

**Kontrakt**: `plugins: [forms, daisyui]` + blok `daisyui: { themes: [...] }` z jednym motywem. Dokładne wartości tokenów (nietrywialna, jednorazowa decyzja projektowa — stąd fragment kodu):

```js
daisyui: {
  themes: [
    {
      wyjazdowicz: {
        'primary': '#2563EB',
        'primary-content': '#FFFFFF',
        'secondary': '#1E3A8A',
        'secondary-content': '#FFFFFF',
        'accent': '#DC2626',
        'accent-content': '#FFFFFF',
        'neutral': '#1F2937',
        'neutral-content': '#F9FAFB',
        'base-100': '#FFFFFF',
        'base-200': '#F3F4F6',
        'base-300': '#E5E7EB',
        'base-content': '#111827',
        'info': '#3B82F6',
        'success': '#16A34A',
        'warning': '#D97706',
        'error': '#DC2626',
      },
    },
  ],
},
```

`primary` (niebieski) niesie główne akcje/linki/nawigację; `accent` i `error` celowo dzielą tę samą czerwień — czerwony jest jednocześnie kolorem marki i semantycznym kolorem niebezpieczeństwa/usuwania, co wzmacnia się nawzajem zamiast kolidować. `base-100` biały spełnia wymóg trzeciego dominującego koloru wprost jako tło aplikacji.

#### 3. ~~Zapisanie pułapki build Node 18/20 do lessons.md~~ — pominięte po weryfikacji

**Zweryfikowano podczas implementacji Fazy 1**: `.nvmrc` (zawartość `20`) zostało dodane 2026-08-01 w zmianie `ci-cd-code-review`, **po** S-04/S-05/S-06 (2026-07-26), gdzie pułapka faktycznie uderzyła. nvm honoruje `.nvmrc` automatycznie — `node -v` w tej sesji od razu pokazuje `v20.20.2` bez ręcznej zmiany. Realne ryzyko lokalnego builda pod Node 18 jest więc dziś w dużej mierze rozwiązane; to nie jest już żywa, powtarzająca się reguła warta wpisu w `lessons.md`, tylko rozwiązany incydent historyczny. Potwierdzone z użytkownikiem — krok pominięty.

Drobna, osobna niespójność znaleziona przy okazji: `review.yml:45` czyta `node-version-file: ".nvmrc"`, ale `deploy.yml:29,55` ma zaszyte na sztywno `node-version: lts/*` zamiast też czytać `.nvmrc`. Nie blokuje tej zmiany (CI i tak używa świeżego LTS) — warto ujednolicić przy okazji przyszłej zmiany dotykającej CI, nie w tej.

### Kryteria sukcesu:

#### Automatyczne

- `npm install` przechodzi czysto po zmianach w `package.json`
- `PATH="$HOME/.nvm/versions/node/v20.20.2/bin:$PATH" npm run build` kończy się sukcesem
- `grep -o '\.btn{' public/build/assets/*.css` (lub analogiczny znany selektor DaisyUI) potwierdza obecność klas DaisyUI w skompilowanym CSS
- `php artisan test` przechodzi bez regresji (żaden widok jeszcze nie zmieniony w tej fazie)
- `context/foundation/lessons.md` zawiera nowy wpis o pułapce Node 18/20

#### Ręczne

- `npm run dev` startuje bez błędów konfiguracji Tailwind/PostCSS

---

## Faza 2: Wspólne komponenty i layout

### Przegląd

Migracja 13 komponentów Blade i 3 layoutów na klasy DaisyUI + nowy motyw `wyjazdowicz`. Ta faza propaguje się na całą resztę aplikacji, bo każdy formularz i każda strona konsumuje te komponenty.

### Wymagane zmiany:

#### 1. Aktywacja motywu na poziomie HTML

**Pliki**: `resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`

**Cel**: Ustawić `data-theme="wyjazdowicz"` na elemencie `<html>` w obu layoutach, żeby motyw z Fazy 1 faktycznie się zastosował.

**Kontrakt**: Atrybut `data-theme` na istniejącym tagu `<html>`, bez zmiany reszty struktury dokumentu.

#### 2. Komponenty przycisków, inputów, alertów, etykiet

**Pliki**: `resources/views/components/primary-button.blade.php`, `secondary-button.blade.php`, `danger-button.blade.php`, `text-input.blade.php`, `input-label.blade.php`, `input-error.blade.php`, `auth-session-status.blade.php`

**Cel**: Zastąpić ręczne klasy Tailwind odpowiednikami DaisyUI wg mapowania z badania (`btn btn-primary`, `btn btn-outline`/`btn-secondary`, `btn btn-error`, `input input-bordered`, `label`/`label-text`, `text-error`, `alert alert-success`).

**Kontrakt**: Każdy komponent zachowuje swój obecny publiczny interfejs (nazwa komponentu, sloty, propsy typu `:messages`, `disabled`) — zmieniają się wyłącznie klasy CSS w atrybutach `class`/`{{ $attributes->merge(...) }}`.

#### 3. Dropdown i nawigacja

**Pliki**: `resources/views/components/dropdown.blade.php`, `dropdown-link.blade.php`, `nav-link.blade.php`, `responsive-nav-link.blade.php`, `resources/views/layouts/navigation.blade.php`

**Cel**: Przestylować na klasy DaisyUI `dropdown`/`dropdown-content`/`menu` zachowując dokładnie obecną logikę Alpine (`x-data="{ open: false }"`, `@click.outside`, `@close.stop`, `x-show` + `x-transition`) — zero zmian w zachowaniu, tylko warstwa wizualna. Pasek nawigacji (`navigation.blade.php`) dostaje tło/kolory z nowego motywu; mobile toggle (`x-data="{ open: false }"`, linie 66-77) zostaje bez zmian strukturalnych.

**Kontrakt**: Nazwy zmiennych Alpine (`open`) i eventy (`@click.outside`, `@close.stop`) niezmienione; zmieniają się klasy pozycjonowania/skórki na `dropdown-content menu`.

#### 4. Modal — restyling przy zachowaniu event-busu

**Plik**: `resources/views/components/modal.blade.php`

**Cel**: Przestylować trzy zagnieżdżone warstwy (`x-show`) na wygląd DaisyUI `modal`/`modal-box`/`modal-backdrop`, zachowując w 100% obecną logikę Alpine: `x-data` z `show`/`focusables()`, `x-on:open-modal.window`, `x-on:close-modal.window`, `x-on:close.stop`, `x-on:keydown.escape.window`, ręczny focus-trap (`x-on:keydown.tab.prevent`/`shift.tab.prevent`), oraz `document.body.classList` toggle. Patrz sekcja "Krytyczne szczegóły implementacji" — nazwy eventów muszą pozostać bit-identyczne, bo Faza 3 zależy od tego kontraktu w dwóch różnych plikach.

**Kontrakt**: Publiczne API komponentu (`:name`, `focusable` prop, `$dispatch('open-modal', name)`/`$dispatch('close')`) niezmienione. Zmieniają się wyłącznie klasy wizualne trzech `x-show` warstw i transition classes.

#### 5. Karta w layout guest

**Plik**: `resources/views/layouts/guest.blade.php`

**Cel**: Przestylować kartę logowania/rejestracji (`bg-white shadow-md ... sm:rounded-lg`) na DaisyUI `card`.

**Kontrakt**: Zachowuje centrowanie flex i strukturę sloty `{{ $slot }}`.

### Kryteria sukcesu:

#### Automatyczne

- `php artisan test` przechodzi w całości (w szczególności `tests/Feature/Auth/*`, `ProfileTest.php` — korzysta z modala usuwania konta)
- Build pod Node 20 przechodzi

#### Ręczne

- Wizualna weryfikacja paska nawigacji (desktop + mobile hamburger), dropdownu ustawień (otwórz/zamknij/klik-poza), stron logowania/rejestracji, modala usuwania konta (otwórz z profilu, Escape zamyka, Cancel zamyka, focus-trap działa) na desktop i wąskim viewporcie mobilnym

---

## Faza 3: Core CRUD widoki (must-have)

### Przegląd

Restyling widoków auth, dashboard, mecze i drużyna — ekranów używanych najczęściej, priorytetowych względem statystyk i landing page zgodnie z ustaleniem z użytkownikiem. Aktualizacja powiązanych testów strukturalnych w tej samej fazie.

### Wymagane zmiany:

#### 1. Auth — surowe kontrolki

**Pliki**: `resources/views/auth/login.blade.php`, `resources/views/auth/register.blade.php`

**Cel**: Przestylować surowy checkbox "remember me" (`login.blade.php:30`) na DaisyUI `checkbox`; przestylować link-jako-przycisk (`register.blade.php:43`) na `btn btn-link`.

**Kontrakt**: Zachowane atrybuty `name`/`type`/`required` na inputach; zmieniają się wyłącznie klasy.

#### 2. Dashboard — normalizacja karty

**Plik**: `resources/views/dashboard.blade.php`

**Cel**: Ujednolicić wariant karty (`bg-white overflow-hidden shadow-sm sm:rounded-lg`) z resztą aplikacji, restylując na DaisyUI `card` tak samo jak pozostałe strony.

**Kontrakt**: Struktura nagłówka i treści karty niezmieniona.

#### 3. Lista meczów — alerty, przycisk, badge, modale

**Plik**: `resources/views/matches/index.blade.php`

**Cel**: Przestylować trzy warianty alertu sukcesu (linie 11/15/19) na `alert alert-success`; przycisk-jako-link (linia 27) na `btn btn-primary`; ternary venue (linie 44, 78) na `badge badge-outline`/`badge-primary` (dom vs wyjazd — dwa warianty koloru dla czytelności). Zachować dokładnie obecny podział mobile-card (`sm:hidden`, linie 36-58) / desktop-table (`hidden sm:block`, linie 61-95) — tylko klasy wewnątrz obu bloków, nie struktura. N dynamicznych modali usuwania (linie 98-123) dziedziczy nowy skin modala z Fazy 2 bez dodatkowych zmian.

**Kontrakt**: Zachowane atrybuty `x-on:click.prevent="$dispatch('open-modal', 'confirm-match-deletion-{{ $match->id }}')"` bit-identyczne z tym, co konsumuje `components/modal.blade.php` po Fazie 2.

#### 4. Formularze meczów i drużyny — surowe radio

**Pliki**: `resources/views/matches/create.blade.php`, `resources/views/matches/edit.blade.php`, `resources/views/matches/candidates.blade.php`, `resources/views/team/edit.blade.php`, `resources/views/team/candidates.blade.php`

**Cel**: Przestylować surowe radio buttony (venue w `create.blade.php:31,35`, kandydat lokalizacji w `candidates.blade.php:33`/`team/candidates.blade.php:30`) na DaisyUI `radio`. Ujednolicić hint text (`create.blade.php:45`, `team/edit.blade.php:36`) i nagłówki sekcji na wspólny wzorzec. Zastosować `badge` dla ternary venue w `matches/edit.blade.php:13`, spójnie z Fazą 3.3.

**Kontrakt**: Zachowane `name`/`value`/`@checked(...)`/`required` na inputach radio.

#### 5. Profil

**Pliki**: `resources/views/profile/edit.blade.php`, `resources/views/profile/partials/delete-user-form.blade.php`, `resources/views/profile/partials/update-password-form.blade.php`, `resources/views/profile/partials/update-profile-information-form.blade.php`

**Cel**: Ujednolicić trzy karty w `edit.blade.php` na DaisyUI `card`. Przestylować toast "Saved." w obu partiale (zachowując identyczną logikę Alpine `x-data="{ show: true }"`/`x-init="setTimeout(...)"` w obu plikach — bez wyodrębniania nowego komponentu, patrz "Czego NIE robimy"). Modal usuwania konta dziedziczy skin z Fazy 2.

**Kontrakt**: Nazwana paczka błędów `$errors->userDeletion->get('password')` i `$errors->updatePassword->get(...)` pozostają nietknięte.

#### 6. Weryfikacja i aktualizacja testów strukturalnych

**Cel**: Uruchomić `tests/Feature/GameMatchTest.php`, `tests/Feature/TeamTest.php`, `tests/Feature/ProfileTest.php`, `tests/Feature/Auth/*`, `tests/Feature/OwnershipContractTest.php` po każdej grupie zmian z tej fazy; zaktualizować dowolne asercje, które okażą się powiązane z konkretnymi klasami CSS zamiast tekstu/routingu (badanie wstępne pokazało, że `GameMatchTest.php` asertuje głównie na tekst i URL-e route'ów, nie klasy — ryzyko niskie, ale wymaga potwierdzenia po faktycznej zmianie markupu).

**Kontrakt**: Zero czerwonych testów po zakończeniu fazy.

### Kryteria sukcesu:

#### Automatyczne

- `php artisan test` przechodzi w całości
- Build pod Node 20 przechodzi

#### Ręczne

- Pełny przepływ CRUD meczu (lista → utwórz → edytuj → usuń z potwierdzeniem) na desktop i wąskim viewporcie mobilnym
- Przepływ drużyny (edycja, wybór kandydata lokalizacji)
- Logowanie/rejestracja z nowym checkboxem/linkiem
- Wizualna spójność nowej palety (biały/niebieski/czerwony) na wszystkich powyższych ekranach

### Dopiski z ręcznego przeglądu na żywo (poza pierwotnym zakresem, dodane w tej fazie)

- **Krytyczny bug znaleziony i naprawiony**: `@tailwindcss/forms` (strategia domyślna `base`) nadpisywał wygląd zaznaczonego radio DaisyUI regułą `input:where([type=radio]):checked:focus` o wyższej specyficzności CSS niż `.radio-primary:checked` — radio stawał się przezroczysty/biały (niewidoczny) w momencie fokusu, czyli zaraz po kliknięciu. Naprawione przez `tailwind.config.js`: `forms({ strategy: 'class' })` zamiast `forms` — plugin przestaje dotykać nieoznaczonych natywnych kontrolek, DaisyUI przejmuje pełną kontrolę.
- `dashboard.blade.php`: linki "Zobacz mecze"/"Zobacz statystyki" zastąpione dwoma kafelkami (`card` + ikona + opis) na życzenie użytkownika po przeglądzie na żywo.
- `matches/index.blade.php`: "Edytuj" zmienione z linku tekstowego na `btn btn-sm btn-outline btn-primary`, dopasowane do sąsiadującego `btn-sm` na `x-danger-button` "Usuń" (oba przyciski, nie link + przycisk).
- `components/application-logo.blade.php`: domyślne logo Laravel/Breeze zastąpione tekstowym wordmarkiem "WYJAZDOWICZ" (gradient primary→accent, `font-extrabold`, `drop-shadow-sm`); `layouts/app.blade.php` i `layouts/guest.blade.php` doładowują wagę `800` fontu Figtree.

---

## Faza 4: Statystyki (nice-to-have)

### Przegląd

Restyling `stats/index.blade.php` i `stats/partials/stats-block.blade.php`. Wykres słupkowy W/D/L zostaje celowo nietknięty (kolory z S-05). Ta faza może zjechać w priorytecie, jeśli zabraknie czasu — potwierdzone z użytkownikiem.

### Wymagane zmiany:

#### 1. Taby statystyk

**Plik**: `resources/views/stats/index.blade.php`

**Cel**: Przestylować przyciski tabów (linie 19-40) na DaisyUI `tabs`/`tabs-bordered`/`tab-active`, zachowując dokładnie obecną logikę Alpine (`x-data="{ tab: 'overall' }"`, `:class` binding, `x-show="tab === '...'"` na panelach).

**Kontrakt**: Wartości stanu `tab` (`'overall'`/`'home'`/`'away'`) i warunki `x-show` niezmienione — zmieniają się wyłącznie klasy wizualne przycisków tabów.

#### 2. Karty statystyk i "Pechowy kibic"

**Plik**: `resources/views/stats/partials/stats-block.blade.php`

**Cel**: Przestylować grid kart statystyk (linie 14-35) na DaisyUI `stat`/`card`. Kartę "Pechowy kibic" (linia 30, obecnie `border border-red-200 rounded-lg p-4 text-center bg-red-50`) przestylować na DaisyUI `alert alert-error` lub `badge badge-error` w karcie — **bez owijania tekstu procentowego/streaku w dodatkowy tag**, patrz "Krytyczne szczegóły implementacji".

**Kontrakt**: Wykres słupkowy (linie 37-51) i kolory `#0ca30c`/`#898781`/`#d03b3b` z `app/Services/StatsCalculator.php` — **nietknięte**, żadna zmiana klas ani inline style poza tym, co już istnieje.

#### 3. Aktualizacja StatsTest.php

**Plik**: `tests/Feature/StatsTest.php`

**Cel**: Zaktualizować `assertSame(1, substr_count($response->getContent(), 'border-red-200'))` (linie 271, 335) na nową klasę/marker wprowadzoną w kroku 4.2 (np. `alert-error` lub `badge-error`, zależnie od finalnego wyboru). Uruchomić cały plik po zmianie i potwierdzić, że asercje `>100%<`/`>1× W<`/`>1× P<`/`>0%<` nadal przechodzą bez modyfikacji.

**Kontrakt**: Liczba wystąpień markera pozostaje `1` na test (dokładnie jedna karta "Pechowy kibic" widoczna na raz).

### Kryteria sukcesu:

#### Automatyczne

- `tests/Feature/StatsTest.php` przechodzi w całości po aktualizacji asercji
- Build pod Node 20 przechodzi

#### Ręczne

- Przełączanie tabów (Ogółem/Dom/Wyjazd) na desktop i mobile
- Wykres słupkowy renderuje się z niezmienionymi kolorami
- Karta "Pechowy kibic" widoczna dokładnie raz, gdy dotyczy

### Dopiski z ręcznego przeglądu na żywo (poza pierwotnym zakresem, dodane w tej fazie)

- `stats-block.blade.php`: powiększone fonty w sekcji "Bilans" (nagłówek, liczba nad słupkiem, etykieta) i dodany odstęp (`mt-4`, `mb-4`) na życzenie użytkownika — było zbyt ciasno. Kolory słupków i logika liczenia wysokości — bez zmian.

---

## Faza 5: Strona główna (nice-to-have)

### Przegląd

Pełna wymiana `welcome.blade.php` na minimalny branded hero w nowej palecie — bez treści marketingowej, bez zrzutów ekranu. Ta faza łamie gate `deploy.yml` (patrz "Krytyczne szczegóły implementacji") — naprawa w Fazie 6.

### Wymagane zmiany:

#### 1. Nowa strona główna

**Plik**: `resources/views/welcome.blade.php`

**Cel**: Zastąpić cały obecny plik (inline compiled-CSS fallback, arbitralne hex-utilities, dekoracyjne SVG) minimalnym brandowanym hero: `<x-application-logo>`/nazwa aplikacji, jedno zdanie propozycji wartości po polsku przez `__()`, przyciski CTA do logowania i rejestracji (`route('login')`, `route('register')`) w stylu DaisyUI `btn btn-primary`/`btn-outline`. Ustawić `data-theme="wyjazdowicz"` na `<html>` tej strony (ma własny, niezależny od `x-app-layout`/`x-guest-layout` boilerplate).

**Kontrakt**: Route `/` (`routes/web.php:9-11`) bez zmian — nadal zwraca `view('welcome')`. Nowe stringi przez `__('Polski tekst')` zgodnie z istniejącą konwencją (bez wymogu wpisu w `lang/pl.json`, chyba że treść wymaga późniejszego nadpisania).

### Kryteria sukcesu:

#### Automatyczne

- `tests/Feature/ExampleTest.php` (asertuje tylko status 200 na `/`) przechodzi
- Build pod Node 20 przechodzi

#### Ręczne

- Wizualna weryfikacja landing page na desktop i mobile — logo/nazwa, jedno zdanie, dwa CTA prowadzące do właściwych tras
- Potwierdzenie, że stara treść startera Laravela (SVG, arbitralne hexy) zniknęła całkowicie

---

## Faza 6: Regresja końcowa i merge

### Przegląd

Finalna weryfikacja całej zmiany przed jedynym mergem do mastera: pełny build, pełny test suite, naprawa gate'u `deploy.yml` złamanego w Fazie 5, ręczny przegląd mobile na kluczowych ekranach.

### Wymagane zmiany:

#### 1. Aktualizacja gate'u deploy.yml

**Plik**: `.github/workflows/deploy.yml`

**Cel**: Zaktualizować krok "Verify compiled CSS contains known responsive classes" — zastąpić cztery zaszyte na sztywno klasy (`sm:block`, `sm:grid-cols-4`, `lg:block`, `lg:flex-row`) klasami faktycznie obecnymi w finalnym skompilowanym CSS po całej migracji (sprawdzić `public/build/assets/*.css` po Fazie 5 i dobrać nowy zestaw klas reprezentatywnych dla responsywności aplikacji, np. z zachowanego mobile-card/desktop-table split w `matches/index.blade.php`).

**Kontrakt**: Ten sam mechanizm gate'u (grep skompilowanego CSS przed uploadem), zmienia się wyłącznie lista sprawdzanych klas.

#### 2. Pełna regresja

**Cel**: Uruchomić kompletny build i test suite jako ostateczną bramkę przed mergem.

**Kontrakt**: Zero czerwonych testów, build kończy się sukcesem, gate `deploy.yml` przechodzi lokalnie z nową listą klas.

### Kryteria sukcesu:

#### Automatyczne

- `PATH="$HOME/.nvm/versions/node/v20.20.2/bin:$PATH" npm run build` przechodzi
- `php artisan test` przechodzi w całości (wszystkie pliki z `tests/Feature/`)
- Zaktualizowany krok gate'u `deploy.yml` przechodzi lokalnie (ten sam grep na `public/build/assets/*.css`)

#### Ręczne

- Ręczny przegląd mobile-viewport (np. 375px) na: liście meczów, statystykach, dashboardzie, stronie głównej
- Ręczny przegląd desktop na tych samych ekranach
- Merge feature-branch do `master` (jeden merge, zgodnie z decyzją)

---

## Strategia testowania

### Testy jednostkowe:

- Brak nowej logiki biznesowej w tej zmianie — czysto UI/CSS. Testy jednostkowe bez zmian.

### Testy integracyjne:

- Istniejący `tests/Feature/*` suite pozostaje jedynym źródłem prawdy o zachowaniu; każda faza aktualizuje powiązane pliki testowe w tej samej fazie, w której zmienia odpowiadający markup (nie na końcu).
- Szczególna uwaga na `StatsTest.php` (Faza 4, asercje strukturalne na klasach i sąsiedztwie tagów) i modal-zależne testy w `ProfileTest.php`/`GameMatchTest.php` (Faza 2/3, kontrakt eventów Alpine).

### Kroki testowania ręcznego:

1. Po każdej fazie: otwórz zmienione ekrany na desktop i viewport mobilny (~375px).
2. Faza 2: zweryfikuj cały cykl modala usuwania konta (otwórz → Escape → otwórz → Cancel → otwórz → Usuń).
3. Faza 3: zweryfikuj cykl usuwania meczu z N modalami (kilka meczów na liście, usuń jeden, potwierdź że tylko jego modal się otworzył).
4. Faza 6: pełny przegląd regresyjny przed mergem.

## Uwagi dotyczące wydajności

Brak nowych zapytań/logiki backendowej — czysto CSS/markup. Rozmiar skompilowanego CSS może wzrosnąć wraz z dodaniem DaisyUI; NFR "<1s p95" dotyczy akcji CRUD (backend), nie jest zagrożony przez tę zmianę, ale warto potwierdzić w Fazie 6, że rozmiar bundla CSS nie urósł drastycznie (DaisyUI purguje nieużywane klasy przez ten sam `content` glob co Tailwind).

## Uwagi dotyczące migracji

Brak migracji danych. Jeden long-lived feature branch przez wszystkie 6 faz, jeden merge do `master` na końcu (decyzja potwierdzona z użytkownikiem) — żaden użytkownik produkcyjny nie zobaczy częściowo przebudowanego UI.

## Referencje

- Badanie: `context/changes/ui-redesign-daisyui/research.md`
- Precedens "jeden plan, jedna implementacja": `context/archive/2026-07-26-home-away-stats-split/plan.md` (S-06)
- Wzorzec modala usuwania: `context/archive/2026-07-26-edit-delete-match/plan.md` (S-04)
- Pułapka build Node 18/20: impl-review S-04, potwierdzona w `context/changes/testing-quality-gates-wiring/research.md:33,86`
- Gate CI: `.github/workflows/deploy.yml`, dodany przez `context/changes/testing-quality-gates-wiring/`

## Postęp

> Konwencja: `- [ ]` oczekujące, `- [x]` wykonane. Dołącz ` — <commit sha>` po zakończeniu kroku. Nie zmieniaj nazw tytułów kroków.

### Faza 1: Fundament

#### Automatyczne

- [x] 1.1 npm install przechodzi czysto po zmianach w package.json — 2963b63
- [x] 1.2 npm run build (Node 20) kończy się sukcesem — 2963b63
- [x] 1.3 Skompilowany CSS zawiera klasy DaisyUI — 2963b63
- [x] 1.4 php artisan test przechodzi bez regresji — 2963b63
- [x] 1.5 lessons.md — pominięte po weryfikacji, .nvmrc z 2026-08-01 już rozwiązuje pułapkę (patrz Faza 1 pkt 3)

#### Ręczne

- [x] 1.6 npm run dev startuje bez błędów konfiguracji — 2963b63

### Faza 2: Wspólne komponenty i layout

#### Automatyczne

- [x] 2.1 php artisan test przechodzi w całości — 209f8d3
- [x] 2.2 Build pod Node 20 przechodzi — 209f8d3

#### Ręczne

- [x] 2.3 Wizualna weryfikacja nawigacji, dropdownu, auth, modala usuwania konta (desktop + mobile) — 209f8d3

### Faza 3: Core CRUD widoki

#### Automatyczne

- [x] 3.1 php artisan test przechodzi w całości — 8947428
- [x] 3.2 Build pod Node 20 przechodzi — 8947428

#### Ręczne

- [x] 3.3 Pełny CRUD meczu (desktop + mobile) — 8947428
- [x] 3.4 Przepływ drużyny — 8947428
- [x] 3.5 Logowanie/rejestracja — 8947428
- [x] 3.6 Spójność wizualna nowej palety — 8947428

### Faza 4: Statystyki

#### Automatyczne

- [x] 4.1 StatsTest.php przechodzi w całości po aktualizacji asercji — d0bb8c6
- [x] 4.2 Build pod Node 20 przechodzi — d0bb8c6

#### Ręczne

- [x] 4.3 Przełączanie tabów (desktop + mobile) — d0bb8c6
- [x] 4.4 Wykres słupkowy z niezmienionymi kolorami — d0bb8c6
- [x] 4.5 Karta "Pechowy kibic" widoczna dokładnie raz, gdy dotyczy — d0bb8c6

### Faza 5: Strona główna

#### Automatyczne

- [x] 5.1 ExampleTest.php przechodzi — 8a428ce
- [x] 5.2 Build pod Node 20 przechodzi — 8a428ce

#### Ręczne

- [x] 5.3 Wizualna weryfikacja landing page (desktop + mobile), CTA prowadzą do właściwych tras — 8a428ce
- [x] 5.4 Potwierdzenie usunięcia starej treści startera Laravela — 8a428ce

### Faza 6: Regresja końcowa i merge

#### Automatyczne

- [x] 6.1 npm run build (Node 20) przechodzi — 380e902
- [x] 6.2 php artisan test przechodzi w całości — 380e902
- [x] 6.3 Zaktualizowany gate deploy.yml przechodzi lokalnie — 380e902

#### Ręczne

- [x] 6.4 Przegląd mobile-viewport (matches/stats/dashboard/landing) — 380e902
- [x] 6.5 Przegląd desktop — 380e902
- [ ] 6.6 Merge feature-branch do master
