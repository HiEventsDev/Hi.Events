import {htmlSafeJsonStringify} from "../utilites/safeScriptJson.js";
import {pickViteEnv} from "./env.js";

/**
 * Build Google Consent Mode defaults from the shared consent cookie.
 * @param {string | undefined} consentCookie
 * @returns {string}
 */
export function googleConsentDefaults(consentCookie) {
    const consent = new URLSearchParams(typeof consentCookie === "string" ? consentCookie : "");
    if (!consent.has("analytics") && !consent.has("advertising")) {
        return "ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500";
    }
    const advertising = consent.get("advertising") === "1" ? "granted" : "denied";
    const analytics = consent.get("analytics") === "1" ? "granted" : "denied";
    return `ad_storage:'${advertising}',ad_user_data:'${advertising}',ad_personalization:'${advertising}',analytics_storage:'${analytics}'`;
}

/**
 * Fill the SSR HTML shell placeholders.
 * @param {string} template
 * @param {{
 *   appHtml: string,
 *   dehydratedState: unknown,
 *   helmetContext: {helmet?: Record<string, {toString(): string}>},
 *   themeColors?: unknown,
 *   publicEnv?: Record<string, string>,
 *   consentCookie?: string,
 * }} params
 * @returns {string}
 */
export function assembleHtml(template, {
    appHtml,
    dehydratedState,
    helmetContext,
    themeColors,
    publicEnv = {},
    consentCookie,
}) {
    const stringifiedState = htmlSafeJsonStringify(dehydratedState);
    const stringifiedThemeColors = htmlSafeJsonStringify(themeColors ?? null);
    const envVariablesHtml = `<script>window.hievents = ${htmlSafeJsonStringify(publicEnv)};</script>`;

    const helmetHtml = Object.values(helmetContext?.helmet || {})
        .map((value) => value.toString() || "")
        .join(" ");

    const headSnippets = [];
    if (publicEnv.VITE_COOKIE_CONSENT_ENABLED === "true") {
        headSnippets.push(
            `<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{${googleConsentDefaults(consentCookie)}});</script>`
        );
    }
    if (publicEnv.VITE_GOOGLE_ADS_CONVERSION_ID) {
        const conversionId = encodeURIComponent(publicEnv.VITE_GOOGLE_ADS_CONVERSION_ID);
        headSnippets.push(`
                <script async src="https://www.googletagmanager.com/gtag/js?id=${conversionId}"></script>
                <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${conversionId}');</script>
            `);
    }
    if (publicEnv.VITE_FATHOM_SITE_ID) {
        headSnippets.push(`
                <script src="https://cdn.usefathom.com/script.js" data-spa="auto" data-site="${encodeURIComponent(publicEnv.VITE_FATHOM_SITE_ID)}" defer></script>
            `);
    }

    return template
        .replace("<!--head-snippets-->", () => headSnippets.join("\n"))
        .replace("<!--app-html-->", () => appHtml)
        .replace(
            "<!--dehydrated-state-->",
            () => `<script>window.__REHYDRATED_STATE__ = ${stringifiedState};window.__THEME_COLORS__ = ${stringifiedThemeColors}</script>`
        )
        .replace("<!--environment-variables-->", () => envVariablesHtml)
        .replace(/<!--render-helmet-->.*?<!--\/render-helmet-->/s, () => helmetHtml);
}

/**
 * Convenience helper when the env source is process.env or Workers bindings.
 */
export function assembleHtmlFromEnv(template, renderResult, envSource) {
    return assembleHtml(template, {
        ...renderResult,
        publicEnv: pickViteEnv(envSource),
    });
}
