# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Symfony 8.5 application built on `sumocoders/application-skeleton`, running PHP 8.5 (`.php-version`). Custom
application code under `src/`

Always prefix PHP/console commands with `symfony` (e.g. `symfony console ...`, `symfony php vendor/bin/phpunit`) so the
correct PHP version and `.env` variables are picked up.

## Commands

Local development:

```
symfony serve
symfony console sass:build --watch
```

Install/update dependencies (also installs the asset mapper and rebuilds sass, via `composer.json`'s `auto-scripts`):

```
symfony composer install
symfony composer update
```

Tests (PHPUnit 13 + `dama/doctrine-test-bundle`, which wraps each test in a rolled-back transaction):

```
symfony console doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration
symfony php vendor/bin/phpunit --color --testdox

# single test file
symfony php vendor/bin/phpunit tests/Path/To/SomeTest.php

# single test method
symfony php vendor/bin/phpunit --filter testMethodName tests/Path/To/SomeTest.php
```

No fixtures load by default; uncomment `doctrine:fixtures:load` in `.gitlab-ci.yml`'s test job if fixtures become
necessary.

Code quality (mirrors the `.gitlab-ci.yml` "code quality" stage; run these before considering PHP work done):

```
symfony php vendor/bin/mago --config=./mago.dist.toml fmt              # format (fix)
symfony php vendor/bin/mago --config=./mago.dist.toml fmt --check      # format (check only)
symfony php vendor/bin/mago --config=./mago.dist.toml lint --reporting-format=rich
symfony php vendor/bin/mago --config=./mago.dist.toml analyse --reporting-format=rich
symfony php vendor/bin/mago --config=./mago.dist.toml guard --reporting-format=rich   # structural rules (e.g. controller naming)

symfony php vendor/bin/phpcs --colors --report-full     # PSR-12, config/+public/+src/
symfony php vendor/bin/phpstan analyse                  # level 7, config/+public/+src/
symfony php vendor/bin/twig-cs-fixer lint templates/
```

Frontend/asset checks (Node-based, run via the `sumocoders/stylelint` and `sumocoders/standardjs` CI images, not
composer):

```
stylelint .
standard .
```

Dependency checks:

```
symfony composer audit
symfony composer outdated --strict
symfony console importmap:audit
symfony console importmap:outdated
```

## Architecture conventions

Namespaces mirror the directory structure, and feature subdirectories go **inside** the layer directory, never the
reverse:

```
src/Entity/Owner/Owner.php      → App\Entity\Owner\Owner      (correct)
src/Controller/Owner/Add.php    → App\Controller\Owner\Add    (correct)
src/Owner/Entity/Owner.php      → App\Owner\Entity\Owner      (wrong: feature wraps layer)
```

Apply this per layer: `Entity`, `Repository`, `Controller`, `Form`, `Enum`, `EventListener`, etc. Doctrine mapping
in `config/packages/doctrine.yaml` scans `src/Entity/` recursively, so nested feature folders are picked up
automatically.

- Controllers: invokable single-action controllers extending `AbstractController`, named `*Controller` (enforced by
  Mago's structural guard rules in `mago.dist.toml`).
- Use Symfony Messenger to decouple side effects from controllers instead of calling services directly. The `async`
  transport (`config/packages/messenger.yaml`) is Doctrine-backed in prod and consumed by a cron-triggered
  `messenger:consume` process (see Deployment below); emails (`SendEmailMessage`) are routed through it in prod.
  There's no `sync`/`async` routing configured yet for app messages, add entries under `messenger.routing` as they're
  introduced.
- Security: `config/packages/security.yaml` currently only has an in-memory user provider and no access control
  rules configured, real auth still needs to be wired up when a User entity/provider is added.
- Locale/mailer/site defaults are configured as parameters in `config/services.yaml` (`locale`, `fallbacks.site_title`,
  `mailer.default_*`), backed by env vars (`SITE_TITLE`, `MAILER_DEFAULT_*`).
- Frontend stack is Symfony UX: Stimulus (`symfony/stimulus-bundle`) for JS behavior, Turbo (`symfony/ux-turbo`) for
  navigation/streams, `symfonycasts/sass-bundle` for SASS compilation, and the Asset Mapper (no webpack/npm build
  step) for JS/CSS delivery, with Bootstrap 5 + Bootstrap Icons as the base UI library.
- Deployment is via `deployer-sumo` (`deploy.php`, `vendor/bin/dep`), CI auto-deploys `staging` branch to staging and
  `master` to production (see `.gitlab-ci.yml`). `.crontab` defines cron jobs (currently just the Messenger
  `async` consumer, running every 2 minutes) that get symlinked into place on deploy.

### Framework core bundle

`sumocoders/framework-core-bundle` provides most non-trivial functionality out of the box, check its docs
(`vendor/sumocoders/framework-core-bundle/docs/`) before building something it already offers: forms, pagination,
menus (KnpMenuBundle-based), breadcrumbs, mailing, file/image uploads, audit trails, encrypted entity fields,
autocomplete fields, PDF generation, and date pickers.

## Testing

PHPUnit with `dama/doctrine-test-bundle` (wraps each test in a transaction that's rolled back). Test suite lives
under `tests/`, mirrors `src/` structure.
