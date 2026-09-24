/// <reference types="@cloudflare/workers-types" />
import {Hono} from "hono";
import {
    isStaticAssetPath,
    pickViteEnv,
    registerPublicRoutes,
    registerSsrHandler,
} from "./src/ssr/createApp.js";
import {render} from "./src/entry.server";

export type WorkerEnv = {
    ASSETS: Fetcher;
    [key: string]: string | Fetcher | undefined;
};

/**
 * Ensure SSR code that reads process.env (e.g. getConfig) sees Workers bindings.
 */
function applyEnvToProcess(env: WorkerEnv) {
    const viteEnv = pickViteEnv(env as Record<string, unknown>) as Record<string, string>;
    if (typeof process !== "undefined" && process.env) {
        Object.assign(process.env, viteEnv);
    }
}

const deps = {
    getPublicEnv: (c: {env: WorkerEnv}) => pickViteEnv(c.env as Record<string, unknown>) as Record<string, string>,
    getTemplate: async (c: {env: WorkerEnv; req: {url: string}}) => {
        const response = await c.env.ASSETS.fetch(new URL("/index.html", c.req.url));
        if (!response.ok) {
            throw new Error(`Failed to load index.html from Assets (${response.status})`);
        }
        return response.text();
    },
    getRender: async (c: {env: WorkerEnv}) => {
        applyEnvToProcess(c.env);
        return render;
    },
};

const app = new Hono<{Bindings: WorkerEnv}>();

registerPublicRoutes(app, deps);

// With run_worker_first, proxy hashed/static assets to the ASSETS binding.
app.use("*", async (c, next) => {
    const pathname = new URL(c.req.url).pathname;
    if (!isStaticAssetPath(pathname)) {
        return next();
    }
    const assetResponse = await c.env.ASSETS.fetch(c.req.raw);
    if (assetResponse.status !== 404) {
        return assetResponse;
    }
    return next();
});

registerSsrHandler(app, deps);

export default app;
