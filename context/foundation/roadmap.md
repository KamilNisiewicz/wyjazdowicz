---
project: "Wyjazdowicz"
version: 1
status: draft
created: 2026-07-18
updated: 2026-07-26
prd_version: 1
main_goal: speed
top_blocker: time
---

# Mapa drogowa: Wyjazdowicz

> Wywiedziono z `context/foundation/prd.md` (v1) + automatycznie zbadana baza kodu.
> Edytuj na miejscu; archiwizuj po zastąpieniu.
> Elementy poniżej są wymienione w kolejności zależności. Tabela „W skrócie” to indeks.

## Podsumowanie wizji

Kibic piłkarski jeżdżący na mecze wyjazdowe (i chodzący na domowe) po latach kibicowania nie ma żadnego zapisu, na których meczach był, jaki dystans pokonał, ani jaki jest jego bilans W/D/L. Zwykły arkusz kalkulacyjny tego nie załatwi — nie policzy sam z siebie dystansu z domu do miasta meczu wyjazdowego, ani nie wyliczy automatycznie, czy kibic jest "pechowy" (czy porażki dominują w jego bilansie). To wymaga dedykowanej logiki, nie tylko listy wierszy.

## Gwiazda przewodnia

**S-03: Dodanie meczu wyjazdowego z automatycznym obliczeniem dystansu, widoczne na liście meczów** — to najmniejszy kompleksowy kawałek, który odróżnia appkę od arkusza kalkulacyjnego (FR-007: "automatyzacja dystansu to sedno wartości appki, ręczne wpisywanie byłoby pustym CRUD-em").

> Jednowierszowe wyjaśnienie: "gwiazda przewodnia" to najmniejszy, kompleksowy element, którego pomyślne dostarczenie udowodniłoby podstawową hipotezę produktu — umieszczony tak wcześnie, jak pozwalają na to wymagania wstępne, ponieważ wszystko inne ma znaczenie tylko wtedy, gdy to działa.

## W skrócie

| ID    | ID zmiany                | Wynik (użytkownik może …)                                                   | Wymagania wstępne | Odnośniki PRD               | Status   |
| ----- | ------------------------- | ---------------------------------------------------------------------------- | ------------------ | ---------------------------- | -------- |
| S-01  | `user-registration-login` | zarejestrować się i zalogować                                                | —                   | FR-001                       | zrobione |
| S-02  | `team-and-home-profile`   | ustawić ulubioną drużynę (miasto stadionu drużyny = "dom" do liczenia dystansu) | S-01             | FR-002                       | zrobione |
| S-03  | `add-match-with-distance` | dodać mecz (dom/wyjazd) z auto-obliczonym dystansem, widoczny na liście       | S-02                | FR-003, FR-006, FR-007, US-01 | zrobione |
| S-04  | `edit-delete-match`       | edytować lub usunąć dodany mecz                                              | S-03                | FR-004, FR-005               | zrobione |
| S-05  | `stats-dashboard`         | zobaczyć panel statystyk (bilans, %, passa, wskaźnik "pechowy kibic")        | S-03                | FR-008, FR-009, FR-010, FR-011, US-01 | proponowany |
| S-06  | `home-away-stats-split`   | zobaczyć statystyki osobno dla meczów domowych i wyjazdowych                 | S-05                | FR-012                       | zrobione |

## Baza

Co już jest na miejscu w bazie kodu na dzień `2026-07-18` (automatycznie zbadane + potwierdzone przez użytkownika). Elementy poniżej zakładają, że są one obecne i nie tworzą ich ponownie.

- **Frontend:** częściowy — Blade + Vite + Tailwind CSS 4 scaffold obecny (`vite.config.js`, `resources/css/app.css`); zero widoków domenowych, jedyny widok to `resources/views/welcome.blade.php`.
- **Backend / API:** nieobecny — `routes/web.php` zawiera wyłącznie domyślną trasę `/`; brak kontrolerów poza pustym `app/Http/Controllers/Controller.php`.
- **Dane:** nieobecny — `database/migrations/` zawiera tylko domyślne migracje Laravela (`users`, `cache`, `jobs`); brak modeli poza `app/Models/User.php`.
- **Uwierzytelnianie:** nieobecny — brak `laravel/breeze`/`fortify`/`jetstream`/`sanctum` w `composer.json`, brak `routes/auth.php`, brak middleware `auth` gdziekolwiek w kodzie.
- **Wdrożenie / infrastruktura:** obecny — zweryfikowane na żywo na cyberFolks (`wyjazdowicz.cfolks.pl`, HTTP 200), auto-deploy na merge przez GitHub Actions działa end-to-end (`context/foundation/infrastructure.md`).
- **Obserwowalność:** częściowy — tylko domyślne logowanie plikowe Laravel/Monolog (`config/logging.php`), brak zewnętrznego error trackingu i metryk.

## Fundamenty

