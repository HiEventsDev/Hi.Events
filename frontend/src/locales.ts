import {i18n} from "@lingui/core";
import {t} from "@lingui/macro";

export type SupportedLocales = "en" | "de" | "fr" | "it" | "nl" | "pt" | "es" | "zh-cn" | "pt-br" | "vi" |"zh-hk";

export const availableLocales = ["en", "de", "fr", "it", "nl", "pt", "es", "zh-cn", "zh-hk", "pt-br", "vi",];

export const localeToFlagEmojiMap: Record<SupportedLocales, string> = {
    en: '🇬🇧',
    de: '🇩🇪',
    fr: '🇫🇷',
    it: '🇮🇹',
    nl: '🇳🇱',
    pt: '🇵🇹',
    es: '🇪🇸',
    "zh-cn": '🇨🇳',
    "zh-hk": '🇭🇰',
    "pt-br": '🇧🇷',
    vi: '🇻🇳',
};

export const localeToNameMap: Record<SupportedLocales, string> = {
    en: `English`,
    de: `German`,
    fr: `French`,
    it: `Italian`,
    nl: `Dutch`,
    pt: `Portuguese`,
    es: `Spanish`,
    "zh-cn": `Chinese`,
    "zh-hk": `Cantonese`,
    "pt-br": `Portuguese (Brazil)`,
    vi: `Vietnamese`,
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
        locale = availableLocales.includes(locale) ? locale : "en";
        const module = (await import(`./locales/${locale}.po`));
        i18n.load(locale, module.messages);
        i18n.activate(locale);
}

export const getSupportedLocale = (userLocale: string) => {
    const normalizedLocale = userLocale.toLowerCase();

    if (availableLocales.includes(normalizedLocale)) {
        return normalizedLocale;
    }

    const mainLanguage = normalizedLocale.split('-')[0];
    const mainLocale = availableLocales.find(locale => locale.startsWith(mainLanguage));
    if (mainLocale) {
        return mainLocale;
    }

    return "en";
};
