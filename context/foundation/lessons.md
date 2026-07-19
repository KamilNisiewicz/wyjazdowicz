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
