<!-- IMPL-REVIEW-REPORT -->
# Przegląd implementacji: Edycja i usuwanie meczu — Plan implementacji

- **Plan**: context/changes/edit-delete-match/plan.md
- **Zakres**: Faza 1 i 2 z 2 (pełny plan)
- **Data**: 2026-07-26
- **Werdykt**: WYMAGA UWAGI
- **Ustalenia**: 0 krytycznych, 2 ostrzeżenia, 3 obserwacje

## Werdykty

| Wymiar | Werdykt |
|-----------|---------|
| Zgodność z planem | WARNING |
| Dyscyplina zakresu | PASS |
| Bezpieczeństwo i jakość | PASS |
| Architektura | PASS |
| Spójność wzorców | WARNING |
| Kryteria sukcesu | PASS |

## Ustalenia

### F1 — Plan nie odzwierciedla finalnej architektury listy meczów

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🔎 ŚREDNI — prawdziwy kompromis; zatrzymaj się, aby to przemyśleć
- **Wymiar**: Zgodność z planem
- **Lokalizacja**: context/changes/edit-delete-match/plan.md:103-109 (kontrakt Fazy 2, punkt 2)
- **Szczegóły**: Plan opisuje pojedynczą tabelę zyskującą jedną kolumnę „Akcje” z modalem inline w wierszu. Finalny `resources/views/matches/index.blade.php` to trzy osobne bloki: lista kart na mobile (`sm:hidden`), tabela od `sm:` (`hidden sm:block`), i wspólna pętla renderująca modały usuwania (wyciągnięta poza oba widoki, żeby nie była ukryta przez `display:none` przodka). Powód: podczas ręcznego testowania wyszły dwa realne bugi — tabela wychodziła poza ekran na mobile (naprawione przez `overflow-x-auto`), a potem desktop przestał w ogóle pokazywać mecze, bo `sm:block` nigdy nie zostało wkompilowane do statycznego builda CSS (projekt buduje assety raz przez `npm run build` pod Node 18, nie live przez Vite). Naprawa (rebuild Node'em 20, już dostępnym przez nvm) zadziałała i została zweryfikowana ręcznie, ale ani ta poprawka, ani finalna struktura 3 bloków, ani pułapka z `sm:block` nie są nigdzie zapisane w `plan.md` ani w `change.md` → Notes. Ktoś czytający sam plan miałby błędny obraz struktury widoku.
- **Poprawka A ⭐ Zalecane**: Dopisz krótki dodatek pod Fazą 2 w `plan.md` opisujący finalną strukturę (karty mobile / tabela desktop / wspólna pętla modali) i pułapkę ze statycznym buildem CSS (`sm:block`).
  - Siła: Utrzymuje `plan.md` jako wiarygodne źródło prawdy dla tej zmiany; pułapka Node 18 vs Tailwind JIT to dokładnie ten typ powtarzalnej pułapki, którą `lessons.md` już śledzi (symlink `build/`) — zasługuje na to samo traktowanie.
  - Kompromis: Odrobinę dodatkowej pracy pisarskiej; `plan.md` staje się mieszanką „jak zaplanowano” i „jak zbudowano”, jeśli nie oznaczone wyraźnie jako dodatek.
  - Pewność: WYSOKA — czysto dokumentacyjna poprawka, zero ryzyka w kodzie.
  - Martwy punkt: Brak znaczących.
- **Poprawka B**: Zapisz pułapkę statycznego builda Tailwind w `context/foundation/lessons.md` przez `/10x-lesson` zamiast w `plan.md`, skoro to powtarzalna pułapka na poziomie projektu (już częściowo udokumentowana jako „do naprawienia” w `START-KONTEKST.md`), a nie jednorazowe odchylenie tego planu.
  - Siła: Umieszcza praktyczną, powtarzalną regułę tam, gdzie przyszłe `/10x-plan` i `/10x-implement` faktycznie ją zobaczą.
  - Kompromis: Nie adresuje węższej luki — że `plan.md` nie opisuje finalnej struktury `index.blade.php`.
  - Pewność: ŚREDNIA — `lessons.md` jest czytane przez umiejętności planowania/implementacji, ale samo w sobie nie naprawia luki plan-vs-rzeczywistość w opisie architektury.
  - Martwy punkt: Obie poprawki można zastosować razem — nie wykluczają się.
- **Decyzja**: FIXED (Poprawka A — dodatek dopisany pod Fazą 2 w plan.md)

### F2 — Przycisk usuwania nie używa x-danger-button zgodnie z kontraktem planu

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Spójność wzorców
- **Lokalizacja**: resources/views/matches/index.blade.php (przycisk mobile ~linia 53, przycisk desktop ~linia 88)
- **Szczegóły**: Kontrakt planu nazwał `x-danger-button` jako przycisk wyzwalający usuwanie (wzorem przycisku w `delete-user-form.blade.php:12-15`). Implementacja używa zwykłego `<button class="text-sm text-red-600 underline">`. `x-danger-button` jest poprawnie użyty na przycisku zatwierdzającym w modalu, ale nie na przycisku w wierszu/karcie. Funkcjonalnie identyczne (to samo `$dispatch`/`open-modal`, ta sama unikalna nazwa modala per mecz), ale odejście od komponentu wskazanego w planie i od wzorca Breeze, na którym się wzorowano.
- **Poprawka**: Zamień oba przyciski wyzwalające na `<x-danger-button type="button" x-data="" x-on:click.prevent="...">{{ __('Usuń') }}</x-danger-button>`, żeby dokładnie odpowiadały przyciskowi z `delete-user-form.blade.php`.
- **Decyzja**: FIXED (oba przyciski, mobile i desktop, zamienione na `<x-danger-button>`; testy 22/22 zielone, klasy już wkompilowane w CSS)

### F3 — Poleganie wyłącznie na wąskich rules() jako jedynej ochronie przed mass assignment

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI
- **Wymiar**: Bezpieczeństwo i jakość
- **Lokalizacja**: app/Http/Controllers/GameMatchController.php:102 (`update()`), app/Models/GameMatch.php:12 (`#[Fillable(...)]`)
- **Szczegóły**: `$gameMatch->update($request->validated())` jest dziś bezpieczne wyłącznie dlatego, że `UpdateRequest::rules()` waliduje tylko 4 pola; `#[Fillable(...)]` modelu jest szersze (obejmuje też `venue`/`city`/`lat`/`lng`/`distance_km`). Gdyby przyszła zmiana dodała jedno z tych pól do `rules()` „tylko do wyświetlenia”, stałoby się cicho zapisywalne. Brak żywego błędu dziś.
- **Poprawka**: Brak wymaganego działania teraz; jeśli ten wzorzec się powtórzy, rozważ `$request->safe()->only([...])` zamiast polegania wyłącznie na wąskości reguł `FormRequest`.
- **Decyzja**: FIXED (`GameMatchController::update()` zmienione na `$request->safe()->only(['opponent', 'played_on', 'goals_for', 'goals_against'])`; testy 22/22 zielone)

### F4 — Trwałe usuwanie bez soft-delete/backupu

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI (świadoma decyzja, już podjęta w planowaniu)
- **Wymiar**: Bezpieczeństwo danych
- **Lokalizacja**: app/Http/Controllers/GameMatchController.php:110
- **Szczegóły**: Potwierdzona świadoma decyzja z planowania (`plan.md` → „Czego NIE robimy”). Ponownie zgłoszone tutaj, bo produkcja wciąż nie ma skonfigurowanych backupów bazy danych (znane ryzyko z `infrastructure.md`), a to pierwsza zmiana pozwalająca użytkownikowi nieodwracalnie skasować realne dane meczów, które sam wpisał.
- **Poprawka**: Brak działania w ramach tej zmiany; rozważ priorytetyzację backupów produkcyjnej bazy danych, skoro istnieją już realne dane warte utraty (już zaznaczone jako do zrobienia w notatkach projektu).
- **Decyzja**: ACCEPTED (świadomie zaakceptowane ryzyko — decyzja podjęta w planowaniu; backupy produkcyjne to osobne zadanie poza tą zmianą)

### F5 — Modal renderowany bez ograniczeń przy dużej liczbie meczów

- **Ważność**: OBSERWACJA
- **Wpływ**: 🏃 NISKI
- **Wymiar**: Architektura
- **Lokalizacja**: resources/views/matches/index.blade.php (wspólna pętla modali, ~linie 100-125)
- **Szczegóły**: Jeden pełny `<x-modal>` jest renderowany bezwarunkowo per mecz; rozmiar DOM rośnie liniowo z liczbą meczów. W porządku przy skali MVP (jeden użytkownik, realistyczne liczby meczów), warto rozważyć ponownie przy setkach wierszy.
- **Poprawka**: Brak wymaganego działania teraz.
- **Decyzja**: SKIPPED (skala MVP zbyt mała, żeby to było warte naprawy teraz)
