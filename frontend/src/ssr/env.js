/**
 * Collect VITE_* public environment variables from a process.env-like object
 * or Cloudflare Workers bindings.
 * @param {Record<string, unknown>} [source]
 * @returns {Record<string, string>}
 */
export function pickViteEnv(source = {}) {
    const envVars = {};
    for (const key of Object.keys(source)) {
        if (key.startsWith("VITE_") && typeof source[key] === "string") {
            envVars[key] = source[key];
        }
    }
    return envVars;
}
