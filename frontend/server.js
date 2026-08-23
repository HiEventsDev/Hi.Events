import express from "express";
import {installGlobals} from "@remix-run/node";
import process from "process";
import compression from "compression";
import fs from "node:fs/promises";
import sirv from "sirv";
import cookieParser from "cookie-parser";
import path from "node:path";
import {fileURLToPath} from "node:url";
import * as nodePath from "node:path";
import * as nodeUrl from "node:url";
import "dotenv/config";
import {sitemapIndexHandler, sitemapEventsHandler, sitemapOrganizersHandler} from "./src/sitemap/proxy.js";
import {htmlSafeJsonStringify} from "./src/utilites/safeScriptJson.js";

installGlobals();

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

    const ssrManifest = isProduction
        ? await fs.readFile("./dist/client/.vite/ssr-manifest.json", "utf-8")
        : undefined;

    const app = express();
    app.use(cookieParser());

    app.use('/.well-known', express.static(path.join(__dirname, 'public/.well-known')));

    app.get('/widget.js', async (req, res) => {
        try {
            const widgetPath = isProduction
                ? path.join(__dirname, './dist/client/widget.js')
                : path.join(__dirname, './public/widget.js');
            const widgetJs = await fs.readFile(widgetPath, 'utf-8');
            res.setHeader('Content-Type', 'application/javascript; charset=utf-8');
            res.setHeader('Cache-Control', 'no-cache');
            return res.status(200).send(widgetJs);
        } catch (error) {
            return res.status(404).send('');
        }
    });

    const widgetTestPageEnabled = !isProduction || process.env.WIDGET_TEST_PAGE_ENABLED === 'true';

    if (widgetTestPageEnabled) {
        app.get('/widget-test', async (req, res) => {
            try {
                const widgetTestHtml = await fs.readFile(path.join(__dirname, './src/widget-test/index.html'), 'utf-8');
                res.setHeader('Content-Type', 'text/html; charset=utf-8');
                res.setHeader('Cache-Control', 'no-cache');
                res.setHeader('X-Robots-Tag', 'noindex');
                return res.status(200).send(widgetTestHtml);
            } catch (error) {
                return res.status(404).send('');
            }
        });
    }

    let vite;

    if (!isProduction) {
        const {createServer: viteServer} = await import("vite");

        vite = await viteServer({
            server: { middlewareMode: true },
            appType: "custom",
            base,
        });

        app.use(vite.middlewares);
    } else {
        app.use(compression());
        app.use(base, sirv(path.join(__dirname, "./dist/client"), { extensions: [] }));
    }

    const getViteEnvironmentVariables = () => {
        const envVars = {};
        for (const key in process.env) {
            if (key.startsWith('VITE_')) {
                envVars[key] = process.env[key];
            }
        }
        return htmlSafeJsonStringify(envVars);
    };

    app.get('/robots.txt', (req, res) => {
        const frontendUrl = process.env.VITE_FRONTEND_URL || `${req.protocol}://${req.get('host')}`;
        const robotsTxt = `User-agent: *
Allow: /

Sitemap: ${frontendUrl}/sitemap.xml
`;
        res.setHeader('Content-Type', 'text/plain');
        res.setHeader('Cache-Control', 'public, max-age=86400');
        res.status(200).send(robotsTxt);
    });

    app.get('/sitemap.xml', sitemapIndexHandler);
    app.get('/sitemap-events-:page.xml', sitemapEventsHandler);
    app.get('/sitemap-organizers-:page.xml', sitemapOrganizersHandler);

    app.use("*", async (req, res) => {
        const url = req.originalUrl.replace(base, "");

        try {
            let template;
            let render;

            if (!isProduction) {
                template = await fs.readFile(path.join(__dirname, "./index.html"), "utf-8");
                template = await vite.transformIndexHtml(url, template);
                render = (await vite.ssrLoadModule("/src/entry.server.tsx")).render;
            } else {
                template = templateHtml;
                render = (await dynamicImport(path.join(__dirname, "./dist/server/entry.server.js"))).render;
            }

            const { appHtml, dehydratedState, helmetContext, themeColors } = await render(
                { req, res },
                ssrManifest
            );
            const stringifiedState = htmlSafeJsonStringify(dehydratedState);
            const stringifiedThemeColors = htmlSafeJsonStringify(themeColors);

            const helmetHtml = Object.values(helmetContext.helmet || {})
                .map((value) => value.toString() || "")
                .join(" ");

            const envVariablesHtml = `<script>window.hievents = ${getViteEnvironmentVariables()};</script>`;

            const headSnippets = [];
            if (process.env.VITE_FATHOM_SITE_ID) {
                headSnippets.push(`
                <script src="https://cdn.usefathom.com/script.js" data-spa="auto" data-site="${process.env.VITE_FATHOM_SITE_ID}" defer></script>
            `);
            }

            const html = template
                .replace("<!--head-snippets-->", () => headSnippets.join("\n"))
                .replace("<!--app-html-->", () => appHtml)
                .replace("<!--dehydrated-state-->", () => `<script>window.__REHYDRATED_STATE__ = ${stringifiedState};window.__THEME_COLORS__ = ${stringifiedThemeColors}</script>`)
                .replace("<!--environment-variables-->", () => envVariablesHtml)
                .replace(/<!--render-helmet-->.*?<!--\/render-helmet-->/s, () => helmetHtml);

            res.setHeader("Content-Type", "text/html");
            return res.status(200).end(html);
        } catch (error) {
            if (error instanceof Response) {
                if (error.status >= 300 && error.status < 400) {
                    return res.redirect(error.status, error.headers.get("Location") || "/");
                } else {
                    return res.status(error.status).send(await error.text());
                }
            }

            console.error(error);
            res.status(500).send("Internal Server Error");
        }
    });

    app.listen(port, () => {
        console.info(`SSR Serving at http://localhost:${port}`);
    });

    const dynamicImport = async (path) => {
        return import(
            nodePath.isAbsolute(path) ? nodeUrl.pathToFileURL(path).toString() : path
        );
        
    }
}
main();
