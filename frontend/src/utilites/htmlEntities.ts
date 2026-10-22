const NAMED_ENTITIES: Record<string, string> = {amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0'};

export const decodeHtmlEntities = (text: string): string =>
    text.replace(/&(#x[\da-f]+|#\d+|[a-z]+);/gi, (match, entity: string) => {
        if (entity[0] !== '#') {
            return NAMED_ENTITIES[entity.toLowerCase()] ?? match;
        }
        const code = entity[1].toLowerCase() === 'x' ? parseInt(entity.slice(2), 16) : parseInt(entity.slice(1), 10);
        return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : match;
    });
