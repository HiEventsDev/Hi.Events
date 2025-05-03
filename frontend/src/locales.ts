import {i18n} from "@lingui/core";

export type SupportedLocales =
    | "en" | "de" | "fr" | "nl" | "pt" | "es"
    | "zh-cn" | "zh-hk" | "pt-br" | "vi";

export const localeToFlagEmojiMap: Record<SupportedLocales, string> = {
    en: '🇬🇧', de: '🇩🇪', fr: '🇫🇷', nl: '🇳🇱', pt: '🇵🇹',
    es: '🇪🇸', "zh-cn": '🇨🇳', "zh-hk": '🇭🇰',
    "pt-br": '🇧🇷', vi: '🇻🇳',
};

export const localeToNameMap: Record<SupportedLocales, string> = {
    en: `English`, de: `German`, fr: `French`, nl: `Dutch`,
    pt: `Portuguese`, es: `Spanish`, "zh-cn": `Chinese`,
    "zh-hk": `Cantonese`, "pt-br": `Portuguese (Brazil)`, vi: `Vietnamese`,
};

export const getLocaleName = (locale: SupportedLocales) => localeToNameMap[locale];

export const getClientLocale = (): SupportedLocales => {
    if (typeof window !== "undefined") {
        const storedLocale = document.cookie.split(";")
            .find((c) => c.includes("locale="))?.split("=")[1];
        return getSupportedLocale(storedLocale || window.navigator.language);
    }
    return "en";
};

export const getSupportedLocale = (userLocale: string): SupportedLocales => {
    const supported: SupportedLocales[] = [
        "en", "de", "fr", "nl", "pt", "es", "zh-cn", "zh-hk", "pt-br", "vi"
    ];

    const normalized = userLocale.toLowerCase();
    if (supported.includes(normalized as SupportedLocales)) return normalized as SupportedLocales;

    const mainLang = normalized.split('-')[0];
    return supported.find(l => l.startsWith(mainLang)) || "en";
};

export async function dynamicActivateLocale(locale: string) {
    const safeLocale = getSupportedLocale(locale);
    try {
        const {messages} = await import(
            /* @vite-ignore */
            `./locales/${safeLocale}.po`
            );
        i18n.load(safeLocale, messages);
        i18n.activate(safeLocale);
    } catch {
        i18n.activate("en");
    }
}
