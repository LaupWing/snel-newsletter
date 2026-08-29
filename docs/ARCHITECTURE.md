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

**Bootstrap.** `snel-newsletter.php` is three lines: constants, the autoloader
(`inc/core/autoload.php`, maps `Snel\Newsletter\Foo\Bar` to `inc/foo/Bar.php`),
and `Core\Plugin::boot()` (`SOT:BOOT`) — whose two lists ARE the plugin: core
files that always run, and one `index.php` per domain that wires its hooks.

**Updater.** `inc/core/updater.php`: update checks go through GitHub releases,
not wordpress.org. Needs `vendor/` from `composer install`.

**Cron.** `inc/core/cron.php`: the map of every background task (queue drainer,
watchdog, self-heals, automations tick, sources sync) and all their wiring.
Domain logic stays in the domains; this file only points at it.

**Install.** `inc/core/install.php` (`SOT:INSTALL`): one function calls every
module's `Install::create_tables()`. Runs on activation and on every version
bump (`snel_newsletter_db_version` option, checked on `admin_init`). dbDelta
makes it safe to re-run. Schema per table lives in each module's `Install.php`.

**Campaign post type.** `inc/core/cpt.php` (`SOT:CAMPAIGN-CPT`): a campaign is
a post of type `snel_newsletter`, not public, Gutenberg on. Four meta fields
decide the audience; see [DATA.md](DATA.md#post-meta-snel_newsletter-posts).

**Editor.** `inc/core/editor.php`: everything that bends Gutenberg for
campaigns. Redirect the default list to the React page, own block category,
whitelist of email-safe blocks, sidebar bundle with its start data, renamed
publish buttons.

**Admin.** `inc/core/admin.php`: the "Newsletter" menu with seven sub pages.
Each page is one empty div; `build/index.js` reads `data-page` and mounts the
matching React screen. REST url and nonce are passed via `snelNewsletter`.

## Domains

Every domain folder follows the same skeleton: `Install` (schema), `Model`
(all SQL, `SOT:MODEL`), `Controller` (request → Model → response,
`SOT:CONTROLLER`), `Rest` (routes + permissions, `SOT:REST`), `index.php`
(requires + `new Rest()`).

**Subscribers.** `inc/subscribers/`: the list and its tags. Three tables
(`SOT:SUBSCRIBER-SCHEMA`). Filter engine in `Model::build_conditions()` turns
UI filter rows into WHERE/HAVING/EXISTS; `ids_for_filters()` is what the queue
uses for custom-list campaigns. `Validator` rejects junk addresses on import.
Dynamic tags are rules in `snel_tag_rules`, recomputed by `sync_dynamic_tag()`.

**Warmup and lanes.** `inc/warmup/` + `inc/lanes/`: sending happens on two
lanes (broadcast on `mail.`, automation on `auto.`), each with its own sender
and daily budget. `Ramp` is the schedule (day 1 → 200 … day 8+ → unlimited),
`Guard` enforces it (daily counter, per-subscriber cooldown of 2 days),
`Settings` keeps the per-lane options. Known debt: `Guard::apply_cooldowns()`
loops per subscriber — the publish-timeout fix on the plan.

**CPT sources.** `inc/cpt-sources/`: pulls addresses that already live elsewhere
in WordPress (a post type, a custom table) into `snel_subscribers`. `Scanner`
discovers email-like fields by sampling, `Store` keeps one config per source in
a single option (shape in [DATA.md](DATA.md#source-configs)), `Importer`
(`SOT:IMPORT`) upserts idempotently, `AutoSync` runs every source hourly and on
`save_post`. Third-party code can push instantly via
`snel_newsletter_sync_source( $id )`.

**Campaigns.** `inc/campaigns/`: the list/dashboard REST layer over campaign
posts. `Model::list()` merges post data, cached stat meta and, for workflow
emails, live tracking stats; `cancel`/`resume` flip queue rows via the queue's
Processor. Known debt: `Controller::stats()` holds raw SQL and the list path
has N+1 stat queries (see PLAN.md).
