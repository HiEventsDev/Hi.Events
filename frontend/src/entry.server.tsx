import type * as express from "express";
import {AsyncLocalStorage} from "node:async_hooks";
import ReactDOMServer from "react-dom/server";
import {i18n} from "@lingui/core";
import {dehydrate, QueryClient} from "@tanstack/react-query";

import {router} from "./router";
import {App} from "./App";
import {createStaticHandler, createStaticRouter, StaticRouterProvider} from "react-router";
import {dynamicActivateLocale, getSupportedLocale, toIntlLocale} from "./locales.ts";
import {embedLocaleForPath} from "./utilites/embedLocale.ts";
import {SsrRequestContext, setSsrRequestContextProvider} from "./utilites/ssrRequestContext.ts";
import {generateThemeColors} from "./utilites/themeColors.ts";
import {prefetchInstanceInfo} from "./ee/licensing/queries/prefetchInstanceInfo.ts";

const themeColors = generateThemeColors();

const requestContext = new AsyncLocalStorage<SsrRequestContext>();

setSsrRequestContextProvider(() => requestContext.getStore());

const getLocale = (req: express.Request): string => {
    const url = new URL(req.originalUrl || req.url, 'http://localhost');
    const embedLocale = embedLocaleForPath(url.pathname, url.search, requestContext.getStore()?.widgetLocale);
    if (embedLocale) {
        return embedLocale;
    }

    if (req.cookies.locale) {
        return getSupportedLocale(req.cookies.locale);
    }

    const acceptLanguage = req.headers['accept-language'];
    return acceptLanguage ? getSupportedLocale(acceptLanguage.split(',')[0].split(';')[0].trim()) : 'en';
}

interface RenderParams {
    req: express.Request;
    res: express.Response;
    backendHeaders?: Record<string, string>;
    licenceSimulation?: string;
    stylesheetsFor?: (entries: string[]) => string[];
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

    return requestContext.run(context, () => renderWithinContext(params, context));
}

async function renderWithinContext(params: RenderParams, ssrContext: SsrRequestContext) {
    const {queryClient} = ssrContext;
    await prefetchInstanceInfo(queryClient);

    const helmetContext = {};

    const {query, dataRoutes} = createStaticHandler(router);
    const remixRequest = createFetchRequest(params.req, params.res);
    const context = await query(remixRequest);

    if (context instanceof Response) {
        throw context;
    }

    const locale = await dynamicActivateLocale(getLocale(params.req));
    ssrContext.locale = locale;

    const routerWithContext = createStaticRouter(dataRoutes, context);
    const stylesheets = params.stylesheetsFor?.(context.matches.flatMap(match => match.route.handle?.styles ?? [])) ?? [];

    i18n.activate(locale);
    const appHtml = ReactDOMServer.renderToString(
        <App
            queryClient={queryClient}
            helmetContext={helmetContext}
            locale={locale}
            themeColors={themeColors}
            ssrStyled={stylesheets.length > 0}
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
        htmlLang: toIntlLocale(locale) ?? locale,
        statusCode: context.statusCode,
        renderErrors: Object.values(context.errors ?? {}),
        stylesheets,
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
