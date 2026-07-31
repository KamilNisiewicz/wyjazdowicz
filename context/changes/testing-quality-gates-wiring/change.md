---
change_id: testing-quality-gates-wiring
title: Wire CI quality gates for test suite and frontend build
status: implemented
created: 2026-07-31
updated: 2026-07-31
archived_at: null
---

## Notes

Open a change folder for rollout Phase 4 of context/foundation/test-plan.md: "Quality-gates wiring".
Risks covered: #1 (auto-deploy-on-merge ships a bad change straight to production with no staging tier and no atomic rollback), #4 (a Tailwind/Vite frontend build silently fails to compile newly-used utility classes, so the UI looks broken in production). Test types planned: gates (CI).
Risk response intent:
- Risk #1: prove a merge with a failing test suite or a broken migration never reaches the git pull/migrate --force step on the server — the pipeline stops before anything changes on production; challenge "green php artisan test locally means the merge is safe" (there is today no CI workflow that runs the test suite as a gate before deploy, only the deploy workflow itself); avoid a test that only validates pipeline YAML syntax instead of actually requiring the suite to pass before deploy.
- Risk #4: prove that after adding a new Tailwind class to any Blade view and running npm run build, the compiled CSS actually contains that class — and CI/a local check flags a build run under the wrong Node version; challenge "the Blade code is correct, so the style will work" (this exact assumption broke in S-04: correct code, dead build); avoid relying only on remembering to export the right Node version by hand before every build (already failed once).
After creating the folder, follow the downstream continuation rule.
