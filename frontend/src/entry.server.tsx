import type * as express from "express";
import {AsyncLocalStorage} from "node:async_hooks";
import ReactDOMServer from "react-dom/server";
import {i18n} from "@lingui/core";
import {dehydrate, QueryClient} from "@tanstack/react-query";

import {router} from "./router";
import {App} from "./App";
import {createStaticHandler, createStaticRouter, StaticRouterProvider} from "react-router";
import {dynamicActivateLocale} from "./locales.ts";
import {SsrRequestContext, setSsrRequestContextProvider} from "./utilites/ssrRequestContext.ts";
import {generateThemeColors} from "./utilites/themeColors.ts";
import {prefetchInstanceInfo} from "./ee/licensing/queries/prefetchInstanceInfo.ts";

const themeColors = generateThemeColors();

const requestContext = new AsyncLocalStorage<SsrRequestContext>();

setSsrRequestContextProvider(() => requestContext.getStore());

const getLocale = (req: express.Request): string => {
    if (req.cookies.locale) {
        return req.cookies.locale;
    }

    const acceptLanguage = req.headers['accept-language'];
    return acceptLanguage ? acceptLanguage.split(',')[0].split('-')[0] : 'en';
}

interface RenderParams {
    req: express.Request;
    res: express.Response;
    backendHeaders?: Record<string, string>;
    licenceSimulation?: string;
}

export async function render(params: RenderParams) {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60 * 1000,
                refetchOnWindowFocus: false,
                networkMode: "always",
            },
            mutations: {
                networkMode: 'always',
            }
        },
    });

    const context: SsrRequestContext = {
        queryClient,
        authToken: params.req.cookies?.token || undefined,
        backendHeaders: params.backendHeaders ?? {},
        licenceSimulation: params.licenceSimulation,
    };

    return requestContext.run(context, () => renderWithinContext(params, queryClient));
}

async function renderWithinContext(params: RenderParams, queryClient: QueryClient) {
    await prefetchInstanceInfo(queryClient);

    const helmetContext = {};

    const {query, dataRoutes} = createStaticHandler(router);
    const remixRequest = createFetchRequest(params.req, params.res);
    const context = await query(remixRequest);

    if (context instanceof Response) {
        throw context;
    }

    const locale = await dynamicActivateLocale(getLocale(params.req));

    const routerWithContext = createStaticRouter(dataRoutes, context);

    i18n.activate(locale);
    const appHtml = ReactDOMServer.renderToString(
        <App
            queryClient={queryClient}
            helmetContext={helmetContext}
            locale={getLocale(params.req)}
            themeColors={themeColors}
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
        themeColors,
        statusCode: context.statusCode,
        renderErrors: Object.values(context.errors ?? {}),
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
