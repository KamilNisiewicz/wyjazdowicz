---
bootstrapped_at: 2026-07-18T06:58:16Z
starter_id: laravel
starter_name: Laravel
project_name: wyjazdowicz
language_family: php
package_manager: composer
cwd_strategy: subdir-then-move
bootstrapper_confidence: verified
phase_3_status: ok
audit_command: "null"
---

## Hand-off

```yaml
starter_id: laravel
package_manager: composer
project_name: wyjazdowicz
hints:
  language_family: php
  team_size: solo
  deployment_target: self-host
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
  bootstrapper_confidence: verified
  path_taken: custom
  quality_override: true
  self_check_answers:
    typed: false
    from_official_starter: true
    conventions: false
    docs_current: true
    can_judge_agent: false
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: false
  has_background_jobs: false
```

## Why this stack

Solo developer building a small single-user web app (Wyjazdowicz) in 3
weeks after-hours, with auth in scope and PostgreSQL preferred. The user
chose the custom path to pick PHP explicitly over the JS-family recommended
default, since they work daily with PHP/Symfony professionally and want to
use this project to also pick up Laravel. Laravel is the only registered PHP
starter for `(web, php)` and clears three of four agent-friendly gates
(convention-based, popular in PHP training data, well-documented) but fails
`typed` (PHP does not enforce project-wide explicit types the way TypeScript
or Pydantic do) — `quality_override` is set because the user proceeded
consciously despite this, backed by real PHP/Symfony fluency that offsets
the self-check's low score on Laravel-specific unfamiliarity. Deployment
targets the user's own SSH-accessible server (self-host, one of Laravel's
supported deployment defaults). CI runs on GitHub Actions with
auto-deploy-on-merge, matching solo/small-team defaults and setting up the
later 10xChampion CI/CD review pipeline on the same provider.

## Pre-scaffold verification

| Signal             | Value                              | Severity | Notes                              |
| ------------------- | ----------------------------------- | -------- | ----------------------------------- |
| npm package        | not run                            | n/a      | language_family is php, not js     |
| GitHub repo        | not run                            | n/a      | card.docs_url is laravel.com/docs, not a github.com URL |

## Scaffold log

**Resolved invocation**: `composer create-project laravel/laravel .bootstrap-scaffold --no-interaction --prefer-dist`
**Strategy**: subdir-then-move
**Exit code**: 0
**Files moved**: 26 (app, artisan, bootstrap, composer.json, composer.lock, config, database, .editorconfig, .env, .env.example, .gitattributes, .npmrc, package.json, phpunit.xml, public, README.md, resources, routes, storage, tests, vendor, vite.config.js — top-level entries)
**Conflicts (.scaffold siblings)**: none
**.gitignore handling**: append-merged
**.bootstrap-scaffold cleanup**: deleted

## Post-scaffold audit

**Tool**: skipped — no built-in audit tool for php
**Recommended external tool**: Roave's `security-advisories` Composer plugin or local-php-security-checker

## Hints recorded but not acted on

| Hint                       | Value                              |
| --------------------------- | ------------------------------------ |
| bootstrapper_confidence    | verified                           |
| quality_override           | true                                |
| path_taken                 | custom                             |
| self_check_answers         | typed: false, from_official_starter: true, conventions: false, docs_current: true, can_judge_agent: false |
| team_size                  | solo                                |
| deployment_target          | self-host                          |
| ci_provider                | github-actions                     |
| ci_default_flow            | auto-deploy-on-merge               |
| has_auth                   | true                                |
| has_payments                | false                               |
| has_realtime                | false                               |
| has_ai                      | false                               |
| has_background_jobs         | false                               |

## Next steps

Next: a future skill will set up agent context (CLAUDE.md, AGENTS.md). For now, your project is scaffolded and verified — happy hacking.

Useful manual steps in the meantime:
- `git init` (if you have not already) to start your own repo history.
- Review any `.scaffold` siblings the conflict policy created and decide which version of each file to keep.
- Address audit findings per your project's risk tolerance — the full breakdown is in this log.
