/* eslint-disable lingui/no-unlocalized-strings */
export type SpreadsheetCell = string | number | null;

export interface SpreadsheetRow {
    number: number;
    cells: SpreadsheetCell[];
}

export interface SpreadsheetSheet {
    name: string;
    rows: SpreadsheetRow[];
    date1904?: boolean;
}

export class SpreadsheetReadError extends Error {
    constructor(public readonly code: 'unsupported_browser' | 'unreadable' | 'unsupported_type') {
        super(code);
    }
}

const ZIP_END_OF_CENTRAL_DIRECTORY = 0x06054b50;
const ZIP_CENTRAL_DIRECTORY_ENTRY = 0x02014b50;

const isBlank = (cells: SpreadsheetCell[]) => cells.every(cell => cell === null || String(cell).trim() === '');

const detectDelimiter = (text: string): string => {
    const firstLine = text.slice(0, text.search(/\r?\n|$/));
    const candidates = ['\t', ';', ','];
    const counts = candidates.map(candidate => {
        let count = 0;
        let quoted = false;
        for (const char of firstLine) {
            if (char === '"') {
                quoted = !quoted;
            } else if (char === candidate && !quoted) {
                count++;
            }
        }
        return count;
    });
    const best = Math.max(...counts);
    return best === 0 ? ',' : candidates[counts.indexOf(best)];
};

export const parseDelimitedText = (input: string, delimiter?: string): SpreadsheetRow[] => {
    const text = input.replace(/^\uFEFF/, '');
    const separator = delimiter ?? detectDelimiter(text);
    const rows: SpreadsheetRow[] = [];
    let cells: SpreadsheetCell[] = [];
    let field = '';
    let quoted = false;
    let recordNumber = 1;

    const endRecord = () => {
        cells.push(field);
        if (!isBlank(cells)) {
            rows.push({number: recordNumber, cells});
        }
        recordNumber++;
        cells = [];
        field = '';
    };

    for (let index = 0; index < text.length; index++) {
        const char = text[index];

        if (quoted) {
            if (char === '"' && text[index + 1] === '"') {
                field += '"';
                index++;
            } else if (char === '"') {
                quoted = false;
            } else {
                field += char;
            }
            continue;
        }

        if (char === '"' && field === '') {
            quoted = true;
        } else if (char === separator) {
            cells.push(field);
            field = '';
        } else if (char === '\n' || char === '\r') {
            if (char === '\r' && text[index + 1] === '\n') {
                index++;
            }
            endRecord();
        } else {
            field += char;
        }
    }

    if (field !== '' || cells.length > 0) {
        endRecord();
    }

    return rows;
};

export const decodeText = (bytes: Uint8Array): string => {
    if (bytes[0] === 0xff && bytes[1] === 0xfe) {
        return new TextDecoder('utf-16le').decode(bytes.subarray(2));
    }
    if (bytes[0] === 0xfe && bytes[1] === 0xff) {
        return new TextDecoder('utf-16be').decode(bytes.subarray(2));
    }
    try {
        return new TextDecoder('utf-8', {fatal: true}).decode(bytes);
    } catch {
        return new TextDecoder('windows-1252').decode(bytes);
    }
};

interface ZipEntry {
    name: string;
    method: number;
    compressedSize: number;
    localHeaderOffset: number;
}

const readZipEntries = (view: DataView): ZipEntry[] => {
    let endOffset = -1;
    for (let offset = view.byteLength - 22; offset >= Math.max(0, view.byteLength - 65_557); offset--) {
        if (view.getUint32(offset, true) === ZIP_END_OF_CENTRAL_DIRECTORY) {
            endOffset = offset;
            break;
        }
    }
    if (endOffset < 0) {
        throw new SpreadsheetReadError('unreadable');
    }

    const entryCount = view.getUint16(endOffset + 10, true);
    let offset = view.getUint32(endOffset + 16, true);
    const decoder = new TextDecoder();
    const entries: ZipEntry[] = [];

    for (let index = 0; index < entryCount; index++) {
        if (view.getUint32(offset, true) !== ZIP_CENTRAL_DIRECTORY_ENTRY) {
            throw new SpreadsheetReadError('unreadable');
        }
        const nameLength = view.getUint16(offset + 28, true);
        const extraLength = view.getUint16(offset + 30, true);
        const commentLength = view.getUint16(offset + 32, true);
        entries.push({
            method: view.getUint16(offset + 10, true),
            compressedSize: view.getUint32(offset + 20, true),
            localHeaderOffset: view.getUint32(offset + 42, true),
            name: decoder.decode(new Uint8Array(view.buffer, view.byteOffset + offset + 46, nameLength)),
        });
        offset += 46 + nameLength + extraLength + commentLength;
    }

    return entries;
};

const readZipEntry = async (view: DataView, entry: ZipEntry): Promise<string> => {
    const offset = entry.localHeaderOffset;
    const dataStart = offset + 30 + view.getUint16(offset + 26, true) + view.getUint16(offset + 28, true);
    const data = new Uint8Array((view.buffer as ArrayBuffer).slice(view.byteOffset + dataStart, view.byteOffset + dataStart + entry.compressedSize));

    if (entry.method === 0) {
        return new TextDecoder().decode(data);
    }

    const stream = new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
    return new Response(stream).text();
};

