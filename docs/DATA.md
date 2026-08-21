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

## Post meta (`snel_newsletter` posts)

_Filled in as we walk through each folder._
