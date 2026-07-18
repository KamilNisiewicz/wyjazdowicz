<!-- BEGIN @przeprogramowani/10x-cli -->

### Twarda zasada

Umiejętności nie mogą zapisywać do `context/archive/`. Zarchiwizowane zmiany są niezmienne; jeśli docelowa ścieżka zaczyna się od `context/archive/`, przerwij z komunikatem: „Ta zmiana jest zarchiwizowana. Otwórz nową zmianę za pomocą `/10x-new`.”

## 10xDevs AI Toolkit — Moduł 1, Lekcja 4

Wprowadź agenta do projektu, który utworzyłeś w Lekcji 3, za pomocą **łańcucha agent-context**:

```
(/10x-init  →  /10x-shape  →  /10x-prd  →  /10x-tech-stack-selector  →  /10x-bootstrapper)  →  /10x-agents-md  →  /10x-rule-review  →  /10x-lesson
```

Łańcuch PRD → tech-stack → bootstrap pochodzi z Lekcji 1–3 (ponownie dołączony, abyś mógł naprawić projekt w trakcie jego realizacji). `/10x-agents-md`, `/10x-rule-review` i `/10x-lesson` to główne tematy lekcji. Łańcuch rozszerza się w Lekcji 5 do etapu infrastruktury/wdrożenia.

### Router zadań — Od czego zacząć

| Umiejętność | Kiedy jej używać |
| --- | --- |
| **Kontekst agenta (główny temat lekcji)** | |
| `/10x-agents-md` | Repozytorium jest przygotowane, ale agent nie ma specyficznego dla projektu wprowadzenia. Sprawdza repozytorium (manifest pakietu, README, skrypty, konfigurację lint/test, układ, historię commitów) i zapisuje zwięzłe, uporządkowane „Wytyczne Repozytorium” do `AGENTS.md` (lub, gdy wywołane z podkatalogu, `AGENTS.md` na poziomie katalogu, przeformułowane wokół lokalnych konwencji i dominującej jednostki). Użyj jako alternatywy dla wbudowanego `/init` hosta lub jako rozwiązania awaryjnego dla narzędzi bez niego. Treść na poziomie repozytorium to około 200 linii; przewodniki na poziomie katalogu to 120–250 słów. |
| `/10x-rule-review <path>` | Masz plik z regułami dla AI (`AGENTS.md`, `CLAUDE.md`, `.cursor/rules/*.mdc`, `.github/copilot-instructions.md`, `.windsurfrules`, zagnieżdżone pliki dla poszczególnych obszarów) i chcesz uzyskać kartę wyników 5-osiową: długość, osadzone fragmenty kodu/konfiguracji, precyzja języka, redundancja z wiedzą publiczną i kolejność reguł. Niezależne od narzędzia — ocenia stan artefaktu, a nie projektu. Domyślny wynik jest tylko do odczytu; tylko Sprawdzenie 5 (zmiana kolejności) może edytować, i tylko za wyraźną zgodą. |
| `/10x-lesson [seed]` | Zauważyłeś powtarzającą się regułę, którą warto uwzględnić w przyszłych uruchomieniach `/10x-frame`, `/10x-research`, `/10x-plan`, `/10x-plan-review`, `/10x-implement` i `/10x-impl-review`. Dodaje pojedynczy wpis (Kontekst / Problem / Reguła / Dotyczy) do `context/foundation/lessons.md`. Samodzielnie inicjuje plik z kanonicznym nagłówkiem `# Lessons Learned` przy pierwszym użyciu. Tylko dodawanie — nigdy nie zmienia kolejności ani nie przepisuje wcześniejszych wpisów. |
| **W razie potrzeby uruchom ponownie upstream** | |
| `/10x-init` / `/10x-shape` / `/10x-prd` / `/10x-tech-stack-selector` / `/10x-bootstrapper` / `/10x-stack-assess` / `/10x-health-check` | Zestawione, abyś mógł naprawić PRD, zmienić stos technologiczny lub ponownie zainicjować projekt w trakcie jego realizacji. Jeśli `/10x-rule-review` zgłosi `FAIL`, którego nie możesz usunąć, często wskazuje to na niejednoznaczne decyzje dotyczące PRD lub stosu technologicznego — uruchom ponownie umiejętność upstream zamiast wypełniać `AGENTS.md` poprawkami. |

