# UI redesign z DaisyUI — Krótki plan

> Pełny plan: `context/changes/ui-redesign-daisyui/plan.md`
> Badania: `context/changes/ui-redesign-daisyui/research.md`

## Co i dlaczego

Przebudowujemy cały UI aplikacji (23 pliki Blade) na DaisyUI 4.x jako plugin Tailwind, z nową custom paletą kolorów (biały/niebieski/czerwony) zamiast dzisiejszego domyślnego indigo/gray z Laravel Breeze. Wygląd aplikacji był odkładany "na po 10xChampionie" i średnio się podoba — to jego odświeżenie.

## Punkt wyjścia

Dziś: Tailwind 3.4.19 bez żadnej custom palety, 13 komponentów Blade + 3 layouty niosące całe stylowanie (Breeze default), Alpine.js jako jedyny JS runtime, `welcome.blade.php` to niedotknięty starter Laravela niepowiązany z resztą aplikacji.

## Pożądany stan końcowy

Spójny motyw DaisyUI `wyjazdowicz` (biały/niebieski/czerwony) na całej aplikacji. Wszystkie komponenty, layouty i 17 widoków w nowej stylistyce. Branded landing page zamiast domyślnej strony Laravela. Zero zmian w logice Alpine/backendzie — to czysto wizualna przebudowa.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
|---|---|---|---|
| Mechanizm modala | Zostaje Alpine event-bus, tylko restyle | Mniej przepisywania, niższe ryzyko regresji na N dynamicznych modali usuwania meczu | Plan |
| Kolorystyka | Nowa custom paleta: biały/niebieski/czerwony | Wprost zażyczone przez użytkownika, reszta doboru (odcienie, mapowanie na tokeny) zostawiona planiście | Plan |
| Dark mode | Poza zakresem | Redukcja powierzchni QA w już dużej zmianie (23 pliki) | Plan |
| Strona główna | Wymieniona na minimalny branded hero | Potwierdzone explicite przez użytkownika — nie zostaje domyślna strona Laravela | Plan |
| Tabela meczów | Zostaje dual mobile-card/desktop-table | Zero ryzyka dla już dostrojonego mobile-first NFR | Plan |
| Kolory wykresu W/D/L | Zostają nietknięte (hexy z S-05) | Świadoma decyzja S-05 o kolorach niezależnych od trybu; nie ryzykujemy jej odkręcenia | Plan |
| Pułapka Node 18/20 | Zapisana do lessons.md w Fazie 1 | Ta zmiana to najwyższe dotychczasowe ryzyko materializacji tego błędu | Plan |
| Rollout | Jeden merge na koniec (Faza 6) | Zgodne z decyzją "jeden plan, jedna implementacja" (precedens S-06) | Plan |
| Testy | Aktualizowane w tej samej fazie co markup | CI zostaje zielone przez cały czas, regresja łapana przy źródle | Plan |
| Priorytet | Core CRUD (Faza 3) przed statystykami (4) i landing page (5) | Chroni główne kryterium sukcesu PRD (tracking meczów) przed drugorzędnym | Plan |

## Zakres

**W zakresie:** wszystkie 13 komponentów Blade, 3 layouty, 17 widoków (auth/dashboard/matches/profile/stats/team), strona główna, custom motyw DaisyUI, wpis do lessons.md, aktualizacja gate'u `deploy.yml`.

**Poza zakresem:** dark mode, migracja kolorów wykresu, konsolidacja tabeli meczów, przepisanie modala na natywny `<dialog>`, DaisyUI 5.x/Tailwind 4, pełna strona marketingowa, przyrostowe merge/deploy.

## Architektura / Podejście

Sześć faz w kolejności zależności: Fundament (plugin + motyw + lesson) → Wspólne komponenty/layout (propagują się na całą appkę) → Core CRUD (must-have) → Statystyki (nice-to-have) → Strona główna (nice-to-have) → Regresja końcowa + merge. Jeden long-lived branch, jeden PR, jeden merge na końcu.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
|---|---|---|
| 1. Fundament | DaisyUI plugin, custom motyw, wpis do lessons.md | Błędna konfiguracja motywu blokuje wszystkie kolejne fazy |
| 2. Komponenty i layout | 13 komponentów + 3 layouty w nowym motywie | Zerwanie kontraktu eventów modala (`$dispatch`) złamałoby Fazę 3 |
| 3. Core CRUD | auth/dashboard/matches/team w nowym stylu | Testy strukturalne (GameMatchTest, ProfileTest) mogą się złamać |
| 4. Statystyki | Taby, karty statystyk w nowym stylu | `StatsTest.php` ma twarde asercje na klasę `border-red-200` |
| 5. Strona główna | Branded landing page | Łamie gate `deploy.yml` (2 z 4 sprawdzanych klas żyją tylko w welcome.blade.php) |
| 6. Regresja końcowa | Naprawa gate'u, pełna weryfikacja, merge | Ostatnia szansa złapać coś przeoczonego przed jedynym mergem |

**Wymagania wstępne:** brak — build pipeline już kompatybilny z DaisyUI 4.x bez migracji.
**Szacowany nakład pracy:** ~6 sesji implementacyjnych (jedna na fazę), jeden branch/PR.

## Otwarte ryzyka i założenia

- Dokładne wartości hex w motywie (`#2563EB` niebieski, `#DC2626` czerwony) to propozycja planisty w ramach kierunku "biały/niebieski/czerwony" — do potwierdzenia wizualnie w Fazie 1/2, nie tylko na papierze.
- Gate `deploy.yml` na pewno się złamie po Fazie 5 — to udokumentowany, oczekiwany efekt uboczny, naprawiony w Fazie 6, nie regresja do paniki.

## Kryteria sukcesu (podsumowanie)

- Cała aplikacja wizualnie spójna w nowym motywie DaisyUI (biały/niebieski/czerwony), zero regresji funkcjonalnych
- `php artisan test` i `npm run build` (Node 20) zielone na końcu każdej fazy
- Jeden merge do mastera po Fazie 6
