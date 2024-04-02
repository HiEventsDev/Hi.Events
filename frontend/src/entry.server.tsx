import type * as express from "express";
import * as React from "react";
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

export async function render(
  request: express.Request,
  response: express.Response
) {
  const { query, dataRoutes } = createStaticHandler(router);
  const remixRequest = createFetchRequest(request, response);
  const context = await query(remixRequest);

  // prefetch queries
  // await queryClient.prefetchQuery("test", async () => {
  //   const ipAPIResponse = await fetch("https://api.ipify.org?format=json");
  //   const ipAPIJSON = await ipAPIResponse.json();
  //   return ipAPIJSON;
  // });

  if (context instanceof Response) {
    throw context;
  }

  const routerWithContext = createStaticRouter(dataRoutes, context);
  const appHtml = ReactDOMServer.renderToString(
    <App queryClient={queryClient}>
      <StaticRouterProvider
        router={routerWithContext}
        context={context}
        nonce="the-nonce"
      />
    </App>
  );

  const dehydratedState = dehydrate(queryClient);

  return {
    appHtml,
    dehydratedState,
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
