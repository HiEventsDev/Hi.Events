/* eslint-disable lingui/no-unlocalized-strings */
import path from "path";
import { promises as fsp } from "fs";
import express from "express";
import { installGlobals } from "@remix-run/node";
import process from "process";
import { fileURLToPath } from 'url';
import {createServer as viteServer} from "vite"
import compression from "compression";

installGlobals();

const root = process.cwd();
const isProduction = process.env.NODE_ENV === "production";

function resolve(p) {
  const __filename = fileURLToPath(import.meta.url);
  const __dirname = path.dirname(__filename);
  return path.resolve(__dirname, p);
}

async function createServer() {
  globalThis.window = {};
  const app = express();

  app.use((_req,_res,next)=>{
    console.log("test1")
    next()
  })
  /**
   * @type {import('vite').ViteDevServer}
   */
  let vite;

  if (!isProduction) {
    vite = await viteServer({
      root,
      server: { middlewareMode: "ssr" },
    });

    app.use(vite.middlewares);
  } else {
    app.use(compression());
    app.use(express.static(resolve("dist/client")));
  }

  app.use((_req,_res,next)=>{
    console.log("test2")
    next()
  })

  app.use("*", async (req, res) => {
    console.log("test2", req.originalUrl)

    const url = req.originalUrl;

    try {
      let template;
      let render;

      if (!isProduction) {
        template = await fsp.readFile(resolve("index.html"), "utf8");
        template = await vite.transformIndexHtml(url, template);
        const module = await vite.ssrLoadModule("src/entry.server.tsx");
        render = module.render;
      } else {
        template = await fsp.readFile(resolve("dist/client/index.html"), "utf8");
        const module = await import(resolve("dist/server/entry.server.js"));
        render = module.render;
      }

      try {
        const { appHtml, dehydratedState } = await render(req, res);
        const strifiedState = JSON.stringify(dehydratedState);
        console.log(appHtml)
        const html = template
          .replace("<!--app-html-->", appHtml)
          .replace(
            "<!--dehydrated-state-->",
            `<script>window.__REHYDRATED_STATE__ = ${strifiedState}</script>`
          );
        res.setHeader("Content-Type", "text/html");
        return res.status(200).end(html);
      } catch (e) {
        if (e instanceof Response && e.status >= 300 && e.status <= 399) {
          return res.redirect(e.status, e.headers.get("Location"));
        }
        throw e;
      }
    } catch (error) {
      if (!isProduction) {
        vite.ssrFixStacktrace(error);
      }
      console.log(error.stack);
      res.status(500).end(error.stack);
    }
  });

  return app;
}

createServer().then((app) => {
  app.listen(3000, () => {
    console.info("SSR Serving at http://localhost:3000");
  });
});
