# Road Routing Adapter Setup

This project ships with a local OSRM (Open Source Routing Machine) adapter that powers
road-based round ordering and full route polylines. The adapter is disabled automatically
when the backend is unreachable and falls back to the previous straight-line ordering.

## Preparing OSRM data

1. Ensure Docker and `curl` (or `wget`) are installed on the host.
2. Download and preprocess the desired map extract (Hungary by default):

```bash
scripts/osrm/setup.sh
```

The script downloads the extract into `data/osrm/` and runs `osrm-extract`,
`osrm-partition`, and `osrm-customize` using the official OSRM backend image.
Use the `OSRM_PBF_URL` environment variable to target a different region and
`OSRM_MAP` to change the output basename if needed.

## Starting the local routing backend

Start the OSRM backend with docker-compose:

```bash
docker compose -f docker-compose.osrm.yml up -d
```

The service listens on `http://127.0.0.1:5000` and exposes a `/health` endpoint that
is checked automatically both by docker and the frontend adapter. You can run a manual
health check with:

```bash
scripts/osrm/healthcheck.sh
```

## Configuration

The client reads routing configuration from `config/config.json` under the `routing`
section. The most important options are:

- `routing.enabled`: set to `false` to disable road-based sorting entirely.
- `routing.base_url`: the OSRM endpoint (defaults to `http://127.0.0.1:5000`).
- `routing.profile`: the OSRM profile to use (defaults to `driving`).
- `routing.request_timeout_ms`: request timeout in milliseconds.
- `routing.healthcheck`: optional health-check tuning (`path`, `timeout_ms`,
  `cache_ms`, `retry_ms`).
- `routing.cache`: in-browser plan cache configuration (`storage`, `max_entries`,
  `ttl_ms`).
- `routing.return_to_origin`: when `true`, routes finish at the starting point.

Any change to `routing.enabled` or clearing `routing.base_url` will cause the adapter
to stop calling OSRM and fall back to the legacy straight-line ordering automatically.
Cached plans expire after the configured TTL, ensuring the results stay fresh.

## Using OSRM ordering in the UI

- Rounds continue to default to the legacy straight-line ordering. No OSRM requests
  are issued unless a user explicitly chooses the **Útvonal (OSRM)** option in the
  round header's rendezés selector.
- When the route option is active the client requests an OSRM `trip` and renders the
  returned geometry on the map. The computed stop sequence is saved to the
  `round_meta.route_order` column so that other clients can reuse the result.
- If the OSRM backend is offline the UI automatically falls back to the straight-line
  nearest-neighbour ordering while keeping the last successful road order cached.
- Switching the selector back to **Alapértelmezett (légvonal)** restores the
  distance-based ordering without touching the persisted OSRM order.

## Disabling road routing quickly

1. Set `"routing": { "enabled": false, ... }` in `config/config.json`, or
2. Override `routing.base_url` with an empty string via runtime configuration, or
3. Ask users to pick **Alapértelmezett (légvonal)** in the rendezés selector per
   round.

Either change reverts the UI to the previous behaviour without requiring a rebuild.