### Jak łańcuch przekazuje kontrolę

- `/10x-agents-md` zapisuje (lub chirurgicznie aktualizuje) `AGENTS.md` w określonym zakresie. Zakres na poziomie repozytorium = plik znajduje się w katalogu głównym repozytorium i obejmuje projekt jako całość; zakres na poziomie katalogu = plik znajduje się obok kodu, którym zarządza, i przeformułowuje go wokół lokalnej jednostki, całkowicie pomijając ramowanie na poziomie repozytorium. Umiejętność nigdy nie nadpisuje w ciszy — przełącza się na tryb aktualizacji, gdy cel istnieje.
- `/10x-rule-review` odczytuje dowolny plik markdown z regułami dla AI, na który wskażesz, i drukuje kartę wyników z 5 sprawdzeniami (`OK` / `WARN` / `FAIL`) z konkretnymi poprawkami. Nie zależy od tego, czy `/10x-agents-md` został uruchomiony; możesz przeglądać `.cursor/rules/`, instrukcje Copilota lub ręcznie napisany `CLAUDE.md` w ten sam sposób.
- `/10x-lesson` samodzielnie inicjuje `context/foundation/lessons.md` przy pierwszym użyciu, a następnie dodaje jeden wpis Kontekst/Problem/Reguła/Dotyczy na każde wywołanie. Plik jest konsumowany jako priorytet przez umiejętności fazy planowania i przeglądu wprowadzone później w przepływie pracy — `/10x-frame`, `/10x-research`, `/10x-plan`, `/10x-plan-review`, `/10x-implement`, `/10x-impl-review`.

### Co umiejętności lekcji przechwytują (a czego NIE)

- **`/10x-agents-md` przechwytuje**: strukturę projektu, polecenia build/test/lint faktycznie obecne w skryptach, konwencje commitów wywnioskowane z historii, pułapki specyficzne dla repozytorium, które agent w przeciwnym razie by przeoczył, odniesienia do kanonicznych plików za pomocą ścieżek `@` zamiast wklejania ich zawartości. Zakres na poziomie katalogu dodatkowo przechwytuje: lokalne wzorce nazewnictwa/układu wywnioskowane z sąsiadów, dozwolone/zabronione importy, wzorzec testowy używany przez sąsiadów i pułapki widoczne w bezpośrednim otoczeniu.
- **`/10x-agents-md` NIE** wkleja zawartości `tsconfig.json` / `eslint.config` / dokumentacji frameworka, którą agent już zna; NIE generuje ogólnych intencji „pisz czysty kod”; NIE zastępuje wbudowanego `/init` hosta, gdy taki istnieje — jest pozycjonowany jako alternatywa lub rozwiązanie awaryjne, a nie domyślne.
- **`/10x-rule-review` przechwytuje**: werdykt długości (OK ≤ 200 niepustych linii, WARN 201–500, FAIL 501+), bloki kodu/konfiguracji, które powinny być odniesieniami `@` zamiast tego, język o niejasnych intencjach, redundancję z dokumentacją frameworka, którą agent już ma z treningu, oraz propozycję zmiany kolejności Sprawdzenia 5, która umieszcza krytyczne reguły na górze.
- **`/10x-rule-review` NIE** edytuje pliku domyślnie; NIE ocenia zawartości projektu (architektury, wyborów stosu technologicznego) — ocenia stan artefaktu reguły; NIE generuje „naprawionej wersji” pliku (Sprawdzenie 5 może przenosić sekcje za wyraźną zgodą, nigdy nie przepisuje sformułowania reguły).
- **`/10x-lesson` przechwytuje**: jeden wpis na każde wywołanie z krótkim, imperatywnym tytułem H2 (tytuł JEST regułą), Kontekst (podsystem / faza / wzorzec pliku, wystarczająco specyficzny, aby pasować do wzorca), Problem (co konkretnie psuje się bez reguły, najlepiej z przeszłym incydentem), Reguła (1–2 imperatywne zdania, które można wkleić dosłownie do przyszłego wyniku przeglądu), Dotyczy (podzbiór `frame`, `research`, `plan`, `plan-review`, `implement`, `impl-review` lub `all`).
- **`/10x-lesson` NIE** edytuje ani nie usuwa istniejących lekcji — plik jest z założenia tylko do dodawania (przepisywanie powtarzających się reguł bez zastanowienia to tryb awarii, któremu zapobiega ta konwencja); NIE grupuje wielu reguł na jedno wywołanie; NIE wypełnia pól proaktywnie (użytkownik pisze — to cena za przechwytywanie reguł poza ustrukturyzowanym przeglądem).

