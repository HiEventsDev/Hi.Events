/**
 * Serialise a value to JSON that is safe to embed inside an inline <script> tag.
 */
export const htmlSafeJsonStringify = (value) =>
    (JSON.stringify(value) ?? "null")
        .replace(/</g, "\\u003c")
        .replace(/>/g, "\\u003e")
        .replace(/&/g, "\\u0026")
        .replace(/\u2028/g, "\\u2028")
        .replace(/\u2029/g, "\\u2029");
