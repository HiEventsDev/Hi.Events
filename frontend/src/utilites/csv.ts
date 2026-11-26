export type CsvCell = string | number | null | undefined;

const FORMULA_TRIGGERS = ['=', '+', '-', '@', '\t', '\r'];

const escapeFormula = (value: string): string =>
    value !== '' && Number.isNaN(Number(value)) && FORMULA_TRIGGERS.includes(value[0]) ? `'${value}` : value;

const toCsvCell = (cell: CsvCell): string => {
    if (cell === null || cell === undefined) {
        return '';
    }
    if (typeof cell === 'number') {
        return String(cell);
    }

    const value = escapeFormula(cell);

    return /[",\r\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
};

export const toCsv = (headers: string[], rows: CsvCell[][]): string =>
    [headers, ...rows].map(row => row.map(toCsvCell).join(',')).join('\r\n');
