# Snel Newsletter: architecture map

The map, not the book. One paragraph per domain plus where to dive in.
Depth lives in the code: grep `SOT:` for the canonical implementation of a pattern.

## Reading guide

| Question | Doc |
|---|---|
| Where does this pattern live? | grep `SOT:` in `inc/` and `src/`, map below |
| How does data move / what does it look like? | [DATA.md](DATA.md) |
| Why is it built this way? | [adr/SUMMARY.md](adr/SUMMARY.md) |
| What are we working on? | [PLAN.md](PLAN.md) |

## Core (plumbing)

**Bootstrap.** `snel-newsletter.php` defines three constants and requires every
module in a fixed order. No logic lives here.

**Updater.** `inc/core/updater.php`: update checks go through GitHub releases,
not wordpress.org. Needs `vendor/` from `composer install`.

**Install.** `inc/core/install.php` (`SOT:INSTALL`): one function calls every
module's `Install::create_tables()`. Runs on activation and on every version
bump (`snel_newsletter_db_version` option, checked on `admin_init`). dbDelta
makes it safe to re-run. Schema per table lives in each module's `Install.php`.

## Domains

_Filled in as we walk through each folder._
