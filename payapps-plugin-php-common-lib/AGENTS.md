# payapps-plugin-php-common-lib

> Agent entry point (Layer 2) for this repo. Cross-repo context, Git/MR conventions, and the `/review` + `/harness` gate live in the [ecosystem-harness AGENTS.md](https://gitlab.awx.im/ecosystem/ecosystem-harness/-/blob/master/AGENTS.md).

## What this plugin does

Shared PHP library (`airwallex/payapps-plugin-php-common-lib`) reused across the
Airwallex merchant-hosted PHP payment plugins (WooCommerce, PrestaShop, Magento,
etc.). It centralises the common code those plugins use to call the Airwallex
public payment API — gateway/HTTP client, configuration, caching, logging and
shared structs/use-cases — so each plugin does not reimplement it. Published as
a Composer package via the GitLab payapps package registry.

## Tech stack

- Language & build: PHP (composer, platform PHP 7.0; library type).
- Frameworks: none — plain PHP library (PSR-4 `Airwallex\PayappsPlugin\CommonLibrary\`), consumed by the plugin repos.
- Key integrations: Airwallex public payment API (Guzzle HTTP client).

## Build, test & lint

```bash
composer install                 # install deps
vendor/bin/phpunit tests         # PHP unit tests (phpunit)
sh scripts/check-coverage.sh     # enforce coverage threshold (as CI does)
```

CI (`.gitlab-ci.yml`) runs `composer install`, `vendor/bin/phpunit tests` and
`scripts/check-coverage.sh` in the `build-and-test` stage, then publishes the
Composer package to the GitLab registry on tag.

## Where things live

- `Gateway/` — Airwallex API gateway / HTTP client code.
- `UseCase/` — business use-cases shared by the plugins.
- `Struct/` — shared data structures / value objects.
- `Configuration/`, `Cache/`, `Logger/`, `Util/`, `Exception/` — config, caching, logging, helpers and exceptions.
- `tests/` — phpunit tests.

## Conventions

- All MRs must reference a Jira ticket and follow the [ecosystem-harness Git & MR conventions](https://gitlab.awx.im/ecosystem/ecosystem-harness/-/blob/master/AGENTS.md#git--mr-conventions).
- Commits follow Conventional Commits (commitlint); library must stay PHP 7.0 compatible for its downstream consumers.
