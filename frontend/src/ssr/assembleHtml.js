import {htmlSafeJsonStringify} from "../utilites/safeScriptJson.js";
import {pickViteEnv} from "./env.js";

/**
 * Fill the SSR HTML shell placeholders.
 * @param {string} template
 * @param {{
 *   appHtml: string,
 *   dehydratedState: unknown,
 *   helmetContext: {helmet?: Record<string, {toString(): string}>},
 *   publicEnv?: Record<string, string>,
 * }} params
 * @returns {string}
 */
export function assembleHtml(template, {
    appHtml,
    dehydratedState,
    helmetContext,
    publicEnv = {},
}) {
    const stringifiedState = htmlSafeJsonStringify(dehydratedState);
    const envVariablesHtml = `<script>window.hievents = ${htmlSafeJsonStringify(publicEnv)};</script>`;

    const helmetHtml = Object.values(helmetContext?.helmet || {})
        .map((value) => value.toString() || "")
        .join(" ");

    const headSnippets = [];
    if (publicEnv.VITE_FATHOM_SITE_ID) {
        headSnippets.push(`
                <script src="https://cdn.usefathom.com/script.js" data-spa="auto" data-site="${publicEnv.VITE_FATHOM_SITE_ID}" defer></script>
            `);
    }

    return template
        .replace("<!--head-snippets-->", () => headSnippets.join("\n"))
        .replace("<!--app-html-->", () => appHtml)
        .replace("<!--dehydrated-state-->", () => `<script>window.__REHYDRATED_STATE__ = ${stringifiedState}</script>`)
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
