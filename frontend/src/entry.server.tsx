import type * as express from "express";
import ReactDOMServer from "react-dom/server";
import { dehydrate } from "@tanstack/react-query";

import {
  createStaticHandler,
  createStaticRouter,
  StaticRouterProvider,
} from "react-router-dom/server";
import { router } from "./router";
import { App } from "./App";
import { queryClient } from "./utilites/queryClient";

const helmetContext = {};

export async function render(params: {
  req: express.Request;
  res: express.Response;
}) {
  const { query, dataRoutes } = createStaticHandler(router);
  const remixRequest = createFetchRequest(params.req, params.res);
  const context = await query(remixRequest);

  if (context instanceof Response) {
    throw context;
  }

  const routerWithContext = createStaticRouter(dataRoutes, context);
  const appHtml = ReactDOMServer.renderToString(
    <App queryClient={queryClient} helmetContext={helmetContext} token={params.req.cookies.token}>
      <StaticRouterProvider
        router={routerWithContext}
        context={context}
        nonce="the-nonce"
      />
    </App>
  );

  const dehydratedState = dehydrate(queryClient);

  return {
    appHtml:`<!-- SSR --!>${appHtml}`,
    dehydratedState,
    helmetContext,
  };
}

export function createFetchRequest(
  req: express.Request,
  res: express.Response
): Request {
  const origin = `${req.protocol}://${req.get("host")}`;
  // Note: This had to take originalUrl into account for presumably vite's proxying
  const url = new URL(req.originalUrl || req.url, origin);
  const controller = new AbortController();
  res.on("close", () => controller.abort());

  const headers = new Headers();

  for (const [key, values] of Object.entries(req.headers)) {
    if (values) {
      if (Array.isArray(values)) {
        for (const value of values) {
          headers.append(key, value);
        }
      } else {
        headers.set(key, values);
      }
    }
  }

  const init: RequestInit = {
    method: req.method,
    headers,
    signal: controller.signal,
  };

  if (req.method !== "GET" && req.method !== "HEAD") {
    init.body = req.body;
  }

  return new Request(url.href, init);
}
