const getBackendUrl = (env = {}) => {
    const backendUrl = env.VITE_API_URL_SERVER
        || (typeof process !== "undefined" ? process.env?.VITE_API_URL_SERVER : undefined);
    if (!backendUrl) {
        throw new Error("VITE_API_URL_SERVER environment variable is not set");
    }
    return backendUrl;
};

const fetchSitemap = async (path, env) => {
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
        console.error(`Error fetching sitemap ${path}:`, error);
        return new Response("Internal server error", {status: 500});
    }
};

const validatePageParam = (page) => {
    return Boolean(page && /^\d+$/.test(page));
};

export const sitemapIndexHandler = async (env) => {
    return fetchSitemap("/sitemap.xml", env);
};

export const sitemapEventsHandler = async (page, env) => {
    if (!validatePageParam(page)) {
        return new Response("Invalid page parameter", {status: 400});
    }
    return fetchSitemap(`/sitemap-events-${page}.xml`, env);
};

export const sitemapOrganizersHandler = async (page, env) => {
    if (!validatePageParam(page)) {
        return new Response("Invalid page parameter", {status: 400});
    }
    return fetchSitemap(`/sitemap-organizers-${page}.xml`, env);
};
