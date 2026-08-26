# Snel Newsletter: plan

Twee doelen, in deze volgorde. Doel 2 begint pas als doel 1 klaar is.

1. **Begrip.** Loc snapt elke map: wat zit erin, hoe ziet de data eruit, hoe stroomt het erin en eruit.
2. **Fixen.** Daarna de review-punten, rood eerst. Zie `ARCHITECTURE-REVIEW-2026-08-21.md`.

Regels
- Per map: Claude legt uit, Loc vat samen in eigen woorden, Loc zegt "volgende". Niet eerder.
- Al schrijvend bouwen we `docs/ARCHITECTURE.md` en `docs/DATA.md` op (formaat: spoorzeker).
- Elke keuze die we tegenkomen die niet uit de code blijkt: één regel in `docs/adr/SUMMARY.md`.
- Korte sessies. Eén of twee mappen per keer is genoeg.
- Eén ding tegelijk, superlangzaam. Per bestand of per blok: Claude toont het, we sparren, we schonen meteen op, dan pas het volgende. Geen grote leesstukken.
- Committen per afgerond blok, op Loc's teken.
- Docs groeien mee, niet achteraf. Bij elk blok dat we doorlopen: `docs/ARCHITECTURE.md` krijgt zijn alinea, `SOT:` markers komen op de canonieke plek in de code, en zodra data erbij komt (tabel, meta, flow) wordt `docs/DATA.md` in dezelfde stap bijgewerkt.
- Comments: alleen het *waarom* of een invariant die de code niet kan tonen. Nooit wat-een-regel-doet, geen sectiekoppen, geen herhaling van de functienaam. Claude voegt geen comment toe tenzij hij aan die regel voldoet.

## Doel 1: begrip (volg de data)

Volgorde is de route van een e-mail: binnenkomen, opslaan, versturen, terugmeten.

- [x] **0. Bootstrap**: hoe laadt alles, welke crons bestaan, welke tabellen zijn er
  - [x] `snel-newsletter.php`: alleen constants + requires. Updater, install en cron-self-heal naar `inc/core/`
  - [x] `inc/cpt.php`: gesplitst in `core/cpt.php` (post type + meta) en `core/editor.php` (Gutenberg)
  - [x] `inc/admin.php` → `core/admin.php`: error_logs weg. Stats-refresh blijft tot tracking.
- [x] **1. Subscribers** `inc/subscribers/`: tabellen `snel_subscribers`, `snel_subscriber_tags`, `snel_tag_rules`; statussen; import; dynamic tags
- [ ] **2. Bronnen** `inc/cpt-sources/`: hoe stromen subscribers binnen vanuit andere post types; auto-sync
- [ ] **3. Campaigns** `inc/campaigns/` + CPT-meta `_snel_nl_*`: wat is een campaign, welke meta, audience-keuze
- [ ] **4. Queue** `inc/queue/`: tabel `snel_send_queue`, statussen, publish → queue → batch, cron + watchdog
- [ ] **5. Warmup + lanes** `inc/warmup/`, `inc/lanes/`: daily cap, cooldown, broadcast vs automation lane
- [ ] **6. Verzenden** `inc/sender/`, `inc/adapters/`, `inc/ses/`: template, adapter-keuze, SES-call, headers
- [ ] **7. Tracking** `inc/tracking/`: tabel `snel_tracking`, pixel, click, unsubscribe, bounce-webhook
- [ ] **8. Automations** `inc/automations/`: tabellen runs/events, enrollment, tick, stappen
- [ ] **9. Rest** `inc/logger/`, `inc/settings/`, `inc/cli.php`
- [ ] **10. Frontend** `src/`: welke pagina's, hoe praten ze met de REST-routes, editor-sidebar

Per map, tijdens het doorlopen
- [ ] Comments opschonen: overbodige weg, goede "waarom"-comments laten staan. Dit hoort bij het begrijpen, niet bij het fixen.

Eindproduct van doel 1
- [ ] `docs/ARCHITECTURE.md` compleet (één alinea per map)
- [ ] `docs/DATA.md` compleet (tabellen + de 3 flows: campaign, automation, tracking)
- [ ] `docs/adr/SUMMARY.md` gestart
- [ ] `CLAUDE.md` in de plugin met werkregels (incl. de comment-regel hierboven)

## Doel 2: fixen (pas na doel 1)

Rood, voor de volgende campaign
- [ ] CLI `test-send` neutraliseren
- [ ] Queue-rows claimen (geen dubbel-verzend)
- [ ] Batch filtert op `status = active`; unsubscribe/bounce cancelt queue-rows
- [ ] SNS-webhook: signature vóór SubscribeURL
- [ ] Preview-tekst opslaan

Oranje, deze maand
- [ ] Publish-timeout: cooldown set-based + index
- [ ] Stats live uit tracking; `admin.php` N+1 weg
- [ ] Log-level + pruning
- [ ] Unsubscribe via POST-confirm
- [ ] Subscriber-delete cascade
- [ ] `set_tags` diff i.p.v. replace-all

Geel, architectuur (in deze volgorde)
- [x] Autoloader + `Plugin::boot()` (eigen autoloader in `core/autoload.php`, geen composer nodig): bootstrap wordt constants + één boot-call; `inc/cpt-sources/` → `inc/cpt_sources/` of namespace-uitzondering
- [ ] Grenzen doorvoeren in oude code: SQL alleen in Model (campaigns Controller), logica uit `index.php` (queue)
- [ ] Queue uit de request: publish zet vlag + cron-event, drainer doet het queuen
- [ ] Eén klok: UTC overal (`current_time` vs `NOW()` vs `strtotime`)
- [ ] Status-constanten i.p.v. losse strings (`Queue\Status::PENDING` …); "has work"-predicaat één keer
- [ ] `Processor::process_batch` splitsen (fetch / render / record)
- [ ] Dunne `Core\Model`-basisklasse zodra 3 Models doorlopen zijn. Geen query builder.
- [ ] Tests op 3 paden: audience-selectie, dubbel-verzend, unsubscribe
- [ ] `Subscribers\Model::list()` weg; Controller alleen via `query()`
- [ ] Frontend: één `api.js`, één `Modal`, console.logs weg

Niet doen (bewust)
- Query builder / ORM bovenop `$wpdb`
- Big-bang rewrite
- Frontend-router / global state; huidige `data-page`-mount volstaat

## Log
- 2026-08-21: plan gemaakt. Review afgerond. Nog niets gefixt.
- 2026-08-21: bootstrap opgeschoond, `inc/core/` gestart (updater, install, cron). Docs-skeletten + eerste ADR.
- 2026-08-21: subscribers doorlopen. Comments + types in alle 6 bestanden, SOT-markers op schema/model/controller/rest.
- 2026-08-26: autoloader + Plugin::boot. Bootstrap is 3 regels; alle require_once uit de index-bestanden. Getest op de lokale site.
