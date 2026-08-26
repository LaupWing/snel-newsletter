# Snel Newsletter: how the data works

Two layers: how data **moves** (the core flows) and what structures **look like**
(tables, post meta, options). Typed shapes live in the code; this file holds what
the code cannot show.

Rule: change a table, meta key or flow, update this file in the same change.

## Core flows

_Filled in as we walk through each folder._

## Tables

All prefixed with `{$wpdb->prefix}snel_`. Created by `SOT:INSTALL`
(`inc/core/install.php`); each module owns its schema in `Install.php`.

| Table | Owner | Holds |
|---|---|---|
| `subscribers` | subscribers | one row per email address, with status |
| `subscriber_tags` | subscribers | tag ↔ subscriber links |
| `tag_rules` | subscribers | manual vs dynamic tag definitions |
| `send_queue` | queue | one row per (campaign, subscriber) to send; warmup adds `delayed_until` |
| `tracking` | tracking | opens and clicks |
| `newsletter_logs` | logger | plugin log lines |
| `automations` | automations | automation definitions (steps as JSON) |
| `automation_runs` | automations | one row per subscriber enrolled in an automation |
| `automation_events` | automations | history per run |

Columns are documented per table as we walk through each module.

### subscribers (`SOT:SUBSCRIBER-SCHEMA`, `inc/subscribers/Install.php`)

| Table | Columns | Notes |
|---|---|---|
| `snel_subscribers` | `id`, `email` (unique), `name`, `status`, `unsubscribe_token`, `created_at` | `status`: `active`, `unsubscribed`, `bounced`, `complained`. Only `active` ever receives mail. |
| `snel_subscriber_tags` | `subscriber_id`, `tag` | unique per pair; one subscriber, many tags |
| `snel_tag_rules` | `tag` (unique), `type`, `metric`, `operator`, `threshold` | `type` `static` = assigned by hand; `dynamic` = auto-assigned when `metric operator threshold` holds, e.g. `open_rate > 50` |

## Source configs

One option (`snel_newsletter_cpt_sources`) holds every configured source, keyed
by id. Real example:

```
inquiry:                        <- the contact form post type
  kind:        cpt              <- or `custom` for an own table
  email_field: _inquiry_email   <- which meta key holds the address
  manual_tags: []               <- tags every import gets
  auto_sync:   true             <- hourly cron + on save_post
  last_result: {imported, tagged, skipped, invalid, junk}
```

Flow: new post/row -> `Importer` reads the email field -> junk check
(`Validator`) -> exists? tag only : create subscriber. Counters land in
`last_result`, shown on the Sources page.

## Post meta (`snel_newsletter` posts)

A campaign is a WordPress post of type `snel_newsletter` (`SOT:CAMPAIGN-CPT`,
`inc/core/cpt.php`). Extra fields live in `wp_postmeta`, all prefixed `_snel_nl_`.

Written by the editor sidebar (registered with `show_in_rest`):

| Meta | Type | Holds |
|---|---|---|
| `_snel_nl_tags` | array | chosen tags, e.g. `["all"]` |
| `_snel_nl_audience` | string | `tags` or `filters` |
| `_snel_nl_audience_filters` | array | filter rules for a custom list |
| `_snel_nl_is_workflow` | bool | campaign belongs to an automation |

These four decide who receives the campaign; the queue reads them at publish.
Meta written by PHP only (send status, counts) is documented with the queue.
