---
project: "Wyjazdowicz"
context_type: greenfield
created: 2026-07-15
updated: 2026-07-15
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "kategoria bólu"
      decision: "brak śledzenia danych to główny ból MVP; social/porównania to motywacja na przyszłość, nie zakres MVP"
    - topic: "zakres persony"
      decision: "jeden nazwany użytkownik (autor) na MVP; multi-user/monetyzacja odłożone"
    - topic: "auth strategy"
      decision: "logowanie (email+hasło / OAuth / magic link), model płaski bez ról na MVP"
  frs_drafted: 12
  quality_check_status: accepted
product_type: web-app
target_scale:
  users: small
timeline_budget:
  mvp_weeks: 3
  hard_deadline: 2026-08-10
  after_hours_only: true
---

# Notatki z sesji shape — Wyjazdowicz

## Wizja i problem

Kibic piłkarski jeżdżący na mecze wyjazdowe (i chodzący na domowe) po latach
kibicowania nie ma żadnego zapisu, na których meczach był, jaki dystans
pokonał dojeżdżając na wyjazdy, ani jaki jest jego osobisty bilans
zwycięstw/remisów/porażek na tych meczach. Dziś te dane po prostu nigdzie nie
istnieją — brak śledzenia oznacza, że fakty się zapominają i nie ma jak
spojrzeć wstecz na własną historię kibicowania.

Zwykły arkusz kalkulacyjny czy kalendarz tego nie załatwi: nie policzy sam z
siebie dystansu z domu do miasta meczu wyjazdowego, ani nie wyliczy
automatycznie, czy kibic jest "pechowy" (czy porażki dominują w jego
bilansie). To wymaga dedykowanej logiki, a nie tylko listy wierszy.

## Użytkownik i persona

**Persona główna**: kibic piłkarski jeżdżący na mecze wyjazdowe (autor
projektu, budujący to najpierw dla siebie). Chce zapisywać każdy mecz, na
którym był — domowy i wyjazdowy — a potem widzieć, na ilu meczach był, jaki
dystans pokonał na wyjazdach i jaki jest jego osobisty bilans W/D/L.

Zakres MVP to single-user: tylko ten jeden nazwany użytkownik. Porównywanie
się / dzielenie wynikami z innymi kibicami to realna motywacja stojąca za
chęcią posiadania tych danych, ale jest to świadomie odłożone — patrz
`## Non-Goals` (zostanie zapisane w Fazie 6). Ewentualna przyszła
monetyzacja została wspomniana, ale jest poza zakresem MVP.

## Access Control

Logowanie (email+hasło / OAuth / magic link — konkretny mechanizm to decyzja
techniczna, poza zakresem PRD). Model płaski: każdy zalogowany użytkownik
widzi i zarządza wyłącznie własnymi meczami i statystykami — brak ról na
MVP, ponieważ MVP zakłada jednego użytkownika.

Uwaga na przyszłość (poza PRD): jeśli produkt urośnie do wielu użytkowników,
konieczna będzie rola admina (np. do zarządzania bazą drużyn/stadionów) —
odłożone poza MVP.

## Success Criteria

