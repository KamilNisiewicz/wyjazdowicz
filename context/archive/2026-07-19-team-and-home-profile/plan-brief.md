# Ulubiona drużyna i lokalizacja "dom" — Krótki plan

> Pełny plan: `context/changes/team-and-home-profile/plan.md`

## Co i dlaczego

Użytkownik ustawia swoją ulubioną drużynę i miasto, w którym rozgrywa ona mecze domowe (FR-002). To miasto staje się lokalizacją "dom" do liczenia dystansu na mecze wyjazdowe w S-03 — jedynym punkcie danych wystarczającym do wartości appki, zgodnie z decyzją podjętą przy tworzeniu mapy drogowej (bez osobnego adresu zamieszkania).

## Punkt wyjścia

Po S-01 istnieje tylko rejestracja/logowanie (Breeze) i pusty `/dashboard`. Zero modeli domenowych, zero integracji z zewnętrznym API.

## Pożądany stan końcowy

Nowo zarejestrowany użytkownik jest kierowany na formularz drużyny, zanim zobaczy dashboard. Wpisuje nazwę drużyny i miasto, appka geokoduje miasto przez darmowe API OpenStreetMap Nominatim i pokazuje listę znalezionych kandydatur do potwierdzenia — dopiero po wyborze zapisuje drużynę i odblokowuje dashboard. Ten sam formularz służy do późniejszej edycji.

## Kluczowe podjęte decyzje

| Decyzja | Wybór | Dlaczego (1 zdanie) | Źródło |
| --- | --- | --- | --- |
| Model danych | Osobna tabela `teams` (1:1 z `users`) | Czystszy podział mimo jednej drużyny na użytkownika w MVP | Plan (pytanie użytkownika) |
| Timing geokodowania | Synchronicznie przy żądaniu | Rzadka, jednorazowa akcja — NFR <1s dotyczy akcji na meczach, nie tego formularza | Plan |
| Potwierdzenie wyniku | Ekran z listą kandydatów do wyboru | Adresuje ryzyko z roadmapy: literówka psuje dystans dla wszystkich meczów wyjazdowych naraz | Plan (pytanie użytkownika) |
| Błąd geokodowania | Blokuj zapis, pokaż błąd walidacji | Gwarantuje, że S-03 może bezpiecznie założyć obecność współrzędnych | Plan |
| Miejsce w UI | Nowa strona `/team` (nie rozszerzenie `/profile` Breeze) | Rozdziela dane konta (Breeze) od danych domenowych appki | Plan |
| Wymagalność | Wymuszone przekierowanie z `/dashboard` | Gwarantuje, że każdy użytkownik ma drużynę zanim zobaczy appkę | Plan (pytanie użytkownika) |
| Wiele wyników Nominatim | Lista kandydatów do wyboru (nie tylko pierwszy wynik) | Eliminuje ręczne dopisywanie kraju przy niejednoznacznych nazwach | Plan (pytanie użytkownika) |

## Zakres

**W zakresie:** tabela `teams`, serwis `NominatimGeocoder`, formularz nazwa+miasto → lista kandydatów → zapis, middleware wymuszające drużynę na `/dashboard`, link nawigacyjny, pełna polska lokalizacja nowych widoków.

**Poza zakresem:** katalog/lista znanych drużyn, cache geokodowania współdzielony między użytkownikami lub między meczami (to S-03), throttling wywołań Nominatim, middleware na trasach innych niż `/dashboard`, usuwanie drużyny do stanu pustego.

## Architektura / Podejście

`TeamController` z trzema akcjami (`edit`, `search`, `store`) rozdziela krok wyszukania od kroku zapisu; wybrany kandydat jest przenoszony między nimi przez ukryte pola formularza (bez sesji). `NominatimGeocoder` jest samodzielnym serwisem w `app/Services/`, testowalnym przez `Http::fake()` — ten sam serwis będzie reużyty przy geokodowaniu miast meczów w S-03.

## Fazy w skrócie

| Faza | Co dostarcza | Kluczowe ryzyko |
| --- | --- | --- |
| 1. Model danych i serwis geokodowania | Tabela `teams`, relacja `User::team()`, `NominatimGeocoder` z testami jednostkowymi | Nominatim odrzuci żądanie bez poprawnego nagłówka `User-Agent` |
| 2. Formularz, potwierdzenie, wymuszone przekierowanie | Pełny przepływ `/team` → lista kandydatów → zapis, middleware blokujące `/dashboard`, link nawigacyjny, testy feature | Realny ruch sieciowy do Nominatim na produkcji (shared hosting) nie był wcześniej testowany — do zweryfikowania ręcznie w Fazie 2 |

**Wymagania wstępne:** S-01 (rejestracja i logowanie) — gotowe.
**Szacowany nakład pracy:** ~1 sesja pracy po godzinach, 2 fazy.

## Otwarte ryzyka i założenia

- Zakładamy, że hosting cyberFolks (shared, gdzie działa produkcja) pozwala na wychodzący ruch HTTPS do `nominatim.openstreetmap.org` — nie zweryfikowane wcześniej w `infrastructure.md`. Jeśli nie, formularz działałby lokalnie, ale nie na produkcji — do potwierdzenia przy weryfikacji ręcznej Fazy 2 na żywo.
- Istniejące konto produkcyjne z S-01 nie ma drużyny i zostanie przekierowane na `/team` przy pierwszym wejściu na `/dashboard` po tym wdrożeniu — zamierzone, nie regresja.

## Kryteria sukcesu (podsumowanie)

- Użytkownik bez drużyny nie widzi dashboardu — trafia na `/team`.
- Wpisanie miasta pokazuje kandydatów z prawdziwego Nominatim do wyboru, nie zapisuje niczego automatycznie.
- Po zapisaniu drużyny dashboard jest dostępny, a dane widoczne przy ponownym wejściu na `/team` do edycji.
