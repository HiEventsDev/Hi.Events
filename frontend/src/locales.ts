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
// @ts-ignore
import {messages as zhCn} from "./locales/zh-cn.po";
// @ts-ignore
import {messages as ptBr} from "./locales/pt-br.po";
import {i18n} from "@lingui/core";
import {t} from "@lingui/macro";

export type SupportedLocales = "en" | "de" | "fr" | "pt" | "es" | "zh-cn" | "pt-br";

export const localeMessages: Record<string, any> = {
    en: en,
    de: de,
    fr: fr,
    pt: pt,
    es: es,
    "zh-cn": zhCn,
    "pt-br": ptBr,
};

export const localeToFlagEmojiMap: Record<SupportedLocales, string> = {
    en: '🇬🇧',
    de: '🇩🇪',
    fr: '🇫🇷',
    pt: '🇵🇹',
    es: '🇪🇸',
    "zh-cn": '🇨🇳',
    "pt-br": '🇧🇷',
};

export const localeToNameMap: Record<SupportedLocales, string> = {
    en: `English`,
    de: `German`,
    fr: `French`,
    pt: `Portuguese`,
    es: `Spanish`,
    "zh-cn": `Chinese`,
    "pt-br": `Portuguese (Brazil)`,
};

export const getLocaleName = (locale: SupportedLocales) => {
    return t`${localeToNameMap[locale]}`
}

export const getClientLocale = () => {
    if (typeof window !== "undefined") {
        const storedLocale = document
            .cookie
            .split(";")
            .find((c) => c.includes("locale="))
            ?.split("=")[1];

        if (storedLocale) {
            return getSupportedLocale(storedLocale);
        }

        return getSupportedLocale(window.navigator.language);
    }

    return "en";
};

export async function dynamicActivateLocale(locale: string) {
    try {
        const messages = localeMessages[locale] || localeMessages["en"];
        i18n.load(locale, messages);
        i18n.activate(locale);
    } catch (error) {
        i18n.activate("en");
    }
}

export const getSupportedLocale = (userLocale: string) => {
    const normalizedLocale = userLocale.toLowerCase();

    if (localeMessages[normalizedLocale]) {
        return normalizedLocale;
    }

    const mainLanguage = normalizedLocale.split('-')[0];
    const mainLocale = Object.keys(localeMessages).find(locale => locale.startsWith(mainLanguage));
    if (mainLocale) {
        return mainLocale;
    }

    return "en";
};
