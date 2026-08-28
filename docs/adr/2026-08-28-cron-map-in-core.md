# Cron wiring lives in core/cron.php

## Context
Five background tasks were wired across four files. Understanding what runs
when nobody is looking required knowing all of them; overlap between the queue
drainer and its watchdog caused a double-send risk that was hard to see.

## Decision
All cron scheduling and hook-to-handler bindings live in `inc/core/cron.php`,
which opens with a map of every background task. Domain logic stays in the
domains (`Processor::watchdog()`, `Engine::tick()`); core only points at it.
Exception: `CptSources\AutoSync` wires itself (instance hooks); the map lists it.

## Consequences
- One 60-line file answers "what runs in the background".
- A module's index.php wires only request-time hooks, never cron.
- Deviates from "modules wire themselves" for cron specifically; this ADR is
  the record of why.
