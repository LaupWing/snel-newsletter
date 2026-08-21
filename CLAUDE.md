# Snel Newsletter

Plan and progress: `docs/PLAN.md`. Read it first.

## Replies
- Dutch, short bullets, no long prose.
- One file or block per turn. Wait for Loc before moving on.

## Comments
- Concise, max 2 lines per block.
- Only above large blocks (class, function, distinct section). Never inside a function or next to a variable unless truly needed.
- A prompt hook in `.claude/settings.json` checks this after every edit.

## Structure
- `inc/core/` is plumbing (updater, install, admin, cpt, cron). Domain folders (`subscribers/`, `queue/`, ...) hold the logic.
- Bootstrap `snel-newsletter.php` only defines constants and requires files.

## Git
- Never commit during the day.
- Every change gets a section in `COMMITS.md` (commit message + files). At the end of the day we commit section by section, then remove it.
