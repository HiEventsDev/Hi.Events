const PHP_TRIMMED_CHARACTERS = new Set([' ', '\t', '\n', '\r', '\0', '\v']);

const phpTrim = (value: string): string => {
    let start = 0;
    let end = value.length;

    while (start < end && PHP_TRIMMED_CHARACTERS.has(value[start])) {
        start++;
    }
    while (end > start && PHP_TRIMMED_CHARACTERS.has(value[end - 1])) {
        end--;
    }

    return value.slice(start, end);
};

export const hasMessageText = (body: string): boolean => phpTrim(body.replace(/<[^>]*>/g, '')) !== '';

const countImages = (html: string): number => (html.match(/<img\b/gi) ?? []).length;

export const lostImages = (sentBody: string, savedBody: string): boolean => countImages(sentBody) > countImages(savedBody);
