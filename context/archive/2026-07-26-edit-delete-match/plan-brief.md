# Edycja i usuwanie meczu — Krótki plan

> Pełny plan: `context/changes/edit-delete-match/plan.md`

## Co i dlaczego

Dodajemy edycję i usuwanie zapisanego meczu — dziś (po S-03) użytkownik może tylko dodawać mecze, nie może poprawić literówki w wyniku ani usunąć błędnie dodanego meczu. FR-004/FR-005 z PRD oznaczają to jako must-have: „pomyłki przy wpisywaniu wyników są nieuniknione”.

## Punkt wyjścia

`GameMatchController` ma dziś `index`/`create`/`search`/`store`. Model `GameMatch` przechowuje przeciwnika, datę, dom/wyjazd, miejscowość, dystans i wynik — wszystko ustalane raz przy tworzeniu meczu przez dwuetapowy flow geokodowania (Nominatim) dla wyjazdów.

## Pożądany stan końcowy

Na liście meczów każdy wiersz ma linki „Edytuj” i „Usuń”. Edycja pozwala poprawić przeciwnika, datę i wynik (nie dom/wyjazd/miejscowość). Usuwanie wymaga potwierdzenia w modalu i jest trwałe. Próba dostępu do cudzego meczu (inne konto) zwraca 404.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) |
| --- | --- | --- |
| Zakres edycji | Tylko przeciwnik/data/wynik | Venue/city/dystans ustalone raz przy tworzeniu; błąd w nich = usuń i dodaj mecz od nowa, zamiast dublować cały flow geokodowania w edycji |
| Autoryzacja | Query scoping przez `$request->user()->gameMatches()` | Zero nowej infrastruktury (Policy), spójne z tym, co już robi `index()`; cudzy mecz = 404 |
| UX usuwania | Modal (`x-modal`) wzorem `delete-user-form.blade.php` | Spójność wizualna z istniejącym wzorcem Breeze w tym repo |
| Typ usuwania | Twarde usunięcie | Prostota, brak soft-delete gdziekolwiek indziej w projekcie, PRD nie wymaga historii usuniętych meczów |
| Miejsce UI | Akcje w wierszu listy (brak `matches.show`) | Brak nowej strony/trasy, spójne z płaską listą meczów z FR-006 |
| Nazewnictwo tras | RESTful `matches.edit/update/destroy` | Idiomatyczne dla Laravela, ten sam wzorzec co już istniejące `profile.edit/update/destroy` |

## Zakres

**W zakresie:**
- Formularz edycji przeciwnika, daty, wyniku
- Trwałe usuwanie meczu z potwierdzeniem
- Ochrona przed edycją/usunięciem cudzego meczu (404)
- Testy feature (w tym IDOR i walidacja)

**Poza zakresem:**
- Edycja dom/wyjazd, miejscowości, dystansu (ponowne geokodowanie)
- Soft-delete / przywracanie usuniętych meczów
- Strona szczegółów pojedynczego meczu
- Panel statystyk (S-05) — nie istnieje jeszcze, więc nic tu nie przelicza żadnego cache'a

## Architektura / Podejście

Powielenie istniejących wzorców: RESTful trasy analogiczne do `profile.*` (`GET .../edit`, `PATCH`, `DELETE` na `/matches/{match}`), `UpdateRequest` analogiczny do `StoreRequest`, modal usuwania skopiowany z `delete-user-form.blade.php`. Każda akcja kontrolera zaczyna scoping od `$request->user()->gameMatches()->findOrFail($match)`.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| --- | --- | --- |
| 1. Backend | Trasy, kontroler (edit/update/destroy), `UpdateRequest`, testy (w tym IDOR) | Pominięcie scopingu własności → IDOR na cudzym meczu |
| 2. UI | Formularz edycji, kolumna akcji + modal usuwania na liście | Nazwa modala musi być unikalna per wiersz, inaczej wszystkie modały współdzielą stan |

**Wymagania wstępne:** S-03 (`add-match-with-distance`) zaimplementowane — jest, `impl_reviewed`.
**Szacowany nakład pracy:** ~1 sesja, 2 fazy.

## Otwarte ryzyka i założenia

- Brak — wszystkie decyzje rozstrzygnięte podczas planowania, żadnych otwartych pytań.

## Kryteria sukcesu (podsumowanie)

- Użytkownik może edytować przeciwnika/datę/wynik zapisanego meczu i widzi zmianę na liście
- Użytkownik może usunąć mecz przez modal potwierdzenia, mecz znika z listy
- Próba edycji/usunięcia cudzego meczu przez URL zwraca 404
