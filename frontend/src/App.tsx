import React, {FC, PropsWithChildren, useEffect} from "react";
import {MantineProvider} from "@mantine/core";
import {Notifications} from "@mantine/notifications";
import {i18n} from "@lingui/core";
import {I18nProvider} from "@lingui/react";
import {ModalsProvider} from "@mantine/modals";
import {HydrationBoundary, QueryClient, QueryClientProvider} from "@tanstack/react-query";
import {Helmet, HelmetProvider} from "react-helmet-async";
import {generateColors} from '@mantine/colors-generator';

import "@mantine/core/styles/global.css";
import "@mantine/core/styles.css";
import "@mantine/notifications/styles.css";
import "@mantine/tiptap/styles.css";
import "@mantine/dropzone/styles.css";
import '@mantine/dates/styles.css';
import "@mantine/charts/styles.css";
import "@fontsource/outfit/400.css";
import "@fontsource/outfit/500.css";
import "@fontsource/outfit/600.css";
import "@fontsource/outfit/700.css";
import "@fontsource/outfit/800.css";
import "@fontsource/plus-jakarta-sans/400.css";
import "@fontsource/plus-jakarta-sans/500.css";
import "@fontsource/plus-jakarta-sans/600.css";
import "@fontsource/plus-jakarta-sans/700.css";
import "./styles/global.scss";
import {isSsr} from "./utilites/helpers.ts";
import {StartupChecks} from "./StartupChecks.tsx";
import {ThirdPartyScripts} from "./components/common/ThirdPartyScripts";
import {getConfig} from "./utilites/config.ts";

declare global {
    interface Window {
        hievents: Record<string, string>;
    }
}

export const App: FC<
    PropsWithChildren<{
        queryClient: QueryClient;
        locale: string;
        helmetContext?: any;
        dehydratedState?: unknown;
    }>
> = (props) => {
    const [isLoadedOnBrowser, setIsLoadedOnBrowser] = React.useState(false);

    useEffect(() => {
        setIsLoadedOnBrowser(!isSsr());
    }, []);

    return (
        <React.StrictMode>
            <div
                className="ssr-loader"
                style={{
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    margin: 0,
                    padding: 0,
                    width: "100vw",
                    height: "100vh",
                    position: "fixed",
                    background: "#ffffff",
                    zIndex: 1000,
                    display: isLoadedOnBrowser ? "none" : "block",
                }}
            />
            <MantineProvider
                theme={{
                    colors: {
                        primary: generateColors(getConfig("VITE_APP_PRIMARY_COLOR", "#40296C") as string),
                        secondary: generateColors(getConfig("VITE_APP_SECONDARY_COLOR", "#3d0b44") as string),
                    },
                    primaryColor: "primary",
                    fontFamily: "Outfit, sans-serif",
                    primaryShade: 8,
                }}
            >
                <HelmetProvider context={props.helmetContext}>
                    <I18nProvider i18n={i18n}>
                        <QueryClientProvider client={props.queryClient}>
                            <HydrationBoundary state={props.dehydratedState}>
                                <StartupChecks/>
                                <ThirdPartyScripts/>
                                <ModalsProvider>
                                    <Helmet>
                                        <title>{getConfig("VITE_APP_NAME", "Hi.Events")}</title>
                                        <link rel="icon"
                                              type="image/svg+xml"
                                              href={getConfig("VITE_APP_FAVICON", "/favicon.svg")}
                                        />
                                    </Helmet>
                                    {props.children}
                                </ModalsProvider>
                                <Notifications/>
                            </HydrationBoundary>
                        </QueryClientProvider>
                    </I18nProvider>
                </HelmetProvider>
            </MantineProvider>
        </React.StrictMode>
    );
};
