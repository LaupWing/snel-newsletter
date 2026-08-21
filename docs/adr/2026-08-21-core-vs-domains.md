# Core vs domains

## Context
Everything lived flat under `inc/`. Plumbing (updater, table creation, admin
menu, cron self-heal) sat next to domain logic and partly inside the bootstrap
file, so the bootstrap did not show at a glance what the plugin is made of.

## Decision
`inc/core/` holds plumbing that always runs and knows nothing about the domain.
Every other folder under `inc/` is a domain with its own `index.php`. The
bootstrap only defines constants and requires files, in that order.

## Consequences
- Bootstrap reads as a table of contents.
- Moving a file into `core/` is allowed only if it has no domain logic.
- Migration is incremental: files move when we walk through them, not all at once.
