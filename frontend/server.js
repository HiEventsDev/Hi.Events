import {serve} from "@hono/node-server";
import {serveStatic} from "@hono/node-server/serve-static";
import {getRequestListener} from "@hono/node-server";
import {compress} from "hono/compress";
import {Hono} from "hono";
import {createServer as createViteServer} from "vite";
import {createServer as createHttpServer} from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import {fileURLToPath} from "node:url";
import * as nodePath from "node:path";
import * as nodeUrl from "node:url";
import process from "process";
import "dotenv/config";
import * as Sentry from "@sentry/node";
import {
    isStaticAssetPath,
    pickViteEnv,
    registerPublicRoutes,
    registerSsrHandler,
} from "./src/ssr/createApp.js";

async function main() {
    const base = process.env.BASE || "/";
    const port = process.argv.includes("--port")
        ? process.argv[process.argv.indexOf("--port") + 1]
        : process.env.NODE_PORT || 5678;
    const isProduction = process.env.NODE_ENV === "production";

    const __dirname = path.dirname(fileURLToPath(import.meta.url));

    const templateHtml = isProduction
        ? await fs.readFile("./dist/client/index.html", "utf-8")
        : "";

    let vite;

    if (!isProduction) {
        vite = await createViteServer({
            server: {middlewareMode: true},
            appType: "custom",
            base,
        });
    }

    const dynamicImport = async (modulePath) => {
        return import(
            nodePath.isAbsolute(modulePath)
                ? nodeUrl.pathToFileURL(modulePath).toString()
                : modulePath
        );
    };

    const deps = {
        base,
        getPublicEnv: () => pickViteEnv(process.env),
        getTemplate: async (_c, url) => {
            if (!isProduction) {
                let template = await fs.readFile(path.join(__dirname, "./index.html"), "utf-8");
                return vite.transformIndexHtml(url, template);
            }
            return templateHtml;
        },
        getRender: async () => {
            if (!isProduction) {
                return (await vite.ssrLoadModule("/src/entry.server.tsx")).render;
            }
            return (await dynamicImport(path.join(__dirname, "./dist/server/entry.server.js"))).render;
        },
        onRenderErrors: (renderErrors, {url}) => {
            renderErrors.forEach((renderError) => {
                Sentry.captureException(renderError, {
                    tags: {source: "ssr-loader"},
                    extra: {url},
                });
            });
        },
        onSsrError: (error, {url, source}) => {
            Sentry.captureException(error, {
                tags: {source},
                extra: {url},
            });
        },
    };

    const app = new Hono();

    app.get("/widget.js", async (c) => {
        try {
            const widgetPath = isProduction
                ? path.join(__dirname, "./dist/client/widget.js")
                : path.join(__dirname, "./public/widget.js");
            const widgetJs = await fs.readFile(widgetPath, "utf-8");
            return c.body(widgetJs, 200, {
                "Content-Type": "application/javascript; charset=utf-8",
                "Cache-Control": "no-cache",
            });
        } catch {
            return c.text("", 404);
        }
    });

    const widgetTestPageEnabled = !isProduction || process.env.WIDGET_TEST_PAGE_ENABLED === "true";

    if (widgetTestPageEnabled) {
        app.get("/widget-test", async (c) => {
            try {
                const widgetTestHtml = await fs.readFile(
                    path.join(__dirname, "./src/widget-test/index.html"),
                    "utf-8"
                );
                return c.html(widgetTestHtml, 200, {
                    "Cache-Control": "no-cache",
                    "X-Robots-Tag": "noindex",
                });
            } catch {
                return c.text("", 404);
            }
        });
    }

    app.use("/.well-known/*", serveStatic({root: "./public"}));

    // Dynamic robots/sitemap before static files (public/robots.txt would otherwise win).
    registerPublicRoutes(app, deps);

    if (isProduction) {
        app.use("*", compress());

        const clientStatic = serveStatic({root: "./dist/client"});
        app.use("*", async (c, next) => {
            const pathname = new URL(c.req.url).pathname;
            if (!isStaticAssetPath(pathname)) {
                return next();
            }
            return clientStatic(c, next);
        });
    }

    registerSsrHandler(app, deps);

    if (!isProduction) {
        const listener = getRequestListener(app.fetch);
        createHttpServer((req, res) => {
            vite.middlewares(req, res, () => listener(req, res));
        }).listen(port, () => {
            console.info(`SSR Serving at http://localhost:${port}`);
        });
        return;
    }

    serve({
        fetch: app.fetch,
        port: Number(port),
    }, () => {
        console.info(`SSR Serving at http://localhost:${port}`);
    });
}

main();
