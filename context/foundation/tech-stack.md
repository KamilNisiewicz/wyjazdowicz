---
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
---

## Why this stack

Solo developer building a small single-user web app (Wyjazdowicz) in 3
weeks after-hours, with auth in scope. PostgreSQL was initially preferred but
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

**Update (2026-07-18, `/10x-infra-research`)**: the self-host target was
confirmed to be shared hosting (cyberFolks, DirectAdmin panel, CloudLinux) —
`pdo_pgsql` is not among the available PHP extensions (only `pdo_mysql` and
`pdo_sqlite`). Database was switched to **MySQL/MariaDB**, provisioned via
the DirectAdmin panel. See `context/foundation/infrastructure.md` for the
full hosting profile.
