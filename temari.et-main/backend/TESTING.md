# Running the test suite

```bash
composer test
```

789 tests, ~110s. Run this before every push — CI runs the same thing.

| Command | What it does |
|---|---|
| `composer test` | Parallel, 4 workers. The default. |
| `composer test:serial` | One process. Use when a failure looks like worker interference. |
| `composer test:edge` | Pins the clock into the Addis/UTC date-boundary window (see below). |
| `php artisan test tests/Feature/DemoSeederSmokeTest.php` | The demo world. Excluded from normal runs — seeds 10 schools, 2+ min. Run before a deploy. |

---

## Why `--processes=4` and not one-per-core

`RefreshDatabase` runs `migrate:fresh` once per worker process, and `migrate:fresh`
drops every table in a **single transaction**. Postgres takes an `ACCESS EXCLUSIVE`
lock on each table, index, TOAST table and TOAST index in that transaction:

```
156 tables + 505 indexes + 132 TOAST + ~132 TOAST indexes  ≈  1,070 locks
```

The lock pool is shared server-wide and fixed at boot:

```
max_locks_per_transaction × (max_connections + max_prepared_transactions)
             64            ×            (100 + 0)                        = 6,400 slots
```

`--parallel` with no `--processes` spawns one worker per core. On a 14-core machine
that is `14 × 1,070 ≈ 15,000` concurrent locks against a 6,400 slot pool, and the run
dies part-way with:

```
SQLSTATE[53200]: Out of memory: 7 ERROR: out of shared memory
HINT: You might need to increase "max_locks_per_transaction".
```

Note this reports as hundreds of *test errors*, not one infrastructure error, which
makes it look like the suite is broken when it is only the harness.

Four workers is `4 × 1,070 ≈ 4,280` — comfortably inside 6,400 with headroom for the
app's own connections.

### Want more workers?

Raise the pool, then raise the worker count. In `postgresql.conf`:

```conf
max_locks_per_transaction = 512
```

That takes a server restart (it sizes a shared-memory area at boot). At 512 the pool
is 51,200 slots and a full one-worker-per-core run fits with room to spare. CI already
does this — see `.github/workflows/backend.yml`, where the Postgres service container
is started with `-c max_locks_per_transaction=512` and the suite runs at full width.

---

## The Addis clock edge (`composer test:edge`)

The app clock is UTC. Every *school-day* judgement — attendance cutoffs, `NotPastDay`,
"is this due date in the past" — runs on **Addis wall time** via `App\Support\Ethiopia`.

Addis is UTC+3, so between **21:00 and 24:00 UTC** the UTC date is still the Addis day
*before*. A test that builds a date with `now()` instead of `Ethiopia::today()` therefore
passes for 21 hours a day and fails for 3 — and does it silently, because whoever wrote
it was not working at midnight Addis time.

`CLOCK_EDGE=1` pins the clock to 22:30 UTC (keeping the real date, shifting only the
time of day, so seeded relative dates still line up) and makes that whole class of bug
reproducible on demand. CI runs it as a separate job.

**Rule: any test that builds a date for a school-day field builds it with
`Ethiopia::today()` / `Ethiopia::now()`, never `now()` or `today()`.**