Brak. Cała funkcjonalność wymagana przez PRD ma bezpośredni, widoczny dla użytkownika wynik (nawet uwierzytelnianie — FR-001 to "użytkownik może się zarejestrować i zalogować", nie niewidoczny szkielet), więc każdy element jest pionowym `S-NN`. Jedyna gotowa infrastruktura przekrojowa (wdrożenie/CI) jest już obecna w bazie (patrz `## Baza`) i nie wymaga osobnego fundamentu.

## Elementy

### S-01: Rejestracja i logowanie

- **Wynik:** użytkownik może się zarejestrować i zalogować.
- **ID zmiany:** `user-registration-login`
- **Odnośniki PRD:** FR-001
- **Wymagania wstępne:** —
- **Równolegle z:** —
- **Blokady:** —
- **Niewiadome:**
  - Konkretny mechanizm logowania nie jest rozstrzygnięty w PRD ("email+hasło / OAuth / magic link — konkretny mechanizm to decyzja techniczna, poza zakresem PRD", `## Access Control`). Właściciel: użytkownik/zespół. Blokuje: nie — `/10x-plan` może wybrać rozsądny domyślny wybór (np. Laravel Breeze, email+hasło).
- **Ryzyko:** to fundament dla wszystkiego innego w appce — błąd w izolacji danych między kontami złamałby wymóg prywatności z `## NFR`, ale przy single-user MVP ryzyko praktyczne jest niskie.
- **Status:** zrobione

### S-02: Ulubiona drużyna (= lokalizacja "dom")

- **Wynik:** użytkownik ustawia swoją ulubioną drużynę wraz z miastem, w którym drużyna rozgrywa mecze domowe; to miasto jest lokalizacją "dom" używaną do liczenia dystansu (decyzja podjęta przy tworzeniu mapy drogowej: `## Business Logic` nie wymaga osobnego adresu zamieszkania — miasto stadionu ulubionej drużyny wystarcza jako punkt startowy).
- **ID zmiany:** `team-and-home-profile`
- **Odnośniki PRD:** FR-002
- **Wymagania wstępne:** S-01
- **Równolegle z:** —
- **Blokady:** —
- **Niewiadome:** — (rozwiązane: brak osobnego pola na adres — miasto drużyny = "dom"; miasto jest tekstem wpisywanym ręcznie przez użytkownika, geokodowanym tym samym mechanizmem co miasta meczów, patrz S-03)
- **Ryzyko:** literówka lub niejednoznaczna nazwa miasta drużyny przy geokodowaniu zepsułaby dystans dla *wszystkich* meczów wyjazdowych na raz (nie tylko jednego) — warto pokazać użytkownikowi wynik geokodowania (np. potwierdzenie znalezionego miasta) przy zapisie.
- **Status:** zrobione

### S-03: Dodanie meczu z automatycznym obliczeniem dystansu

- **Wynik:** użytkownik dodaje mecz, na którym był obecny (data, przeciwnik, dom/wyjazd, wynik, miejscowość); dystans dom→miejscowość jest liczony automatycznie dla meczów wyjazdowych; mecz pojawia się na liście.
- **ID zmiany:** `add-match-with-distance`
- **Odnośniki PRD:** FR-003, FR-006, FR-007, US-01
- **Wymagania wstępne:** S-02, zewnętrzne: dostępność OpenStreetMap Nominatim (darmowe API geokodowania — decyzja podjęta przy tworzeniu mapy drogowej)
- **Równolegle z:** —
- **Blokady:** —
- **Niewiadome:** — (rozwiązane: geokodowanie miast przez darmowe OpenStreetMap Nominatim, bez własnej bazy stadionów i bez płatnego API)
- **Ryzyko:** to gwiazda przewodnia — błędne obliczenie dystansu unieważnia całą wartość appki (Guardrail w `## Success Criteria`); wymaga testu na znanych parach miast, zanim uzna się to za zrobione. Dodatkowo: Nominatim ma politykę użycia ~1 zapytanie/s i wymaga nagłówka `User-Agent` — wyniki geokodowania miast powinny być cache'owane (np. w kolumnie lat/lng na meczu/drużynie), żeby nie odpytywać API przy każdym wyświetleniu.
- **Status:** zrobione

### S-04: Edycja i usuwanie meczu

- **Wynik:** użytkownik może edytować lub usunąć dodany mecz.
- **ID zmiany:** `edit-delete-match`
- **Odnośniki PRD:** FR-004, FR-005
- **Wymagania wstępne:** S-03
- **Równolegle z:** S-05, S-06
- **Blokady:** —
- **Niewiadome:** —
- **Ryzyko:** edycja wyniku musi przeliczyć bilans/statystyki na nowo — upewnić się, że przeliczenie jest idempotentne (edycja meczu dwa razy nie psuje bilansu).
- **Status:** zrobione

### S-05: Panel statystyk zbiorczych

