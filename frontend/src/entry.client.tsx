import {hydrateRoot} from "react-dom/client";
import {createBrowserRouter, matchRoutes, RouterProvider} from "react-router-dom";
import {hydrate} from "@tanstack/react-query";

import {routes} from "./router";
import {App} from "./App";
import {queryClient} from "./utilites/queryClient";
import {dynamicActivateLocale, getClientLocale, getSupportedLocale,} from "./locales.ts";

declare global {
    interface Window {
        __REHYDRATED_STATE__?: unknown;
    }
}

if (window.__REHYDRATED_STATE__) {
    hydrate(queryClient, window.__REHYDRATED_STATE__);
}

async function initClientApp() {
    const rawLocale = getClientLocale();
    const locale = getSupportedLocale(rawLocale);
    await dynamicActivateLocale(locale);

    // Resolve lazy-loaded routes before hydration
    const matches = matchRoutes(routes, window.location)?.filter((m) => m.route.lazy);
    if (matches && matches.length > 0) {
        await Promise.all(
            matches.map(async (m) => {
                const routeModule = await m.route.lazy?.();
                Object.assign(m.route, {...routeModule, lazy: undefined});
            })
        );
    }

    const browserRouter = createBrowserRouter(routes);

    hydrateRoot(
        document.getElementById("app") as HTMLElement,
        <App queryClient={queryClient} locale={rawLocale}>
            <RouterProvider router={browserRouter}/>
        </App>
    );
}

initClientApp();
