const PRECISION = 10000;

export const sumColumn = <T, >(rows: T[], key: keyof T): number =>
    rows.reduce((total, row) => total + Math.round((Number(row[key]) || 0) * PRECISION), 0) / PRECISION;

export const sharedCurrency = <T, >(rows: T[], currencyOf: (row: T) => string | null | undefined): string | null => {
    const currencies = new Set(rows.map(row => currencyOf(row)?.toUpperCase()).filter(Boolean));

    return currencies.size === 1 ? [...currencies][0] as string : null;
};

export const percentOf = (part: number, whole: number): number =>
    whole > 0 ? Math.round((part / whole) * 1000) / 10 : 0;

export type SortDirection = 'asc' | 'desc';

export const sortRows = <T, >(rows: T[], key: keyof T | null, direction: SortDirection): T[] => {
    if (!key) {
        return rows;
    }

    const factor = direction === 'asc' ? 1 : -1;
    const isBlank = (value: unknown) => value === null || value === undefined || value === '';

    return [...rows].sort((a, b) => {
        const left = a[key];
        const right = b[key];

        if (isBlank(left) || isBlank(right)) {
            return Number(isBlank(left)) - Number(isBlank(right));
        }

        const leftNumber = Number(left);
        const rightNumber = Number(right);
        if (!Number.isNaN(leftNumber) && !Number.isNaN(rightNumber)) {
            return (leftNumber - rightNumber) * factor;
        }

        return String(left).localeCompare(String(right), undefined, {sensitivity: 'base', numeric: true}) * factor;
    });
};