### Primary
- Cały przepływ north-star działa od początku do końca: zalogowany
  użytkownik dodaje mecz (data, przeciwnik, dom/wyjazd, wynik, miejscowość),
  aplikacja automatycznie liczy dystans dla wyjazdów i aktualizuje
  statystyki (bilans W/D/L, % zwycięstw, passa, wskaźnik "pechowego
  kibica"), a użytkownik widzi listę meczów + panel statystyk.
- Mieści się w 3 tygodniach pracy po godzinach (potwierdzone przez
  użytkownika).

### Secondary
- Podział statystyk na mecze domowe vs. wyjazdowe (osobne widoki/liczby, nie
  tylko łączny bilans).
- Przyjemny, czytelny UI z wizualizacją statystyk (wykresy).

### Guardrails
- Poprawność obliczonego dystansu i bilansu — błędne obliczenia unieważniają
  całą wartość aplikacji.
- Prywatność danych — dane jednego użytkownika nigdy nie są widoczne dla
  innego.

## Functional Requirements

Użytkownik zapisuje mecze, na których osobiście był obecny (dom lub wyjazd).
Wskaźnik "pechowego kibica" liczony jest wyłącznie na podstawie własnego
bilansu W/D/L użytkownika — bez potrzeby znajomości ogólnego bilansu
drużyny (patrz `## Business Logic` niżej, decyzja z Fazy 5).

### Konto i drużyna
- FR-001: Użytkownik może się zarejestrować i zalogować. Priority: must-have
  > Socrates: Kontrargument rozważony: "to appka dla jednego użytkownika, po
  > co w ogóle konto zamiast lokalnego profilu bez auth?" Rozstrzygnięcie:
  > zostaje — użytkownik planuje przyszłą monetyzację/wielu użytkowników.
- FR-002: Użytkownik może ustawić swoją ulubioną drużynę. Priority: must-have
  > Socrates: Kontrargument rozważony: "kibic może kibicować więcej niż
  > jednej drużynie (klub + reprezentacja), model jednej drużyny jest zbyt
  > wąski." Rozstrzygnięcie: to realne ograniczenie, świadomie akceptowane
  > na MVP.

### Mecze
- FR-003: Użytkownik może dodać mecz, na którym był obecny (data, przeciwnik, dom/wyjazd, wynik, miejscowość). Priority: must-have
  > Socrates: Kontrargument pierwotnie rozważony przy wersji z wpisywaniem
  > WSZYSTKICH meczów drużyny (nie tylko swoich): "duży nakład pracy, może
  > zniechęcić do appki." Rozstrzygnięcie: nieaktualne — w Fazie 5 uproszczono
  > wskaźnik pecha tak, by nie wymagał danych o meczach drużyny, więc
  > użytkownik wpisuje wyłącznie mecze, na których był obecny.
- FR-004: Użytkownik może edytować dodany mecz. Priority: must-have
  > Socrates: Kontrargument rozważony: "czy edycja/usuwanie to naprawdę
  > must-have, czy można zacząć od append-only?" Rozstrzygnięcie: zostaje —
  > pomyłki przy wpisywaniu wyników są nieuniknione.
- FR-005: Użytkownik może usunąć dodany mecz. Priority: must-have
  > Socrates: (rozważone łącznie z FR-004, patrz wyżej).
- FR-006: Użytkownik widzi listę wszystkich dodanych meczów. Priority: must-have
  > Socrates: Kontrargument rozważony: "czy to nie duplikuje panelu
  > statystyk (FR-011)?" Rozstrzygnięcie: zostaje — lista pokazuje surowe
  > dane per mecz, panel pokazuje zagregowane liczby, to różne widoki.

### Dystans i statystyki
- FR-007: Aplikacja automatycznie oblicza dystans dom→miasto dla meczów wyjazdowych. Priority: must-have
  > Socrates: Kontrargument rozważony: "poleganie na zewnętrznym API
  > geokodowania to dodatkowa zależność i możliwe błędy przy niejednoznacznych
  > nazwach miast — czy nie prościej wpisać dystans ręcznie?" Rozstrzygnięcie:
  > zostaje — automatyzacja dystansu to sedno wartości appki, ręczne
  > wpisywanie byłoby pustym CRUD-em.
- FR-008: Aplikacja oblicza bilans W/D/L i % zwycięstw na meczach użytkownika. Priority: must-have
  > Socrates: Kontrargument rozważony: "czy bilans ma sens już od pierwszego
  > meczu, czy dopiero po większej próbce?" Rozstrzygnięcie: zostaje —
  > pokazujemy od razu, nawet przy małej próbce, użytkownik sam oceni
  > sensowność.
- FR-009: Aplikacja pokazuje aktualną passę (serię wyników) użytkownika. Priority: must-have
  > Socrates: Kontrargument rozważony: "przy rzadkich meczach wyjazdowych
  > passa może być mało znacząca statystycznie." Rozstrzygnięcie: zostaje —
  > to prosta i intuicyjnie wartościowa metryka.
- FR-010: Aplikacja oznacza użytkownika jako "pechowego kibica", jeśli porażki są najczęstszym wynikiem wśród jego meczów (więcej porażek niż zwycięstw i więcej porażek niż remisów). Priority: must-have
  > Socrates: Pierwotna wersja tego FR wymagała porównania z ogólnym
  > bilansem drużyny (wszystkie jej mecze, nie tylko te użytkownika); w
  > Fazie 5 uznano to za zbyt skomplikowane i niejasne w wyjaśnieniu, więc
  > regułę uproszczono do porównania samych trzech wyników użytkownika
  > (W vs D vs L) bez potrzeby zewnętrznych danych o drużynie.
- FR-011: Użytkownik widzi panel statystyk zbiorczych. Priority: must-have
  > Socrates: Kontrargument rozważony: "czy to nie za dużo liczb na raz na
  > MVP (bilans, dystans, passa, wskaźnik pecha)?" Rozstrzygnięcie: zostaje —
  > to właśnie ten panel jest north-star, okrajanie go osłabiłoby cel
  > projektu.
- FR-012: Statystyki są podzielone na mecze domowe vs. wyjazdowe. Priority: must-have
  > Socrates: Kontrargument rozważony: "czy to na pewno nice-to-have, skoro
  > projekt nazywa się Wyjazdowicz i kładzie nacisk na wyjazdy?"
  > Rozstrzygnięcie: podniesione z nice-to-have do must-have — podział
  > dom/wyjazd to część tożsamości produktu.

## User Stories

### US-01: Użytkownik dodaje mecz i widzi zaktualizowane statystyki

- **Given** zalogowany użytkownik z ustawioną ulubioną drużyną
- **When** dodaje mecz wyjazdowy, na którym był obecny (data, przeciwnik, miejscowość, wynik)
- **Then** aplikacja oblicza dystans dom→miejscowość meczu, aktualizuje jego bilans W/D/L, passę i wskaźnik pecha, a mecz pojawia się na liście meczów

#### Acceptance Criteria
- Dystans jest liczony automatycznie na podstawie miejscowości meczu i wyliczany tylko dla meczów wyjazdowych
- Bilans, passa i wskaźnik pecha aktualizują się natychmiast po zapisaniu meczu
- Wskaźnik pecha opiera się wyłącznie na własnym bilansie W/D/L użytkownika (bez danych o meczach drużyny, na których nie był)

## Business Logic

Aplikacja automatycznie oblicza dystans pokonany na każdy mecz wyjazdowy na
podstawie miejscowości meczu i, na podstawie własnego bilansu W/D/L
kibica, oznacza go jako "pechowego kibica", gdy porażki są najczęstszym
wynikiem wśród jego meczów.

Reguła konsumuje: miejscowość meczu (do dystansu) oraz wynik każdego
zapisanego meczu (do bilansu i wskaźnika pecha). Wynikiem jest: przeliczony
dystans w km na mecz wyjazdowy, zsumowany łączny dystans, oraz binarna
etykieta "pechowy kibic" / "niepechowy kibic" widoczna w panelu statystyk.
Użytkownik napotyka to w praktyce jako liczby i etykietę w panelu statystyk,
aktualizujące się automatycznie po każdym dodanym meczu — bez żadnej ręcznej
kalkulacji z jego strony.

## Non-Functional Requirements

- Użytkownik widzi potwierdzenie zapisania meczu i zaktualizowane statystyki w czasie odczuwalnym jako natychmiastowy (< 1s p95 dla typowych akcji: dodanie/edycja/usunięcie meczu).
- Aplikacja jest w pełni używalna z poziomu przeglądarki mobilnej (telefon), bez konieczności instalacji natywnej aplikacji.
- Dane jednego użytkownika nigdy nie są widoczne ani dostępne dla innego użytkownika (patrz też `## Access Control`).

## Non-Goals

- Brak gamifikacji / rankingu "najlepszego kibica" — appka nie porównuje użytkowników między sobą ani nie przyznaje odznak/punktów.
- Brak rywalizacji lub jakichkolwiek funkcji społecznościowych między kibicami różnych klubów — MVP jest ściśle single-user.
- Brak importu historycznych meczów z zewnętrznych źródeł — użytkownik wpisuje mecze wyłącznie ręcznie, appka nie integruje się z żadną zewnętrzną bazą wyników.
- Brak wsparcia dla wielu drużyn na jedno konto — jedna ulubiona drużyna per użytkownik na MVP (patrz FR-002 i jego runda sokratejska).
- Brak porównania osobistego bilansu z ogólnym bilansem drużyny — wskaźnik "pechowego kibica" uproszczono w Fazie 5 do reguły opartej wyłącznie na własnym W/D/L użytkownika.
- Brak trybu offline — aplikacja wymaga połączenia z internetem.

## Quality cross-check

Wszystkie elementy kontroli jakości obecne, brak luk:
- Access Control: present
- Business Logic: present
- Project artifacts: present
- Timeline-cost ack: present (mvp_weeks: 3, ≤ 3 tygodnie)
- Non-Goals: present (6 pozycji)
