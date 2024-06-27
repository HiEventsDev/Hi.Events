import type * as express from "express";
import ReactDOMServer from "react-dom/server";
import {dehydrate} from "@tanstack/react-query";

import {createStaticHandler, createStaticRouter, StaticRouterProvider,} from "react-router-dom/server";
import {router} from "./router";
import {App} from "./App";
import {queryClient} from "./utilites/queryClient";
import {setAuthToken} from "./utilites/apiClient.ts";
import {i18n} from "@lingui/core";

const helmetContext = {};

const getLocale = (req: express.Request): string => {
    if (req.cookies.locale) {
        return req.cookies.locale;
    }

    const acceptLanguage = req.headers['accept-language'];
    return acceptLanguage ? acceptLanguage.split(',')[0].split('-')[0] : 'en';
}

export async function render(params: {
    req: express.Request;
    res: express.Response;
}) {
    setAuthToken(params.req.cookies.token);

    const {query, dataRoutes} = createStaticHandler(router);
    const remixRequest = createFetchRequest(params.req, params.res);
    const context = await query(remixRequest);
    const locale = getLocale(params.req);

    if (context instanceof Response) {
        throw context;
    }

    i18n.activate(locale);

    const routerWithContext = createStaticRouter(dataRoutes, context);
    const appHtml = ReactDOMServer.renderToString(
        <App
            queryClient={queryClient}
            helmetContext={helmetContext}
            locale={getLocale(params.req)}
        >
            <StaticRouterProvider
                router={routerWithContext}
                context={context}
            />
        </App>
    );

    const dehydratedState = dehydrate(queryClient);

    return {
        appHtml: appHtml,
        dehydratedState,
        helmetContext,
    };
}

export function createFetchRequest(
    req: express.Request,
    res: express.Response
): Request {
    const origin = `${req.protocol}://${req.get("host")}`;
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
