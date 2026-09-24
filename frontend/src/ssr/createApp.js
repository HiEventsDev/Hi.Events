import {Hono} from "hono";
import {assembleHtml} from "./assembleHtml.js";
import {parseCookies} from "./cookies.js";
import {pickViteEnv} from "./env.js";
import {
    sitemapEventsHandler,
    sitemapIndexHandler,
    sitemapOrganizersHandler,
} from "../sitemap/proxy.js";

/**
 * @typedef {{
 *   getTemplate: (c: import('hono').Context, url: string) => Promise<string>,
 *   getRender: (c: import('hono').Context) => Promise<(request: Request) => Promise<{
 *     appHtml: string,
 *     dehydratedState: unknown,
 *     helmetContext: object,
 *     themeColors?: unknown,
 *     statusCode?: number,
 *     renderErrors?: unknown[],
 *   }>>,
 *   getPublicEnv: (c: import('hono').Context) => Record<string, string>,
 *   base?: string,
 *   onSsrError?: (error: unknown, context: {url: string, source: string}) => void,
 *   onRenderErrors?: (errors: unknown[], context: {url: string}) => void,
 * }} CreateAppDeps
 */

const nonRenderablePathPattern = /(^|\/)\.[^/]|\.(php|asp|aspx|jsp|cgi|sql|bak|old|zip|tar|gz|rar|7z|env|ini|yml|yaml|conf|log|sh|exe|dll)$/i;

/**
 * Robots + sitemap routes. Register before static middleware so they win over
 * any checked-in public/robots.txt.
 * @param {import('hono').Hono} app
 * @param {CreateAppDeps} deps
 */
export function registerPublicRoutes(app, deps) {
    const {getPublicEnv} = deps;

    app.get("/robots.txt", (c) => {
        const publicEnv = getPublicEnv(c);
        const frontendUrl = publicEnv.VITE_FRONTEND_URL || new URL(c.req.url).origin;
        const robotsTxt = `User-agent: *
Allow: /

Sitemap: ${frontendUrl}/sitemap.xml
`;
        return c.text(robotsTxt, 200, {
            "Cache-Control": "public, max-age=86400",
        });
    });

    app.get("/sitemap.xml", async (c) => {
        return sitemapIndexHandler(getPublicEnv(c));
    });

    app.get("/sitemap-events-:page.xml", async (c) => {
        return sitemapEventsHandler(c.req.param("page"), getPublicEnv(c));
    });

    app.get("/sitemap-organizers-:page.xml", async (c) => {
        return sitemapOrganizersHandler(c.req.param("page"), getPublicEnv(c));
    });
}

/**
 * SSR catch-all. Register after static asset middleware.
 * @param {import('hono').Hono} app
 * @param {CreateAppDeps} deps
 */
export function registerSsrHandler(app, deps) {
    const {
        getTemplate,
        getRender,
        getPublicEnv,
        base = "/",
        onSsrError,
        onRenderErrors,
    } = deps;

    app.use("*", async (c, next) => {
        const pathname = new URL(c.req.url).pathname;
        if (nonRenderablePathPattern.test(pathname)) {
            return c.text("Not Found", 404);
        }
        return next();
    });

    app.all("*", async (c) => {
        const url = new URL(c.req.url);
        let pageUrl = url.pathname + url.search;
        if (base !== "/" && pageUrl.startsWith(base)) {
            pageUrl = pageUrl.slice(base.length - (base.endsWith("/") ? 1 : 0)) || "/";
        }

        try {
            const template = await getTemplate(c, pageUrl);
            const render = await getRender(c);
            const {
                appHtml,
                dehydratedState,
                helmetContext,
                themeColors,
                statusCode,
                renderErrors = [],
            } = await render(c.req.raw);

            if (statusCode >= 500 && renderErrors.length > 0 && onRenderErrors) {
                onRenderErrors(renderErrors, {url: pageUrl});
            }

            const cookies = parseCookies(c.req.header("cookie"));
            const html = assembleHtml(template, {
                appHtml,
                dehydratedState,
                helmetContext,
                themeColors,
                publicEnv: getPublicEnv(c),
                consentCookie: cookies.hi_cookie_consent,
            });

            return c.html(html, /** @type {import('hono/utils/http-status').ContentfulStatusCode} */ (statusCode || 200));
        } catch (error) {
            if (error instanceof Response) {
                if (error.status >= 300 && error.status < 400) {
                    const location = error.headers.get("Location") || "/";
                    return c.redirect(location, /** @type {300 | 301 | 302 | 303 | 304 | 307 | 308} */ (error.status));
                }
                return new Response(await error.text(), {
                    status: error.status,
                    headers: error.headers,
                });
            }

            onSsrError?.(error, {url: pageUrl, source: "ssr-render"});
            console.error(error);
            return c.text("Internal Server Error", 500);
        }
    });
}

/**
 * Register shared SSR routes on a Hono app (Node + Cloudflare Workers).
 *
 * For Node, prefer calling registerPublicRoutes → static middleware →
 * registerSsrHandler so assets do not shadow SSR (especially index.html).
 *
 * @param {CreateAppDeps} deps
 * @param {import('hono').Hono} [app]
 */
export function createApp(deps, app = new Hono()) {
    registerPublicRoutes(app, deps);
    registerSsrHandler(app, deps);
    return app;
}

/**
 * True when the path looks like a static file (has a basename with an extension).
 * Used so `/` and client-route paths fall through to SSR instead of index.html.
 * @param {string} pathname
 */
export function isStaticAssetPath(pathname) {
    if (pathname === "/index.html") {
        return false;
    }
    const basename = pathname.split("/").pop() || "";
    return basename.includes(".");
}

export {pickViteEnv};