const supportsDeflateRaw = (): boolean => {
    if (typeof DecompressionStream === 'undefined') {
        return false;
    }
    try {
        new DecompressionStream('deflate-raw');
        return true;
    } catch {
        return false;
    }
};

const columnIndex = (reference: string): number => {
    const letters = reference.replace(/\d+$/, '');
    let index = 0;
    for (const letter of letters) {
        index = index * 26 + (letter.charCodeAt(0) - 64);
    }
    return index - 1;
};

const elements = (parent: Document | Element, localName: string): Element[] =>
    Array.from(parent.getElementsByTagNameNS('*', localName));

const textOf = (element: Element): string => elements(element, 't')
    .filter(node => node.parentElement?.localName !== 'rPh')
    .map(node => node.textContent ?? '')
    .join('');

const parseSheetXml = (xml: string, sharedStrings: string[]): SpreadsheetRow[] => {
    const document = new DOMParser().parseFromString(xml, 'application/xml');
    const rows: SpreadsheetRow[] = [];

    elements(document, 'row').forEach((rowElement, rowPosition) => {
        const declaredNumber = Number(rowElement.getAttribute('r'));
        const cells: SpreadsheetCell[] = [];

        elements(rowElement, 'c').forEach((cell, position) => {
            const reference = cell.getAttribute('r');
            const column = reference ? columnIndex(reference) : position;
            const type = cell.getAttribute('t');
            const value = elements(cell, 'v')[0]?.textContent ?? null;

            if (type === 'inlineStr') {
                cells[column] = textOf(cell);
            } else if (type === 's') {
                cells[column] = value === null ? null : sharedStrings[Number(value)] ?? null;
            } else if (type === 'str' || type === 'e' || type === 'd') {
                cells[column] = value;
            } else if (type === 'b') {
                cells[column] = value === '1' ? 'TRUE' : 'FALSE';
            } else {
                const numeric = value === null || value === '' ? NaN : Number(value);
                cells[column] = Number.isNaN(numeric) ? value : numeric;
            }
        });

        const filled = Array.from(cells, cell => cell ?? null);
        if (!isBlank(filled)) {
            rows.push({number: declaredNumber > 0 ? declaredNumber : rowPosition + 1, cells: filled});
        }
    });

    return rows;
};

const readXlsx = async (buffer: ArrayBuffer): Promise<SpreadsheetSheet[]> => {
    if (!supportsDeflateRaw()) {
        throw new SpreadsheetReadError('unsupported_browser');
    }

    const view = new DataView(buffer);
    const entries = new Map(readZipEntries(view).map(entry => [entry.name, entry]));
    const read = async (path: string) => {
        const entry = entries.get(path);
        return entry ? readZipEntry(view, entry) : null;
    };

    const workbookXml = await read('xl/workbook.xml');
    if (!workbookXml) {
        throw new SpreadsheetReadError('unreadable');
    }

    const parser = new DOMParser();
    const workbook = parser.parseFromString(workbookXml, 'application/xml');
    const date1904 = ['1', 'true'].includes(elements(workbook, 'workbookPr')[0]?.getAttribute('date1904') ?? '');
    const relationships = new Map(
        elements(parser.parseFromString(await read('xl/_rels/workbook.xml.rels') ?? '<r/>', 'application/xml'), 'Relationship')
            .map(relationship => [relationship.getAttribute('Id'), relationship.getAttribute('Target') ?? '']),
    );
    const sharedStringsXml = await read('xl/sharedStrings.xml');
    const sharedStrings = sharedStringsXml
        ? elements(parser.parseFromString(sharedStringsXml, 'application/xml'), 'si').map(textOf)
        : [];

    return Promise.all(elements(workbook, 'sheet').map(async sheet => {
        const relationshipId = sheet.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')
            ?? sheet.getAttribute('r:id');
        const target = relationships.get(relationshipId) ?? '';
        const path = target.startsWith('/') ? target.slice(1) : `xl/${target.replace(/^\.\//, '')}`;
        const xml = await read(path);

        return {
            name: sheet.getAttribute('name') ?? '',
            rows: xml ? parseSheetXml(xml, sharedStrings) : [],
            date1904,
        };
    }));
};

export const readSpreadsheetFile = async (file: File): Promise<SpreadsheetSheet[]> => {
    const extension = file.name.split('.').pop()?.toLowerCase();

    if (extension === 'xlsx') {
        try {
            return await readXlsx(await file.arrayBuffer());
        } catch (error) {
            throw error instanceof SpreadsheetReadError ? error : new SpreadsheetReadError('unreadable');
        }
    }

    if (extension === 'csv' || extension === 'tsv' || extension === 'txt') {
        const text = decodeText(new Uint8Array(await file.arrayBuffer()));
        return [{name: file.name, rows: parseDelimitedText(text, extension === 'tsv' ? '\t' : undefined)}];
    }

    throw new SpreadsheetReadError('unsupported_type');
};
