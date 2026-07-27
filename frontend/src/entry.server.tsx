import ReactDOMServer from "react-dom/server";
import {dehydrate, QueryClient} from "@tanstack/react-query";

import {router} from "./router";
import {App} from "./App";
import {setAuthToken} from "./utilites/apiClient.ts";
import {createStaticHandler, createStaticRouter, StaticRouterProvider} from "react-router";
import {dynamicActivateLocale} from "./locales.ts";
import {setSsrQueryClient} from "./utilites/ssrQueryClient.ts";
import {parseCookies} from "./ssr/cookies.js";

const getLocale = (request: Request, cookies: Record<string, string>): string => {
    if (cookies.locale) {
        return cookies.locale;
    }

    const acceptLanguage = request.headers.get("accept-language");
    return acceptLanguage ? acceptLanguage.split(",")[0].split("-")[0] : "en";
};

export async function render(request: Request) {
    const cookies = parseCookies(request.headers.get("cookie"));
    setAuthToken(cookies.token);

    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60 * 1000,
                refetchOnWindowFocus: false,
                networkMode: "always",
            },
            mutations: {
                networkMode: "always",
            },
        },
    });

    setSsrQueryClient(queryClient);

    const helmetContext = {};

    const {query, dataRoutes} = createStaticHandler(router);
    const context = await query(request);

    if (context instanceof Response) {
        throw context;
    }

    const locale = getLocale(request, cookies);
    await dynamicActivateLocale(locale);

    const routerWithContext = createStaticRouter(dataRoutes, context);

    const appHtml = ReactDOMServer.renderToString(
        <App
            queryClient={queryClient}
            helmetContext={helmetContext}
            locale={locale}
        >
            <StaticRouterProvider
                router={routerWithContext}
                context={context}
            />
        </App>
    );

    const dehydratedState = dehydrate(queryClient);

    setSsrQueryClient(null);

    return {
        appHtml,
        dehydratedState,
        helmetContext,
    };
}
