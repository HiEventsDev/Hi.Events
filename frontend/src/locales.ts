import {i18n} from "@lingui/core";
import {t} from "@lingui/macro";
import {resolveClientEmbedLocale} from "./utilites/embedLocale.ts";
import {getSsrRequestContext} from "./utilites/ssrRequestContext.ts";

export type SupportedLocales =
    "en"
    | "de"
    | "fr"
    | "it"
    | "nl"
    | "pt"
    | "es"
    | "zh-cn"
    | "pt-br"
    | "vi"
    | "zh-hk"
    | "tr"
    | "hu"
    | "pl"
    | "se"
    | "sk"
    | "el"
    | "ko"
    | "ja"
    | "fi";

export const availableLocales = ["en", "de", "fr", "it", "nl", "pt", "es", "zh-cn", "zh-hk", "pt-br", "vi", "tr", "hu", "pl", "se", "sk", "el", "ko", "ja", "fi"];

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
    tr: '🇹🇷',
    hu: '🇭🇺',
    pl: '🇵🇱',
    se: '🇸🇪',
    sk: '🇸🇰',
    el: '🇬🇷',
    ko: '🇰🇷',
    ja: '🇯🇵',
    fi: '🇫🇮',
};

export const getLocaleName = (locale: SupportedLocales): string => {
    switch (locale) {
        case "en":
            return t`English`;
        case "de":
            return t`German`;
        case "fr":
            return t`French`;
        case "it":
            return t`Italian`;
        case "nl":
            return t`Dutch`;
        case "pt":
            return t`Portuguese`;
        case "es":
            return t`Spanish`;
        case "zh-cn":
            return t`Chinese (Simplified)`;
        case "zh-hk":
            return t`Chinese (Traditional)`;
        case "pt-br":
            return t`Brazilian Portuguese`;
        case "vi":
            return t`Vietnamese`;
        case "tr":
            return t`Turkish`;
        case "hu":
            return t`Hungarian`;
        case "pl":
            return t`Polish`;
        case "se":
            return t`Swedish`;
        case "sk":
            return t`Slovak`;
        case "el":
            return t`Greek`;
        case "ko":
            return t`Korean`;
        case "ja":
            return t`Japanese`;
        case "fi":
            return t`Finnish`;
        default:
            return locale;
    }
};

export const getClientLocale = () => {
    if (typeof window !== "undefined") {
        const embedLocale = resolveClientEmbedLocale();
        if (embedLocale) {
            return embedLocale;
        }

        const storedLocale = document
            .cookie
            .split(";")
            .map((c) => c.trim())
            .find((c) => c.startsWith("locale="))
            ?.split("=")[1];

        if (storedLocale) {
            return getSupportedLocale(storedLocale);
        }

        return getSupportedLocale(window.navigator.language);
    }

    return getSsrRequestContext()?.locale ?? "en";
};

const intlLocaleOverrides: Partial<Record<SupportedLocales, string>> = {
    se: "sv",
};

export const toIntlLocale = (locale: string | null | undefined): string | undefined => {
    if (!locale) {
        return undefined;
    }

    return intlLocaleOverrides[locale as SupportedLocales] ?? locale;
};

export const toDayjsLocale = (locale: string): string => toIntlLocale(locale) ?? locale;

const localeForLanguage = (language: string): string | undefined => Object.entries(intlLocaleOverrides)
    .find(([, intlLocale]) => intlLocale === language)?.[0];

const dayjsLocaleLoaders: Partial<Record<SupportedLocales, () => Promise<unknown>>> = {
    de: () => import("dayjs/locale/de"),
    fr: () => import("dayjs/locale/fr"),
    it: () => import("dayjs/locale/it"),
    nl: () => import("dayjs/locale/nl"),
    pt: () => import("dayjs/locale/pt"),
    es: () => import("dayjs/locale/es"),
    "zh-cn": () => import("dayjs/locale/zh-cn"),
    "pt-br": () => import("dayjs/locale/pt-br"),
    vi: () => import("dayjs/locale/vi"),
    "zh-hk": () => import("dayjs/locale/zh-hk"),
    tr: () => import("dayjs/locale/tr"),
    hu: () => import("dayjs/locale/hu"),
    pl: () => import("dayjs/locale/pl"),
    se: () => import("dayjs/locale/sv"),
    sk: () => import("dayjs/locale/sk"),
    el: () => import("dayjs/locale/el"),
    ko: () => import("dayjs/locale/ko"),
    ja: () => import("dayjs/locale/ja"),
    fi: () => import("dayjs/locale/fi"),
};

export async function dynamicActivateLocale(locale: string): Promise<string> {
    locale = availableLocales.includes(locale) ? locale : "en";
    try {
        const [module] = await Promise.all([
            import(`./locales/${locale}.po`),
            dayjsLocaleLoaders[locale as SupportedLocales]?.().catch((error) => console.error("Error loading dayjs locale:", error)),
        ]);
        i18n.load(locale, module.messages);
        i18n.activate(locale);
        if (typeof document !== "undefined") {
            document.documentElement.lang = toIntlLocale(locale) ?? locale;
        }
    } catch (error) {
        console.error("Error loading locale:", error);
    }
    return locale;
}

export const getSupportedLocale = (userLocale: string) => {
    const normalizedLocale = userLocale.toLowerCase();

    if (availableLocales.includes(normalizedLocale)) {
        return normalizedLocale;
    }

    const mainLanguage = normalizedLocale.split('-')[0];
    const aliasedLocale = localeForLanguage(mainLanguage);
    if (aliasedLocale) {
        return aliasedLocale;
    }

    const mainLocale = availableLocales.find(locale => locale.startsWith(mainLanguage));
    if (mainLocale) {
        return mainLocale;
    }

    return "en";
};
