/* eslint-disable lingui/no-unlocalized-strings */
import express from "express";
import { installGlobals } from "@remix-run/node";
import process from "process";
import { createServer as viteServer } from "vite";
import compression from "compression";
import fs from "node:fs/promises";
import sirv from "sirv";

installGlobals();

const base = process.env.BASE || "/";
const port = process.argv.includes("--port")
  ? process.argv[process.argv.indexOf("--port") + 1]
  : process.env.PORT || 5678;
const isProduction = process.env.NODE_ENV === "production";

globalThis.window = {};

// Cached production assets
const templateHtml = isProduction
  ? await fs.readFile("./dist/client/index.html", "utf-8")
  : "";

const ssrManifest = isProduction
  ? await fs.readFile("./dist/client/.vite/ssr-manifest.json", "utf-8")
  : undefined;

const app = express();

let vite;

if (!isProduction) {
  vite = await viteServer({
    server: { middlewareMode: true },
    appType: "custom",
    base,
  });

  app.use(vite.middlewares);
} else {
  app.use(compression());
  app.use(base, sirv("./dist/client", { extensions: [] }));
}

app.use("*", async (req, res) => {
  const url = req.originalUrl.replace(base, "");

  try {
    let template;
    let render;

    if (!isProduction) {
      template = await fs.readFile("./index.html", "utf-8");
      template = await vite.transformIndexHtml(url, template);
      render = (await vite.ssrLoadModule("/src/entry.server.tsx")).render;
    } else {
      template = templateHtml;
      render = (await import("./dist/server/entry.server.js")).render;
    }

    const { appHtml, dehydratedState, helmetContext } = await render(
      { req, res },
      ssrManifest
    );
    const strifiedState = JSON.stringify(dehydratedState);
    const html = template
      .replace("<!--app-html-->", appHtml)
      .replace(
        "<!--dehydrated-state-->",
        `<script>window.__REHYDRATED_STATE__ = ${strifiedState}</script>`
      )
      .replace(
        "<!--render-helmet-->",
        `${Object.values(helmetContext.helmet || {})
          .map((value) => {
            return value.toString() || "";
          })
          .join(" ")}`
      );
    res.setHeader("Content-Type", "text/html");
    return res.status(200).end(html);
  } catch (error) {
    if (!isProduction) {
      vite.ssrFixStacktrace(error);
    }
    console.log(error.stack);
    res.status(500).end(error.stack);
  }
});

app.listen(port, () => {
  console.info(`SSR Serving at http://localhost:${port}`);
});
