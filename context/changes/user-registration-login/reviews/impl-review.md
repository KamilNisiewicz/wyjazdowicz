<!-- IMPL-REVIEW-REPORT -->
# Przegląd implementacji: Rejestracja i logowanie użytkownika

- **Plan**: context/changes/user-registration-login/plan.md
- **Zakres**: Faza 2 z 2 (pełny przegląd planu)
- **Data**: 2026-07-19
- **Werdykt**: WYMAGA UWAGI
- **Ustalenia**: 0 krytycznych, 3 ostrzeżenia, 0 obserwacji

## Werdykty

| Wymiar | Werdykt |
|-----------|---------|
| Zgodność z planem | WARNING |
| Dyscyplina zakresu | WARNING |
| Bezpieczeństwo i jakość | PASS |
| Architektura | PASS |
| Spójność wzorców | PASS |
| Kryteria sukcesu | PASS |

## Ustalenia

### F1 — Polska lokalizacja UI nieudokumentowana w treści planu

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Dyscyplina zakresu
- **Lokalizacja**: context/changes/user-registration-login/plan.md (całość); dodane pliki `lang/pl/*.php`, `lang/pl.json`, `.env.example`, `resources/views/welcome.blade.php`
- **Szczegóły**: W trakcie Fazy 1 użytkownik zauważył, że domyślny UI Breeze jest po angielsku mimo że cały projekt jest po polsku. Dodano pełną lokalizację (4 pliki `lang/pl/*.php`, `lang/pl.json` z 27 kluczami, `APP_LOCALE=pl`) oraz poprawiono 3 twarde stringi w `welcome.blade.php`. To uzasadniona, dobrze wykonana praca (potwierdzona testami i ręczną weryfikacją), ale treść `plan.md` (Przegląd, Fazy, Referencje) nigdzie o niej nie wspomina — ktoś czytający sam plan bez historii konwersacji nie dowie się, skąd wzięła się lokalizacja.
- **Poprawka**: Dopisać krótki akapit-dodatek na końcu `plan.md` (np. nowa sekcja `## Dodatek: lokalizacja PL (odkryte podczas Fazy 1)`) opisujący decyzję i zakres, żeby przyszłe przeglądy/archiwizacja miały pełny obraz bez polegania na komunikatach commitów.
- **Decyzja**: FIXED — dodano sekcję `## Dodatek: lokalizacja PL` do `plan.md`

### F2 — Martwa logika zerowania `email_verified_at` po usunięciu weryfikacji e-mail

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🔎 ŚREDNI — prawdziwy kompromis; zatrzymaj się, aby to przemyśleć
- **Wymiar**: Zgodność z planem
- **Lokalizacja**: app/Http/Controllers/ProfileController.php:31-33
- **Szczegóły**: Plan (`## Podejście do implementacji`) jawnie deklaruje: "usuwamy (nie tylko wyłączamy) kod związany z resetem hasła i weryfikacją e-mail, żeby nie zostawiać martwych, nieosiągalnych tras". Mimo to `ProfileController::update()` nadal zeruje `email_verified_at` przy zmianie adresu e-mail — logika bez żadnego funkcjonalnego znaczenia, skoro `User` nie implementuje `MustVerifyEmail` i nie ma już żadnego mechanizmu weryfikacji. Test `ProfileTest::test_profile_information_can_be_updated` asertuje ten null, więc usunięcie wymaga też aktualizacji testu.
- **Poprawka A ⭐ Zalecane**: Usuń blok `if ($request->user()->isDirty('email')) { ... }` z `ProfileController::update()` oraz asercję `assertNull($user->email_verified_at)` w `ProfileTest.php` (zastąp asercją, że e-mail się zmienił).
  - Siła: Konsekwentne z deklarowaną w planie filozofią "usuwamy, nie ukrywamy"; eliminuje ostatni ślad nieistniejącej funkcji.
  - Kompromis: Dotyka testu, który dziś przechodzi — trzeba go świadomie zmienić, nie tylko kod produkcyjny.
  - Pewność: WYSOKA — kolumna `email_verified_at` w bazie zostaje (należy do bazowego schematu Laravela), tylko logika jej resetowania znika.
  - Martwy punkt: Brak znaczących.
- **Poprawka B**: Zostaw jak jest, jako neutralne przygotowanie na ewentualne włączenie weryfikacji e-mail w przyszłości (poza MVP).
  - Siła: Zero dodatkowej pracy teraz; nieszkodliwe, bo pole nie jest nigdzie odczytywane w logice biznesowej.
  - Kompromis: Techniczny dług sprzeczny z jawnie zadeklarowaną w planie zasadą "nie zostawiać martwego kodu" — może mylić przyszłego czytelnika kodu.
  - Pewność: ŚREDNIA — zależy, czy weryfikacja e-mail kiedykolwiek wróci.
  - Martwy punkt: Nie sprawdzono, czy `/10x-lesson`/roadmapa przewiduje powrót do weryfikacji e-mail w późniejszym module.
- **Decyzja**: SKIPPED — pozostawione bez zmian (neutralne przygotowanie na ewentualny powrót weryfikacji e-mail)

### F3 — Martwe tłumaczenia dla usuniętej funkcji resetu hasła

- **Ważność**: ⚠️ OSTRZEŻENIE
- **Wpływ**: 🏃 NISKI — szybka decyzja; poprawka jest oczywista i wąsko zakrojona
- **Wymiar**: Zgodność z planem
- **Lokalizacja**: lang/pl/passwords.php (cały plik, 5 kluczy: reset/sent/throttled/token/user)
- **Szczegóły**: Plik zawiera tłumaczenia komunikatów dla flow resetu hasła przez e-mail, który został fizycznie usunięty w Fazie 2 (trasy, kontrolery, widoki). Te tłumaczenia nie są już nigdzie używane — ten sam wzorzec "martwego kodu po usunięciu funkcji", co F2, tylko w warstwie lokalizacji.
- **Poprawka**: Usuń `lang/pl/passwords.php` (i odpowiadający mu `lang/en/passwords.php`, jeśli też ma zostać spójnie usunięty — Laravel nie wymaga jego obecności, gdy funkcja resetu hasła nie istnieje).
- **Decyzja**: SKIPPED — pozostawione bez zmian
