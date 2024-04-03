import React, { FC, PropsWithChildren, useRef } from "react";
import { MantineProvider } from "@mantine/core";
import { Notifications } from "@mantine/notifications";
import { i18n } from "@lingui/core";
import { I18nProvider } from "@lingui/react";
import { ModalsProvider } from "@mantine/modals";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {HelmetProvider} from "react-helmet-async";

import "@mantine/core/styles/global.css";
import "@mantine/core/styles.css";
import "@mantine/notifications/styles.css";
import "@mantine/tiptap/styles.css";
import "@mantine/dropzone/styles.css";
import "@mantine/charts/styles.css";
import "./styles/global.scss";

import { messages as en } from "./locales/en.po";
import { messages as de } from "./locales/de.po";
import { messages as fr } from "./locales/fr.po";
import { messages as pt } from "./locales/pt.po";
import { messages as es } from "./locales/es.po";

const supportedLocales: Record<string, any> = {
  en,
  de,
  fr,
  pt,
  es,
};

export async function dynamicActivate(locale: string) {
  i18n.load(locale, supportedLocales[locale || "en"] || {});
  i18n.activate(locale);
}

const getSupportedLocale = () => {
  if (typeof window.navigator !== "undefined") {
    const supportedLocalesKeys = Object.keys(supportedLocales);
    const userLocale = navigator.language.split("-")[0]; // Extracting the base language

    if (supportedLocalesKeys.includes(userLocale)) {
      return userLocale;
    }
  }

  return "en";
};

export const App: FC<
  PropsWithChildren<{
    queryClient: QueryClient;
    helmetContext?: any;
  }>
> = (props) => {
  const localeActivated = useRef(false);

  if (!localeActivated.current) {
    localeActivated.current = true;
    dynamicActivate(getSupportedLocale());
  }

  return (
    <React.StrictMode>
      <HelmetProvider context={props.helmetContext}>
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
