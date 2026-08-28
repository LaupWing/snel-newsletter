# ADR summary

One line per decision. New decision = new file (`YYYY-MM-DD-slug.md`, sections
Context / Decision / Consequences) plus one line here. Never rewrite an ADR; a
changed mind is a new ADR.

- [2026-08-21 · Core vs domains](2026-08-21-core-vs-domains.md): `inc/core/` holds plumbing that always runs (updater, install, admin, cpt, cron); every other folder under `inc/` is a domain. The bootstrap only defines constants and requires files.
- [2026-08-28 - Cron map in core](2026-08-28-cron-map-in-core.md): alle cron-bedrading in core/cron.php met een kaart bovenaan; logica blijft in de domeinen