### Test włączenia (filtr dla AGENTS.md / CLAUDE.md)

Zanim dodasz regułę do dowolnego pliku z regułami dla AI, zadaj sobie pytanie: *czy agent mógłby to wiedzieć bez tego pliku? Czy publiczne dane treningowe — książki, blogi, repozytoria w tym stosie — mogły go na to przygotować?* Jeśli tak, pomiń to. Jeśli nie, zachowaj. Plik jest wprowadzeniem dla agenta, który już zna TypeScript / Python / twój framework, ale NIE zna twoich lokalnych konwencji.

Należy:
- nieoczywiste konwencje projektu (kształt odpowiedzi na błąd, nazewnictwo plików, dozwolone ścieżki importu)
- pułapki specyficzne dla projektu i „kłopotliwe” obejścia związane z historią lub błędami zależności
- odniesienia do kanonicznych plików za pomocą ścieżek `@` (np. `@src/features/users/user.service.ts` jako odniesienie do wzorca, a nie wklejony kod)

NIE należy:
- główna dokumentacja frameworka
- zawartość README, którą agent i tak przeczyta (link za pomocą `@README.md`)
- popularne ogólne porady („używaj trybu ścisłego TypeScript”), które są już wymuszane przez konfigurację
- deklaracje intencji („pisz czysty kod”, „postępuj zgodnie z dobrymi praktykami”) — przekształć w sprawdzalne zachowanie lub pomiń

### U-kształtna uwaga i szczegółowe reguły

LLM-y zwracają największą uwagę na początek i koniec kontekstu (Lost-in-the-Middle / U-kształtna uwaga). Długi, monolityczny `CLAUDE.md` umieszcza swoje środkowe reguły w najsłabszej strefie uwagi. Dwie praktyczne konsekwencje:

1. **Najważniejsze reguły trafiają na początek** każdego pliku reguł.
2. **Reguły dla poszczególnych obszarów należą obok ich kodu** — zagnieżdżone `AGENTS.md` / `CLAUDE.md` wewnątrz `src/api/`, `.cursor/rules/*.mdc` z globami plików itp. Szczegółowe pliki są ładowane selektywnie i trafiają w całości na początek własnej sekcji, zamiast być zakopane na linii 400 jednego dużego pliku.

`/10x-rule-review` Sprawdzenie 5 (zmiana kolejności) operacjonalizuje konsekwencję (1); test włączenia plus `/10x-agents-md` na poziomie katalogu operacjonalizuje konsekwencję (2).

### Ćwiczenie kalibracyjne z pięcioma wzorcami

Przed napisaniem reguły sprawdź, czy agent faktycznie łamie konwencję bez niej. Wybierz jeden wzorzec z twojego projektu (kształt odpowiedzi na błąd, nazewnictwo plików, styl importu, struktura modułów, obsługa dat). Następnie:

1. Poproś agenta o zaimplementowanie wzorca 3–5 razy od czystego stanu, bez reguły.
2. Zauważ, gdzie złamał konwencję; zanotuj czas wykonania, eksplorowane pliki i widoczny koszt/tokeny, jeśli host je udostępnia.
3. Dodaj regułę składającą się z 1–3 zdań do odpowiedniego zakresu (root lub na poziomie obszaru).
4. Uruchom ponownie to samo zadanie w nowej sesji i porównaj zgodność z konwencją, czas, pliki i iteracje.

Jeśli agent już dąży do konwencji bez reguły, nie potrzebujesz tej reguły. Jeśli systematycznie wybiera niewłaściwy wzorzec, znalazłeś regułę o dużym wpływie do dodania. To ćwiczenie pokazuje, jak naprawdę wygląda „zdobywanie reguły z powtarzającej się awarii”.

### Hierarchia i interoperacyjność narzędzi

- **Claude Code** ładuje `CLAUDE.md` z katalogu użytkownika (`~/.claude/CLAUDE.md`), katalogu głównego repozytorium i dowolnego podkatalogu, w którym pracuje agent. Głębsze pliki nadpisują lub uzupełniają wyższe.
- **Codex** i **GitHub Copilot** ładują `AGENTS.md` z bieżącego katalogu w górę — wygrywa najbliższy plik.
- Jeden kanoniczny plik jest lepszy niż trzy duplikaty. Typowy wzorzec: `AGENTS.md` jako źródło prawdy, `CLAUDE.md` jako cienka nakładka Claude-Code z importem `@AGENTS.md`, `.github/copilot-instructions.md` tylko jeśli Copilot potrzebuje własnych dodatków. Dowiązanie symboliczne (`ln -s AGENTS.md CLAUDE.md`) jest najprostszym sposobem deduplikacji, gdy narzędzia wymagają obu nazw.
- Automatyczna pamięć (np. `~/.claude/projects/<dir-with-slashes-as-dashes>/memory/MEMORY.md` w Claude Code) jest lokalna dla maszyny i nie zastępuje `AGENTS.md`. Reguły wiążące zespół znajdują się w repozytorium; automatyczna pamięć to osobista pamięć podręczna, okresowo podlegająca przeglądowi.

### Haki pętli wewnętrznej (deterministyczna informacja zwrotna bez podpowiedzi)

Mechaniczne, niepodlegające wyborowi sprawdzenia należą do haków (np. `PostToolUse` w Claude Code), a nie do pliku reguł. Agent kończy edycję; uruchamia się formatter lub szybki linter; wynik jest przekazywany z powrotem bez przypominania. Szablon ustawień (`settings.json.template`) jest dostarczany w pakiecie lekcji jako punkt wejścia do okablowania. Proceduralne przepływy pracy (głębszy przegląd, lista kontrolna wydania, wdrożenie na sandboxie) zachowaj w umiejętnościach, a haki zarezerwuj dla deterministycznych sygnałów narzędzi.

### Ścieżki bazowe używane w tej lekcji

- `AGENTS.md` / `CLAUDE.md` (i warianty dla poszczególnych obszarów) — wynik `/10x-agents-md`
- `context/foundation/lessons.md` — wynik `/10x-lesson` (rejestr tylko do dodawania, konsumowany przez przyszłe umiejętności planowania/przeglądu)
- `context/foundation/prd.md`, `context/foundation/tech-stack.md` — dane wejściowe z wcześniejszych lekcji, nadal obecne
- `docs/reference/contract-surfaces.md` — rejestr nazw nośnych (utworzony przez `/10x-init`)

### Uniwersalny język

Dostarczone umiejętności nie zawierają odniesień do 10xDevs / kohorty / certyfikacji. `/10x-agents-md` odkrywa z repozytorium, w którym jest wywoływany; `/10x-rule-review` jest niezależny od narzędzi i traktuje każdy plik jako „artefakt reguł dla AI”; `/10x-lesson` zapisuje jeden kształt wpisu niezależnie od domeny projektu. Ćwiczenie kalibracyjne z 5 wzorcami jest ilustracyjne — zastąp wzorce z własnego stosu.

<!-- END @przeprogramowani/10x-cli -->
