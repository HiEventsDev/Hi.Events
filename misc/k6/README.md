# k6 load tests

Two scripts exercising the public ticket-buying API. Both default to the local dev stack
(`https://localhost:8443/api`, self-signed certs are skipped unless `INSECURE=false`).

| Script | What it does |
|---|---|
| `checkout-flow.js` | Full buyer funnel per iteration: event page → reserve (`POST /order`) → complete (free) or abandon → order summary (`GET /order?include=event`). Counts reservations, completions, sold-out rejections, 429s, 5xx, and per-step p95. Can create a capacity-capped product and assert nothing oversells. |
| `event-page.js` | Read-side only: hammers `GET /public/events/{id}` (optionally with `event_occurrence_id`, which bypasses the 2s quantity cache). |

## Setup

```bash
cd docker/development
docker compose -f docker-compose.dev.yml exec backend php artisan dev:bootstrap
```

That prints `single_event_id`, `free_product_id / price_id`, `recurring_event_id`, occurrence ids and a Bearer token.

The dev stack's `backend/.env` sets `APP_API_RATE_LIMIT_PER_MINUTE=100000`. Against any other environment, k6
runs from one IP and the default `api` throttle (180/min per IP) will 429 you almost immediately — either raise
that env var on the target or expect the `rate_limited` threshold to fail (which is itself a finding).

## Checkout funnel

Smoke (3 iterations, 1 VU) to prove the target is wired correctly:

```bash
EVENT_ID=123 SCENARIO=smoke k6 run misc/k6/checkout-flow.js
```

Spike — ramp to `RATE` new buyers/second over 20s, hold for `DURATION`, ramp down — against a freshly created
product capped at `CAPACITY` tickets (needs `TOKEN` so setup can create it). This is the oversell test: when the
run ends, `tickets_completed` must be ≤ `CAPACITY`, the teardown re-reads the product with the token and checks
`quantity_sold <= initial_quantity_available`, and the remaining buyers must have received sold-out 422s rather
than 5xx or timeouts.

```bash
EVENT_ID=123 TOKEN=... CAPACITY=200 RATE=40 DURATION=1m k6 run misc/k6/checkout-flow.js
```

Steady state at a fixed arrival rate against an existing product (auto-discovers the first in-stock FREE product,
or pin one with `PRODUCT_ID`/`PRICE_ID`; recurring events pick the first active occurrence or `OCCURRENCE_ID`):

```bash
EVENT_ID=123 SCENARIO=steady RATE=20 DURATION=2m k6 run misc/k6/checkout-flow.js
```

Variables: `BASE_URL`, `EVENT_ID` (required), `SCENARIO` (`smoke|steady|spike`, default `spike`), `RATE`
(arrivals/sec, default 30), `DURATION` (default `1m`), `MAX_QTY` (tickets per order, 1..N, default 2),
`ABANDON_RATIO` (share of buyers who abandon after reserving, default 0.1), `PRODUCT_ID`/`PRICE_ID`,
`OCCURRENCE_ID`, `TOKEN` + `CAPACITY` (create a capped product), `INSECURE` (default true).

Thresholds fail the run on any 429, any 5xx, any unexpected status, or p95 above 800ms (reads) / 1500ms
(reserve, complete). Expected sold-out 422s are counted in `sold_out_responses` and do not fail the run.

## Event page

```bash
EVENT_ID=123 RATE=100 DURATION=30s k6 run misc/k6/event-page.js
EVENT_ID=456 OCCURRENCE_ID=789 RATE=100 k6 run misc/k6/event-page.js   # cache-bypassing recurring path
```

## Reading results

- `step_reserve` p95 climbing with `RATE` while `step_event_page` stays flat → the per-event advisory lock in
  `CreateOrderHandler` is the bottleneck (all reservations for one event serialise behind it).
- `rate_limited > 0` → the per-IP `api` throttle is biting before the database does.
- `tickets_completed > CAPACITY` or the teardown oversell check failing → a capacity race (shared capacity
  assignments are the known weak spot; per-price/per-occurrence limits are enforced under the lock).
- Completed-order emails and event statistics are queued; watch the queue worker and Mailpit
  (`http://localhost:8025`) to see how far delivery lags behind completion.
