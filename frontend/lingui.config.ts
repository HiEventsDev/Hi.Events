import type {LinguiConfig} from "@lingui/conf";

const config: LinguiConfig = {
    locales: [
        "en", // English
        "zh-cn", // Mandarin Chinese (Simplified)
        "es", // Spanish
        "fr", // French
        "pt-br", // Portuguese (Brazil)
        "ru", // Russian
        "de", // German
        "pt", // Portuguese (Portugal)

        // "it", // Italian
        // "pl", // Polish
        // "ja", // Japanese
        // "ko", // Korean
        // "id", // Indonesian
        // "zh-hk", // Cantonese Chinese (Hong Kong)
        // "cs", // Czech
        // "ga", // Irish
    ],
    catalogs: [
        {
            path: "<rootDir>/src/locales/{locale}",
            include: ["src"],
        },
    ],
    sourceLocale: "en",
    format: "po",
    fallbackLocales: {
        "default": "en",
    }
};

export default config;
