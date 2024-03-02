import React from 'react';
import ReactDOM from 'react-dom/client';
import { RouterProvider } from "react-router-dom";
import { MantineProvider } from "@mantine/core";
import { router } from "./router.tsx";
import { Notifications } from '@mantine/notifications';
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { i18n } from "@lingui/core";
import { I18nProvider } from "@lingui/react";
import { ModalsProvider } from "@mantine/modals";
import { HelmetProvider } from "react-helmet-async";

import '@mantine/core/styles/global.css';
import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';
import '@mantine/tiptap/styles.css';
import '@mantine/dropzone/styles.css';
import '@mantine/charts/styles.css';
import './styles/global.scss';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 0,
            refetchOnWindowFocus: false,
            networkMode: "always",
        }
    }
});

export async function dynamicActivate(locale: string) {
    const { messages } = await import(`./locales/${locale}.po`);

    i18n.load(locale, messages);
    i18n.activate(locale);
}

const getSupportedLocale = () => {
    const supportedLocales = ['es', 'de', 'en', 'fr', 'pt'];
    const userLocale = navigator.language.split('-')[0]; // Extracting the base language

    if (supportedLocales.includes(userLocale)) {
        return userLocale;
    }

    return 'en';
};

dynamicActivate(getSupportedLocale());

ReactDOM.createRoot(document.getElementById('hievents-root') as HTMLElement).render(
    <React.StrictMode>
        <HelmetProvider>
            <I18nProvider i18n={i18n}>
                <QueryClientProvider client={queryClient}>
                    <MantineProvider theme={{
                        colors: {
                            "purple": [
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
                            ]
                        },
                        primaryColor: "purple",
                        fontFamily: "'Varela Round', sans-serif",
                    }}>
                        <ModalsProvider>
                            <RouterProvider router={router}/>
                        </ModalsProvider>
                        <Notifications/>
                    </MantineProvider>
                </QueryClientProvider>
            </I18nProvider>
        </HelmetProvider>
    </React.StrictMode>
);
