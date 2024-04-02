import React, { FC, PropsWithChildren } from "react";
import { MantineProvider } from "@mantine/core";
import { Notifications } from "@mantine/notifications";
import { i18n } from "@lingui/core";
import { I18nProvider } from "@lingui/react";
import { ModalsProvider } from "@mantine/modals";
import { HelmetProvider } from "react-helmet-async";

import "@mantine/core/styles/global.css";
import "@mantine/core/styles.css";
import "@mantine/notifications/styles.css";
import "@mantine/tiptap/styles.css";
import "@mantine/dropzone/styles.css";
import "@mantine/charts/styles.css";
import "./styles/global.scss";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

export async function dynamicActivate(locale: string) {
  const { messages } = await import(`./locales/${locale}.po`);

  i18n.load(locale, messages);
  i18n.activate(locale);
}

const getSupportedLocale = () => {
  if (typeof window.navigator !== "undefined") {
    const supportedLocales = ["es", "de", "en", "fr", "pt"];
    const userLocale = navigator.language.split("-")[0]; // Extracting the base language

    if (supportedLocales.includes(userLocale)) {
      return userLocale;
    }
  }

  return "en";
};

dynamicActivate(getSupportedLocale());

export const App: FC<
  PropsWithChildren<{
    queryClient: QueryClient;
  }>
> = (props) => {
  return (
    <React.StrictMode>
      <HelmetProvider>
        <I18nProvider i18n={i18n}>
          <QueryClientProvider client={props.queryClient}>
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
              <ModalsProvider>{props.children}</ModalsProvider>
              <Notifications />
            </MantineProvider>
          </QueryClientProvider>
        </I18nProvider>
      </HelmetProvider>
    </React.StrictMode>
  );
};
