const getBackendUrl = (env = {}) => {
    const backendUrl = env.VITE_API_URL_SERVER
        || (typeof process !== "undefined" ? process.env?.VITE_API_URL_SERVER : undefined);
    if (!backendUrl) {
        throw new Error("VITE_API_URL_SERVER environment variable is not set");
    }
    return backendUrl;
};

/**
 * Optional Sentry capture for Node. Avoid importing @sentry/node here so the
 * Workers bundle stays free of Node-only packages.
 * @param {unknown} error
 * @param {Record<string, unknown>} extra
 */
const captureSitemapError = async (error, extra) => {
    console.error(`Error fetching ${extra.errorContext}:`, error);
    if (typeof process === "undefined" || !process.env?.SENTRY_SSR_DSN) {
        return;
    }
    try {
        const Sentry = await import("@sentry/node");
        Sentry.captureException(error, {
            tags: {source: "sitemap-proxy"},
            extra,
        });
    } catch {
        // Sentry unavailable (e.g. Workers) — already logged above.
    }
};

const fetchSitemap = async (path, env, errorContext) => {
    try {
        const backendUrl = getBackendUrl(env);
        const response = await fetch(`${backendUrl}/public${path}`, {
            headers: {Accept: "application/xml"},
        });

        if (response.status === 404) {
            return new Response("Sitemap not found", {status: 404});
        }

        if (!response.ok) {
            console.error(`Error fetching sitemap ${path}: HTTP ${response.status}`);
            return new Response("Internal server error", {status: 500});
        }

        const body = await response.text();
        const headers = {"Content-Type": "application/xml"};
        const cacheControl = response.headers.get("cache-control");
        if (cacheControl) {
            headers["Cache-Control"] = cacheControl;
        }

        return new Response(body, {status: 200, headers});
    } catch (error) {
        await captureSitemapError(error, {errorContext, path});
        return new Response("Internal server error", {status: 500});
    }
};

const validatePageParam = (page) => {
    return Boolean(page && /^\d+$/.test(page));
};

export const sitemapIndexHandler = async (env) => {
    return fetchSitemap("/sitemap.xml", env, "sitemap index");
};

export const sitemapEventsHandler = async (page, env) => {
    if (!validatePageParam(page)) {
        return new Response("Invalid page parameter", {status: 400});
    }
    return fetchSitemap(`/sitemap-events-${page}.xml`, env, "sitemap events");
};

export const sitemapOrganizersHandler = async (page, env) => {
    if (!validatePageParam(page)) {
        return new Response("Invalid page parameter", {status: 400});
    }
    return fetchSitemap(`/sitemap-organizers-${page}.xml`, env, "sitemap organizers");
};
