# Cloudflare Workers (frontend SSR)

Deploy the **React SSR frontend** to Cloudflare Workers while keeping your existing
Laravel API wherever it already runs (Proxmox, VPS, Docker, etc.).

Laravel is **not** deployed to Workers. The Worker only serves HTML/JS and calls your
API over HTTPS.

## Architecture

```
Browser ──► Cloudflare Worker (SSR + static assets)
                │
                ├── VITE_API_URL_SERVER  (SSR / server-side fetches from the edge)
                └── window.hievents.VITE_API_URL_CLIENT  (browser XHR/fetch)
                         │
                         ▼
              Your existing Laravel API (public HTTPS)
```

| Variable | Used by | Must be |
|----------|---------|---------|
| `VITE_API_URL_SERVER` | Worker SSR (`getConfig` on the server) | Public HTTPS URL Cloudflare’s edge can reach (not `http://backend`) |
| `VITE_API_URL_CLIENT` | Browser (injected as `window.hievents`) | Public HTTPS URL the user’s browser can reach |
| `VITE_FRONTEND_URL` | Robots/sitemap and absolute frontend links | Your Worker URL (`*.workers.dev` or custom domain) |

For Hi.Events, both API URLs normally end with `/api` (same as Docker `.env.example`):

```text
VITE_API_URL_CLIENT=https://tickets.example.com/api
VITE_API_URL_SERVER=https://tickets.example.com/api
```

If your Laravel app is already exposed as `https://api.example.com` with `/api`
routed at the host root, use that host’s public base URL including `/api` if that
is how the axios clients are configured today.

## Laravel / API checklist (existing backend)

1. **Public HTTPS** — The API must be reachable from the internet (or at least from
   Cloudflare’s network). Docker-only names like `http://backend:80/api` will not work.
2. **CORS** — Browser calls use `withCredentials: true`. Set Laravel
   `CORS_ALLOWED_ORIGINS` to your Worker origin(s), e.g.
   `https://hievents-frontend.<account>.workers.dev,https://tickets.example.com`.
   Do not rely on `*` when credentialed requests are required.
3. **`APP_FRONTEND_URL`** — Point this at the Worker/custom-domain frontend URL so
   emails, invitations, and redirects target the right host.
4. **Cookies / auth** — Cross-site cookies need `Secure` and an appropriate `SameSite`
   policy if the frontend and API are on different sites. Same-site custom domains
   (e.g. `app.example.com` + `api.example.com`) are easier than `workers.dev` + a
   different apex.
5. **Stripe / webhooks** — Unchanged; they still hit Laravel, not the Worker.

## Quick start (local Worker → remote API)

```bash
cd frontend
cp .dev.vars.example .dev.vars
# Edit .dev.vars: public .../api URLs, VITE_FRONTEND_URL=http://localhost:8787

yarn install
yarn build:ssr:client   # static assets for Workers Assets
yarn build:worker       # bundles SSR for the Worker
npx wrangler dev        # or: yarn dev:cf (rebuilds client + worker, then wrangler dev)
```

`yarn dev:cf` runs `build:ssr:client`, `build:worker`, then `wrangler dev`.

## Production deploy

```bash
cd frontend
# Set vars (wrangler.jsonc "vars" and/or `wrangler secret put` for sensitive values).
# At minimum set VITE_API_URL_CLIENT, VITE_API_URL_SERVER, VITE_FRONTEND_URL
# to your production API and Worker/custom domain.

yarn deploy:cf          # yarn build && yarn build:worker && wrangler deploy
```

After the first deploy, set `VITE_FRONTEND_URL` to the real `*.workers.dev` or
custom domain and redeploy (or update vars in the dashboard) so robots/sitemap
and absolute links are correct.

Optional: attach a custom domain in the Cloudflare dashboard for the Worker.

## Docker / Node (unchanged)

`yarn start` / Docker SSR still use `@hono/node-server`. No Workers account required.
Use `frontend/.env` as before (`VITE_API_URL_SERVER` may stay as an internal hostname
inside Docker Compose).