- **Wynik:** użytkownik widzi panel statystyk: bilans W/D/L, % zwycięstw, aktualną passę i wskaźnik "pechowy kibic".
- **ID zmiany:** `stats-dashboard`
- **Odnośniki PRD:** FR-008, FR-009, FR-010, FR-011, US-01
- **Wymagania wstępne:** S-03
- **Równolegle z:** S-04
- **Blokady:** —
- **Niewiadome:** —
- **Ryzyko:** bilans i wskaźnik pecha muszą pozostać spójne po edycji/usunięciu meczu (S-04) — testy powinny pokryć oba przypadki (dodanie i zmiana istniejącego meczu).
- **Status:** proponowany

### S-06: Podział statystyk dom vs. wyjazd

- **Wynik:** użytkownik widzi statystyki osobno dla meczów domowych i wyjazdowych.
- **ID zmiany:** `home-away-stats-split`
- **Odnośniki PRD:** FR-012
- **Wymagania wstępne:** S-05
- **Równolegle z:** S-04
- **Blokady:** —
- **Niewiadome:** —
- **Ryzyko:** niskie — to filtr/agregacja nad tymi samymi danymi co S-05; główne ryzyko to zduplikowana logika agregująca między widokami.
- **Status:** zrobione

## Przekazanie do backlogu

| ID mapy drogowej | ID zmiany                | Sugerowany tytuł zadania                              | Gotowe do `/10x-plan` | Uwagi |
| ----------------- | -------------------------- | -------------------------------------------------------- | ---------------------- | ----- |
| S-01               | `user-registration-login`  | Rejestracja i logowanie użytkownika                       | tak                     | Uruchom `/10x-plan user-registration-login` |
| S-02               | `team-and-home-profile`    | Profil: ulubiona drużyna (= lokalizacja "dom")              | nie                     | Czeka na S-01 |
| S-03               | `add-match-with-distance`  | Dodawanie meczu z automatycznym obliczeniem dystansu       | nie                     | Czeka na S-02; dystans liczony przez OpenStreetMap Nominatim |
| S-04               | `edit-delete-match`        | Edycja i usuwanie meczu                                    | nie                     | Czeka na S-03 |
| S-05               | `stats-dashboard`          | Panel statystyk zbiorczych (bilans, passa, pechowy kibic)  | nie                     | Czeka na S-03 |
| S-06               | `home-away-stats-split`    | Podział statystyk dom vs. wyjazd                            | nie                     | Czeka na S-05 |

## Otwarte Pytania Mapy Drogowej

Brak. Obie pierwotne niewiadome zostały rozwiązane przy tworzeniu mapy drogowej (2026-07-18):
- Lokalizacja "dom" = miasto, w którym ulubiona drużyna użytkownika rozgrywa mecze domowe (ustawiane w S-02, bez osobnego pola adresu).
- Dane geograficzne miast = OpenStreetMap Nominatim (darmowe API geokodowania), bez własnej bazy stadionów.

Obie decyzje są udokumentowane w Niewiadomych S-02/S-03 (oznaczone jako rozwiązane) i w ich polach Ryzyko.

## Zaparkowane

- **Gamifikacja / ranking "najlepszego kibica"** — Dlaczego zaparkowane: `## Non-Goals` PRD — appka nie porównuje użytkowników między sobą ani nie przyznaje odznak/punktów.
- **Rywalizacja / funkcje społecznościowe między kibicami różnych klubów** — Dlaczego zaparkowane: `## Non-Goals` PRD — MVP jest ściśle single-user.
- **Import historycznych meczów z zewnętrznych źródeł** — Dlaczego zaparkowane: `## Non-Goals` PRD — użytkownik wpisuje mecze wyłącznie ręcznie.
- **Wsparcie dla wielu drużyn na jedno konto** — Dlaczego zaparkowane: `## Non-Goals` PRD — jedna ulubiona drużyna per użytkownik na MVP.
- **Porównanie osobistego bilansu z ogólnym bilansem drużyny** — Dlaczego zaparkowane: `## Non-Goals` PRD — wskaźnik "pechowego kibica" opiera się wyłącznie na własnym W/D/L użytkownika.
- **Tryb offline** — Dlaczego zaparkowane: `## Non-Goals` PRD — aplikacja wymaga połączenia z internetem.

## Zrobione

- **S-01: zarejestrować się i zalogować** — Zarchiwizowano 2026-07-23 → `context/archive/2026-07-19-user-registration-login/`. Lekcja: —.
- **S-02: ustawić ulubioną drużynę (miasto stadionu drużyny = "dom" do liczenia dystansu)** — Zarchiwizowano 2026-07-23 → `context/archive/2026-07-19-team-and-home-profile/`. Lekcja: —.
- **S-06: zobaczyć statystyki osobno dla meczów domowych i wyjazdowych** — Zarchiwizowano 2026-07-26 → `context/archive/2026-07-26-home-away-stats-split/`. Lekcja: —.
- **S-03: dodać mecz (dom/wyjazd) z auto-obliczonym dystansem, widoczny na liście** — Zarchiwizowano 2026-07-26 → `context/archive/2026-07-23-add-match-with-distance/`. Lekcja: —.
- **S-04: edytować lub usunąć dodany mecz** — Zarchiwizowano 2026-07-26 → `context/archive/2026-07-26-edit-delete-match/`. Lekcja: —.
