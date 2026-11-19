export const personName = (first: string | null, last: string | null) => [first, last].filter(Boolean).join(' ');
