---
change_id: ui-redesign-daisyui
title: UI redesign with DaisyUI
status: implemented
created: 2026-08-08
updated: 2026-08-08
archived_at: null
---

## Notes

Kontekst: przebudowa UI odłożona wcześniej na "po 10xChampionie" (który jest teraz formalnie zamknięty). Użytkownikowi średnio podoba się wygląd apki, chce ją odświeżyć.

Decyzje ustalone z użytkownikiem przed startem tej zmiany:
- **Kit**: DaisyUI (nie Flowbite) — czysty plugin Tailwinda, zero własnego JS runtime, więc nie gryzie się z Alpine.js już używanym w projekcie (dropdown.blade.php, modal potwierdzenia usunięcia z S-04).
- **Zakres**: cały UI — auth (login/register/profile), dashboard, mecze (lista/create/edit/candidates), statystyki, layout/nawigacja. Nie tylko strona powitalna.
- **Workflow**: pełny cykl `/10x-new` → `/10x-research` → `/10x-plan` → `/10x-implement`, ale **jako jedna zmiana z jednym research.md i jednym plan.md** — świadomie NIE dzielimy na osobne mniejsze change foldery/PR-y per obszar (inaczej niż S-05/S-06). Plan może mieć wewnętrzne fazy/checklisty, ale to jeden cykl planowania i jedna implementacja.

Ważny fakt techniczny odkryty przy starcie tej zmiany (koryguje wcześniejsze założenie z `START-KONTEKST.md`): realnie zainstalowany jest **Tailwind CSS 3.4.19**, nie 4 — `@tailwindcss/vite@4` w `package.json` jest nieużywanym reliktem, `resources/css/app.css` używa klasycznych dyrektyw `@tailwind base/components/utilities`, `tailwind.config.js` jest w stylu v3 (JS config, nie CSS-first). To ma znaczenie dla wyboru wersji DaisyUI: **DaisyUI 4.x** jest kompatybilny z Tailwind 3 jako zwykły plugin w `tailwind.config.js` — zero migracji builda. DaisyUI 5 wymagałby migracji na Tailwind 4 — poza zakresem tej zmiany, chyba że research/plan zdecyduje inaczej.

Istniejące widoki do objęcia (23 pliki Blade), zob. `resources/views/` — auth/, components/, dashboard, layouts/, matches/, profile/, stats/, team/, welcome.
