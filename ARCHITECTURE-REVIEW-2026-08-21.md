# Snel Newsletter — Architectuur-review (21 aug 2026, v1.9.11)

Status: **geparkeerd** — input voor de geplande refactor-sessie. Niets hiervan is al gefixt.
Omvang: ~16k regels (PHP + React), 87 commits, geen tests, geen linter.

## Oordeel
Structuur is **goed genoeg om op door te bouwen** (7/10). Consistent module-skelet
(`inc/<feature>/{Install,Model,Controller,Rest,index.php}`), namespaces kloppen, comments leggen
*waarom* uit, security-basis is op orde (prepared SQL, capability checks, HMAC-gesigneerde
click-links, SNS-verificatie, SigV4 correct). De automation-engine is het best ontworpen stuk
(expliciete state machine, idempotentie via UNIQUE keys).
Zwak: concurrency (geen row-claim), late status-wijzigingen worden genegeerd, geen tests/lint,
frontend dupliceert de API-helper 12×, Tailwind-preflight lekt in wp-admin.

## 🔴 Direct (veiligheid / geld / reputatie)
1. **CLI `test-send` mailt je hele lijst.** `inc/cli.php:112` → `queue_campaign($id)` zonder tags/filters → "iedereen"-branch (`Processor.php:68`). 50 gaan via LogAdapter, de rest via cron op SES. → Commando neutraliseren (sentinel-tag + weigeren op `production`).
2. **Dubbel verzenden mogelijk.** `Processor.php:243-254` selecteert 50 pending rows, markeert pas `sent` ná de SES-call; geen claim. Overlappende cron-runs (50×30s timeout > 60s cron-lock) pakken dezelfde rows. → Atomische claim (`UPDATE … SET status='processing'`) + stale-sweeper.
3. **Uitgeschreven/gebouncede subscribers krijgen alsnog mail.** Batch-query heeft geen `s.status='active'`; unsubscribe/bounce wijzigt alleen subscriber-status, cancelt geen queue-rows. Met warmup-spreiding over dagen is dit reëel. → Predicaat toevoegen + rows cancelen bij unsubscribe/bounce.
4. **SSRF in SNS-webhook.** `SESAdapter.php:58-64` doet `wp_remote_get(SubscribeURL)` vóór signature-check. → Eerst verifiëren, URL pinnen op `https://sns.<region>.amazonaws.com/`.
5. **Preview-tekst wordt weggegooid.** `NewsletterSidebar.js:272,326` houdt `previewText` alleen in state; `_snel_nl_preview_text` is nooit geregistreerd met `show_in_rest`. Mails gaan zonder preheader. → `register_post_meta` + `editPost`.

## 🟠 Deze maand
6. Publish-timeout: `Guard::apply_cooldowns` per-subscriber loop + **ontbrekende index** op `snel_send_queue(subscriber_id, status, sent_at)` → set-based UPDATE + index + queueing naar cron.
7. Stats: `admin.php:55-67` draait N+1 over álle campaigns op elke admin-pagina; `Processor` ververst alleen tijdens batches. → Live uit `snel_tracking` lezen (één GROUP BY), admin.php-blok weg.
8. Logtabel groeit onbeperkt; `debug` + per-mail `info` in productie; `download_logs` laadt alles in memory. → Min-level, per-mail-log weg, dagelijkse prune, chunked CSV.
9. Unsubscribe op GET (link-scanners schrijven mensen uit) + `unsubscribe_token` niet geïndexeerd. → Confirm-pagina met POST-knop, UNIQUE index.
10. Subscriber verwijderen laat queue-rows wees → drainer telt ze als pending, campaign blijft eeuwig `sending`. → Cascade delete/cancel.
11. `set_tags` (replace-all) wist dynamic tags en vuurt triggers voor álle tags → log-vervuiling, automations. → Diff oud/nieuw.
12. Retry na HTTP-timeout kan dubbel sturen (SES accepteerde al). → Timeout ≠ retry.
13. Automation `tick` zonder claim/lock (zelfde klasse probleem als #2).
14. Tag rename/delete niet doorgezet naar automations-trigger, steps-JSON, campaign-meta.
15. Open-pixel is ongesigneerd (stats te vervalsen); click wél. → Zelfde HMAC.
16. Tailwind `@import "tailwindcss" important` unscoped in wp-admin; FilterBar in editor-sidebar ongestyled (geen Tailwind in editor.css).
17. Unhandled promise rejections: `saving`/`importing` blijven hangen bij fout (Builder, Settings, ImportCSVModal).

## 🟡 Refactor-sessie
- PSR-4 autoloader + `Plugin::boot()` i.p.v. volgorde-gevoelige `require_once`-keten en side-effect-instantiatie in `index.php`.
- Status-constanten (`Queue\Status::PENDING` …) i.p.v. magic strings; "has work"-predicaat staat 3× gekopieerd (root, watchdog, Processor); stats-refresh 3×; twee test-mail-implementaties.
- `Processor::process_batch` (~220 regels) splitsen in `fetch_batch / render_for_row / record_result`.
- Eén klok: UTC overal (`current_time` vs `NOW()` vs `strtotime('tomorrow')` lopen door elkaar; warmup-cap kan 24u extra parkeren).
- Warmup daily counter: atomisch (`option_value + 1`) of tellen uit queue.
- Schema: `delayed_until` in queue-CREATE i.p.v. hand-ALTER in Warmup\Install; migraties ook buiten admin_init.
- AWS-secret: `SNEL_SES_SECRET_KEY` constant in wp-config, option `autoload=false`.
- Frontend: één `src/lib/api.js` (`@wordpress/api-fetch`), één `Modal`-component (a11y), key-by-id op lijsten, console.logs/error_logs weg, `wp_set_script_translations`, block.json naar `build/`, editor-preview via bestaande `/preview`-endpoint, test-send via `savePost()` i.p.v. DOM-click + sleep.
- Dead code: `Tracking\Model::subscriber_stats`, `Lane::for_campaign`, `AdapterInterface::fetch_stats`, `Subscribers\Model::list`, "Extract names with AI" (is een regex met sleep).
- Tooling: phpunit (vangnet: audience-selectie, dubbel-verzend, unsubscribe), phpcs/phpstan, eslint/prettier, `package.json` versie gelijktrekken.

## Wat al goed is (niet weggooien)
Cron-durability (self-reschedule + watchdog + self-heal), audience-safety-net (nooit doorvallen naar "iedereen" bij tags-mode), List-Unsubscribe one-click, header-injection-bescherming in SES-client, adapter-abstractie + LogAdapter, cpt-sources factoring (Scanner/Importer/Store/Providers), Gutenberg meta-plumbing, server-rendered blocks.
