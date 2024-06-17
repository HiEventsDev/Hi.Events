import React, {FC, PropsWithChildren, useEffect, useRef} from "react";
import {MantineProvider} from "@mantine/core";
import {Notifications} from "@mantine/notifications";
import {i18n} from "@lingui/core";
import {I18nProvider} from "@lingui/react";
import {ModalsProvider} from "@mantine/modals";
import {QueryClient, QueryClientProvider} from "@tanstack/react-query";
import {Helmet, HelmetProvider} from "react-helmet-async";

import "@mantine/core/styles/global.css";
import "@mantine/core/styles.css";
import "@mantine/notifications/styles.css";
import "@mantine/tiptap/styles.css";
import "@mantine/dropzone/styles.css";
import "@mantine/charts/styles.css";
import "./styles/global.scss";
// @ts-ignore
import {messages as en} from "./locales/en.po";
// @ts-ignore
import {messages as de} from "./locales/de.po";
// @ts-ignore
import {messages as fr} from "./locales/fr.po";
// @ts-ignore
import {messages as pt} from "./locales/pt.po";
// @ts-ignore
import {messages as es} from "./locales/es.po";
import {isSsr} from "./utilites/helpers.ts";

declare global {
    interface Window {
        hievents: Record<string, string>;
    }
}

const supportedLocales: Record<string, any> = {
    en,
    de,
    fr,
    pt,
    es,
};

export async function dynamicActivate(locale: string) {
    try {
        i18n.load(locale, supportedLocales[locale || "en"] || {});
        i18n.activate(locale);
    } catch (error) {
        console.error(`Error loading locale ${locale}:`, error);
        i18n.activate("en");
    }
}

const getSupportedLocale = (userLocale: string) => {
    if (Object.keys(supportedLocales).includes(userLocale)) {
        return userLocale;
    }

    return "en";
};

export const App: FC<
    PropsWithChildren<{
        queryClient: QueryClient;
        locale: string;
        helmetContext?: any;
    }>
> = (props) => {
    const [isLoadedOnBrowser, setIsLoadedOnBrowser] = React.useState(false);
    const localeActivated = useRef(false); // Initialize localeActivated as a ref with the initial value false

    useEffect(() => {
        if (!localeActivated.current && typeof window !== "undefined") {
            localeActivated.current = true;
            dynamicActivate(getSupportedLocale(props.locale));
        }

        setIsLoadedOnBrowser(!isSsr());
    }, []);

    return (
        <React.StrictMode>
            <div
                className="ssr-loader"
                style={{
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
                        purple: [
                            "#8260C6",
                            "#734DBF",
                            "#6741B2",
                            "#5E3CA1",
                            "#563792",
                            "#4E3284",
                            "#472E78",
                            "#40296C",
                            "#392562",
                            "#332158",
                        ],
                    },
                    primaryColor: "purple",
                    fontFamily: "'Varela Round', sans-serif",
                }}
            >
                <HelmetProvider context={props.helmetContext}>
                    <I18nProvider i18n={i18n}>
                        <QueryClientProvider client={props.queryClient}>
                            <ModalsProvider>
                                <Helmet>
                                    <title>Hi.Events</title>
                                </Helmet>
                                {props.children}
                            </ModalsProvider>
                            <Notifications/>
                        </QueryClientProvider>
                    </I18nProvider>
                </HelmetProvider>
            </MantineProvider>
        </React.StrictMode>
    );
};
