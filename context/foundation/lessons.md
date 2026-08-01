# Lessons Learned

> Rejestr tylko do dodawania powtarzających się reguł i wzorców. Odczytywany ponownie na początku przez /10x-frame, /10x-research, /10x-plan, /10x-plan-review, /10x-implement, /10x-impl-review.

## AGENTS.md jako źródło prawdy, CLAUDE.md jako nakładka

- **Kontekst**: Każdy plik reguł AI w tym repo, niezależnie od fazy
- **Problem**: Realne reguły projektu (np. kompensacja quality_override) giną w szumie tabelek routera lekcji
- **Reguła**: Trzymaj AGENTS.md jako źródło prawdy reguł projektu; po zakończeniu łańcucha lekcji kursu przytnij CLAUDE.md do cienkiej nakładki z @AGENTS.md
- **Dotyczy**: all

## Symlinkuj repo/public/build do public_html/build na split-webroot hostingu

- **Kontekst**: Deploy na cyberFolks (split webroot: public_html/ vs repo/ poza docroot) — zmiany dotyczące assetów Vite
- **Problem**: Laravel czyta public_path() względem repo/public/, nie public_html/ — @vite() używa nigdy nieaktualizowanego manifestu, deploy "przechodzi", ale strona 404-uje na CSS/JS
- **Reguła**: Po pierwszym build assetów, zrób repo/public/build symlinkiem do public_html/build (lub odwrotnie) — nigdy nie zakładaj, że public_path() wskazuje katalog serwowany przez webserver
- **Dotyczy**: plan, implement, impl-review

## claude-code-action@v1 nie uruchomi się na PR-ze, który modyfikuje własny plik workflow

- **Kontekst**: `.github/workflows/review.yml` (10xChampion Faza D), każdy przyszły PR zmieniający ten plik
- **Problem**: `claude-code-action@v1` odmawia uruchomienia (cicho, `success` z ostrzeżeniem w logu, bez żadnego efektu) gdy plik workflow na branchu PR-a różni się od wersji na domyślnej gałęzi — zabezpieczenie przed eskalacją uprawnień przez modyfikację własnego workflow w PR-ze. Odkryte na żywo: PR z fixem `id-token: write` w `review.yml` dostał zielony job `impl-review`, ale bez żadnego komentarza/raportu — log: "Workflow validation failed... your workflow will begin working once you merge your PR."
- **Reguła**: Każda zmiana w `.github/workflows/review.yml` musi najpierw trafić na `master` (merge), zanim da się zweryfikować, że `claude-code-action`/skill `10x-impl-review-ci` faktycznie coś zrobił — test na tym samym PR-ze, który zmienia workflow, zawsze wygląda na "zielony", ale jest fałszywym negatywem
- **Dotyczy**: plan, implement, impl-review
