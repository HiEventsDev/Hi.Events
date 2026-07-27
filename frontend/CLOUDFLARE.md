# Cloudflare Workers (frontend SSR)

The frontend can run on Cloudflare Workers in addition to the default Node/Docker
runtime. The Laravel API is not deployed to Workers — point `VITE_API_URL_SERVER`
at a publicly reachable HTTPS API.

## Quick start

```bash
cp .dev.vars.example .dev.vars
# Edit .dev.vars with your public API URL and other VITE_* values

yarn install
yarn build
yarn build:worker
yarn dev:cf          # wrangler dev (local Workers runtime)
# or
yarn deploy:cf       # wrangler deploy
```

Set production secrets/vars with `wrangler secret put` / `wrangler.jsonc` `vars`.

Docker users are unchanged: `yarn start` still serves SSR via `@hono/node-server`.
