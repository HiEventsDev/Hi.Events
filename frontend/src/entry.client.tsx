import ReactDOM from "react-dom/client";
import {createBrowserRouter, matchRoutes, RouterProvider} from "react-router-dom";
import {hydrate} from "@tanstack/react-query";

import {router} from "./router";
import {App} from "./App";
import {queryClient} from "./utilites/queryClient";
import {getClientLocale} from "./locales.ts";

declare global {
    interface Window {
        __REHYDRATED_STATE__?: unknown;
    }
}

if (window.__REHYDRATED_STATE__) {
    hydrate(queryClient, window.__REHYDRATED_STATE__);
}

async function initClientApp() {
    // Determine if any of the initial routes are lazy
    const lazyMatches = matchRoutes(router, window.location)?.filter(
        (m) => m.route.lazy
    );

    // Load the lazy matches and update the routes before creating your router
    // so we can hydrate the SSR-rendered content synchronously
    if (lazyMatches && lazyMatches?.length > 0) {
        await Promise.all(
            lazyMatches.map(async (m) => {
                const routeModule = await m.route.lazy?.();
                Object.assign(m.route, {...routeModule, lazy: undefined});
            })
        );
    }

    // todo - Investigate using hydrateRoot instead of createRoot
    ReactDOM.createRoot(document.getElementById("app") as HTMLElement).render(
        <App
            queryClient={queryClient}
            locale={getClientLocale()}
        >
            <RouterProvider
                router={createBrowserRouter(router)}
                fallbackElement={null}
            />
        </App>
    );
}

initClientApp();
