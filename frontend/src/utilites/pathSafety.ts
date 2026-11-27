const PATH_STRUCTURE_CHARACTERS = /[/\\?#\u0000-\u001f\u007f]/;
const ENCODED_SEPARATOR = /%(2f|5c)/i;
const DOT_SEGMENT = /^(\.|%2e){1,2}$/i;
const CHARACTERS_URL_PARSERS_DROP = /[\t\n\r]/g;

const decodesToPathStructure = (segment: string): boolean => {
    try {
        const decoded = decodeURIComponent(segment);

        return PATH_STRUCTURE_CHARACTERS.test(decoded) || ENCODED_SEPARATOR.test(decoded) || DOT_SEGMENT.test(decoded);
    } catch {
        return true;
    }
};

export const hasUnsafePathSegment = (pathname: string): boolean => pathname.split("/").some(decodesToPathStructure);

export const leavesItsEndpoint = (requestUrl: string): boolean => {
    const path = requestUrl.split(/[?#]/)[0].replace(CHARACTERS_URL_PARSERS_DROP, "");

    return path.includes("\\") || path.split("/").some((segment) => DOT_SEGMENT.test(segment));
};
