/* eslint-disable lingui/no-unlocalized-strings */
import type {LineupBillingTier} from "../api/lineup.client.ts";
import type {SpreadsheetCell, SpreadsheetRow, SpreadsheetSheet} from "./spreadsheetReader.ts";

const MAX_IMPORT_PEOPLE = 500;
const MAX_IMPORT_SLOTS = 1000;
export const MAX_LINKS = 12;

const LIMITS = {
    name: 255,
    tagline: 255,
    role: 100,
    stage: 100,
    title: 255,
    longText: 20000,
    peoplePerSlot: 50,
};

const HEADER_SEARCH_ROWS = 10;
const EXCEL_EPOCH_MS = Date.UTC(1899, 11, 30);
const EXCEL_1904_OFFSET_DAYS = 1462;
const DAY_MS = 86_400_000;

export type PeopleColumn = 'name' | 'billing' | 'role' | 'tagline' | 'bio' | 'links' | 'hidden';
export type ScheduleColumn = 'date' | 'start' | 'end' | 'duration' | 'stage' | 'title' | 'people' | 'description';

export type ColumnAliases<T extends string> = Record<T, string[]>;

export const PEOPLE_COLUMN_ALIASES: ColumnAliases<PeopleColumn> = {
    name: ['name', 'full name', 'person', 'speaker', 'artist', 'act', 'performer', 'host', 'band'],
    billing: ['billing', 'billing tier', 'tier'],
    role: ['role', 'role label'],
    tagline: ['tagline', 'subtitle', 'headline', 'job title', 'genre'],
    bio: ['bio', 'biography', 'about'],
    links: ['links', 'link', 'website', 'url', 'urls'],
    hidden: ['hidden', 'hide'],
};

export const SCHEDULE_COLUMN_ALIASES: ColumnAliases<ScheduleColumn> = {
    date: ['date', 'day'],
    start: ['start', 'starts', 'start time', 'starts at', 'from', 'time'],
    end: ['end', 'ends', 'end time', 'ends at', 'until', 'to', 'finish'],
    duration: ['duration', 'duration min', 'length', 'minutes', 'mins'],
    stage: ['stage', 'room', 'stage or room', 'space', 'track', 'venue area'],
    title: ['title', 'session', 'session title', 'what', 'name', 'talk'],
    people: ['people', 'speakers', 'speaker', 'artists', 'artist', 'performers', 'performer', 'hosts', 'host', 'acts', 'act', 'who', 'lineup'],
    description: ['description', 'details', 'abstract'],
};

export type RowStatus = 'new' | 'add' | 'update' | 'unchanged' | 'duplicate' | 'error';

export type RowError =
    | 'missing_name'
    | 'missing_start'
    | 'bad_date'
    | 'bad_time'
    | 'missing_end'
    | 'end_before_start'
    | 'empty_slot'
    | 'too_long'
    | 'too_many_people'
    | 'too_many_rows';

export interface PersonImportRow {
    row: number;
    sheet: string;
    name: string;
    tagline: string | null;
    bio: string | null;
    links: string[];
    billingTier: LineupBillingTier | null;
    roleLabel: string | null;
    isHidden: boolean | null;
    status: RowStatus;
    error?: RowError;
}

export interface SlotImportRow {
    row: number;
    sheet: string;
    startsAt: string;
    endsAt: string;
    stage: string | null;
    title: string | null;
    description: string | null;
    people: string[];
    newPeople: string[];
    status: RowStatus;
    error?: RowError;
}

export interface ImportPlan {
    people: PersonImportRow[];
    slots: SlotImportRow[];
    newStages: string[];
    unrecognisedSheets: string[];
    hasAmbiguousDates: boolean;
}

export interface ExistingPerson {
    tagline: string | null;
    bioText: string;
    links: string[];
    billingTier?: LineupBillingTier;
    roleLabel?: string | null;
    isHidden?: boolean;
}

export interface ImportContext {
    profiles: Map<string, ExistingPerson>;
    lineupNames: Set<string>;
    stageNames: Set<string>;
    existingSlotKeys: Set<string>;
    billingLabels: Record<string, LineupBillingTier>;
    yesWords: string[];
    dayFirst: boolean;
    peopleAliases: ColumnAliases<PeopleColumn>;
    scheduleAliases: ColumnAliases<ScheduleColumn>;
}

const normaliseKey = (value: string): string => value
    .normalize('NFKC')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();

export const nameKey = (value: string): string => value.trim().replace(/\s+/gu, ' ').toLowerCase();

export const htmlToPlainKey = (html: string | null | undefined): string => nameKey(
    (html ?? '')
        .replace(/<(br|\/p|\/div|\/li)[^>]*>/gi, ' ')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#0?39;/g, "'"),
);

const cellText = (cell: SpreadsheetCell | undefined): string => cell === null || cell === undefined ? '' : String(cell).trim();

const blankToNull = (value: string): string | null => value === '' ? null : value;

const findColumns = <T extends string>(header: SpreadsheetCell[], aliases: ColumnAliases<T>): Partial<Record<T, number>> => {
    const normalisedHeader = header.map(cell => normaliseKey(cellText(cell)));
    const columns: Partial<Record<T, number>> = {};

    (Object.keys(aliases) as T[]).forEach(column => {
        const wanted = aliases[column].map(normaliseKey);
        const index = normalisedHeader.findIndex((cell, position) => wanted.includes(cell)
            && !Object.values(columns).includes(position));
        if (index >= 0) {
            columns[column] = index;
        }
    });

    return columns;
};

const isScheduleHeader = (columns: Partial<Record<ScheduleColumn, number>>) => columns.start !== undefined
    && (columns.end !== undefined || columns.duration !== undefined || columns.date !== undefined);

const pad = (value: number) => String(value).padStart(2, '0');

const formatDate = (year: number, month: number, day: number): string | null => {
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
        return null;
    }
    return `${year}-${pad(month)}-${pad(day)}`;
};

const addDays = (isoDate: string, days: number): string => {
    const [year, month, day] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day + days));
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
};

const NUMERIC_DATE = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4}|\d{2})(?!\d)/;

const isAmbiguousDate = (cell: SpreadsheetCell | undefined): boolean => {
    const match = cellText(cell).match(NUMERIC_DATE);
    return !!match && Number(match[1]) <= 12 && Number(match[2]) <= 12 && match[1] !== match[2];
};

export const parseDateCell = (cell: SpreadsheetCell | undefined, dayFirst: boolean, date1904 = false): string | null => {
    if (typeof cell === 'number') {
        if (cell < 1) {
            return null;
        }
        const days = Math.floor(cell) + (date1904 ? EXCEL_1904_OFFSET_DAYS : 0);
        const date = new Date(EXCEL_EPOCH_MS + days * DAY_MS);
        return formatDate(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate());
    }

    const text = cellText(cell);
    const iso = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (iso) {
        return formatDate(Number(iso[1]), Number(iso[2]), Number(iso[3]));
    }

    const numeric = text.match(NUMERIC_DATE);
    if (numeric) {
        const year = numeric[3].length === 2 ? 2000 + Number(numeric[3]) : Number(numeric[3]);
        const [day, month] = dayFirst ? [Number(numeric[1]), Number(numeric[2])] : [Number(numeric[2]), Number(numeric[1])];
        return formatDate(year, month, day);
    }

    if (/\p{L}/u.test(text) && /\b\d{4}\b/.test(text)) {
        const parsed = new Date(text.replace(/\b\d{1,2}[:.]\d{2}.*$/, '').trim());
        if (!Number.isNaN(parsed.getTime())) {
            return formatDate(parsed.getFullYear(), parsed.getMonth() + 1, parsed.getDate());
        }
    }

    return null;
};

const stripDatePrefix = (text: string) => text
    .replace(/^\d{4}-\d{1,2}-\d{1,2}[t\s]+/i, '')
    .replace(/^\d{1,2}[/.-]\d{1,2}[/.-]\d{2,4}[,\s]+/, '');

export const parseTimeCell = (cell: SpreadsheetCell | undefined): number | null => {
    if (typeof cell === 'number') {
        if (Number.isInteger(cell) && cell >= 0 && cell < 24) {
            return cell * 60;
        }
        const fraction = cell - Math.floor(cell);
        return Math.round(fraction * 1440) % 1440;
    }

    const text = stripDatePrefix(cellText(cell).toLowerCase());
    const match = text.match(/^(\d{1,2})(?:[:.h](\d{2}))?(?::\d{2})?\s*(am|pm|a\.m\.|p\.m\.)?$/);
    if (!match || (match[2] === undefined && match[3] === undefined)) {
        return null;
    }

    let hours = Number(match[1]);
    const minutes = Number(match[2] ?? 0);
    const meridiem = match[3]?.replace(/\./g, '');

    if (minutes > 59 || hours > 23 || (meridiem && (hours < 1 || hours > 12))) {
        return null;
    }
    if (meridiem === 'pm' && hours < 12) {
        hours += 12;
    }
    if (meridiem === 'am' && hours === 12) {
        hours = 0;
    }

    return hours * 60 + minutes;
};

const splitTimeRange = (cell: SpreadsheetCell | undefined): [string, string] | null => {
    if (typeof cell !== 'string') {
        return null;
    }
    const parts = cell.split(/\s*(?:–|—|-|to)\s*(?=\d)/i);
    return parts.length === 2 ? [parts[0], parts[1]] : null;
};

const parseDuration = (cell: SpreadsheetCell | undefined): number | null => {
    if (typeof cell === 'number') {
        return cell > 0 && cell < 1 ? Math.round(cell * 1440) : cell;
    }
    const text = cellText(cell);
    const clock = text.match(/^(\d{1,2}):(\d{2})$/);
    if (clock) {
        return Number(clock[1]) * 60 + Number(clock[2]);
    }
    const minutes = Number(text.replace(',', '.').replace(/\s*(min|mins|minutes|m)$/i, ''));
    return Number.isFinite(minutes) && minutes > 0 ? minutes : null;
};

const toLocalDateTime = (date: string, minutes: number) => `${date} ${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}:00`;

const dateWithinCell = (cell: SpreadsheetCell | undefined, dayFirst: boolean, date1904: boolean): string | null => {
    if (typeof cell === 'number') {
        return cell >= 1 && !Number.isInteger(cell) ? parseDateCell(cell, dayFirst, date1904) : null;
    }
    const text = cellText(cell);
    const prefix = text.match(/^(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[/.-]\d{1,2}[/.-]\d{2,4})[t\s,]/i);
    return prefix ? parseDateCell(prefix[1], dayFirst) : null;
};

export const splitLinks = (value: string): string[] => value
    .split(/[\s,;]+/)
    .map(link => link.trim())
    .filter(Boolean)
    .map(link => /^www\./i.test(link) ? `https://${link}` : link.replace(/^http:\/\//i, 'https://'))
    .filter(link => {
        try {
            return new URL(link).protocol === 'https:' && link.length <= 500;
        } catch {
            return false;
        }
    });

const splitNames = (value: string): string[] => value
    .split(/\s*(?:;|\n|\s\|\s)\s*/)
    .map(name => name.trim())
    .filter(Boolean);

const escapeHtml = (value: string) => value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

export const plainTextToHtml = (value: string | null): string | null => value === null
    ? null
    : value
        .split(/\n\s*\n/)
        .map(paragraph => paragraph.trim())
        .filter(Boolean)
        .map(paragraph => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`)
        .join('');

export const slotKey = (startsAt: string, endsAt: string, stage: string | null, title: string | null, people: string[]): string => [
    startsAt.slice(0, 16),
    endsAt.slice(0, 16),
    nameKey(stage ?? ''),
    nameKey(title ?? ''),
    people.map(nameKey).sort().join(','),
].join('|');

const parseBoolean = (value: string, yesWords: string[]): boolean | null => {
    if (value === '') {
        return null;
    }
    return ['yes', 'y', 'true', '1', 'x', ...yesWords].map(normaliseKey).includes(normaliseKey(value));
};

const parseBilling = (value: string, labels: Record<string, LineupBillingTier>): LineupBillingTier | null => {
    const key = normaliseKey(value);
    if (key === '') {
        return null;
    }
    const tiers: LineupBillingTier[] = ['HEADLINER', 'FEATURED', 'STANDARD'];
    return tiers.find(tier => normaliseKey(tier) === key)
        ?? Object.entries(labels).find(([label]) => normaliseKey(label) === key)?.[1]
        ?? null;
};

const personChanges = (row: PersonImportRow, existing: ExistingPerson): boolean => {
    const existingLinks = new Set(existing.links);
    return (row.tagline !== null && row.tagline !== existing.tagline)
        || (row.bio !== null && htmlToPlainKey(row.bio) !== existing.bioText)
        || row.links.slice(0, MAX_LINKS).some(link => !existingLinks.has(link))
        || (row.billingTier !== null && row.billingTier !== existing.billingTier)
        || (row.roleLabel !== null && row.roleLabel !== existing.roleLabel)
        || (row.isHidden !== null && row.isHidden !== existing.isHidden);
};

const peopleRows = (sheet: SpreadsheetSheet, rows: SpreadsheetRow[], columns: Partial<Record<PeopleColumn, number>>, context: ImportContext): PersonImportRow[] => {
    const value = (cells: SpreadsheetCell[], column: PeopleColumn) => columns[column] === undefined ? '' : cellText(cells[columns[column]!]);

    return rows.map(({number, cells}) => {
        const name = value(cells, 'name').replace(/\s+/g, ' ');
        const row: PersonImportRow = {
            row: number,
            sheet: sheet.name,
            name,
            tagline: blankToNull(value(cells, 'tagline')),
            bio: plainTextToHtml(blankToNull(value(cells, 'bio'))),
            links: splitLinks(value(cells, 'links')).slice(0, MAX_LINKS),
            billingTier: parseBilling(value(cells, 'billing'), context.billingLabels),
            roleLabel: blankToNull(value(cells, 'role')),
            isHidden: parseBoolean(value(cells, 'hidden'), context.yesWords),
            status: 'new',
        };

        if (name === '') {
            return {...row, status: 'error', error: 'missing_name'};
        }
        if (name.length > LIMITS.name || (row.tagline?.length ?? 0) > LIMITS.tagline
            || (row.roleLabel?.length ?? 0) > LIMITS.role || (row.bio?.length ?? 0) > LIMITS.longText) {
            return {...row, status: 'error', error: 'too_long'};
        }

        const key = nameKey(name);
        const existing = context.profiles.get(key);
        if (!existing) {
            return row;
        }
        if (!context.lineupNames.has(key)) {
            return {...row, status: 'add'};
        }
        return {...row, status: personChanges(row, existing) ? 'update' : 'unchanged'};
    });
};

const slotRows = (sheet: SpreadsheetSheet, rows: SpreadsheetRow[], columns: Partial<Record<ScheduleColumn, number>>, context: ImportContext): SlotImportRow[] => {
    const raw = (cells: SpreadsheetCell[], column: ScheduleColumn) => columns[column] === undefined ? undefined : cells[columns[column]!];
    const value = (cells: SpreadsheetCell[], column: ScheduleColumn) => cellText(raw(cells, column));
    const date1904 = !!sheet.date1904;

    return rows.map(({number, cells}): SlotImportRow => {
        const base: SlotImportRow = {
            row: number,
            sheet: sheet.name,
            startsAt: '',
            endsAt: '',
            stage: blankToNull(value(cells, 'stage')),
            title: blankToNull(value(cells, 'title')),
            description: plainTextToHtml(blankToNull(value(cells, 'description'))),
            people: splitNames(value(cells, 'people')),
            newPeople: [],
            status: 'new',
        };
        const fail = (error: RowError): SlotImportRow => ({...base, status: 'error', error});

        let startCell = raw(cells, 'start');
        let endCell = raw(cells, 'end');
        if (cellText(endCell) === '') {
            const range = splitTimeRange(startCell);
            if (range) {
                [startCell, endCell] = range;
            }
        }

        if (cellText(startCell) === '') {
            return fail('missing_start');
        }

        const date = dateWithinCell(startCell, context.dayFirst, date1904)
            ?? parseDateCell(raw(cells, 'date'), context.dayFirst, date1904);
        if (!date) {
            return fail('bad_date');
        }

        const startMinutes = parseTimeCell(startCell);
        if (startMinutes === null) {
            return fail('bad_time');
        }

        const duration = parseDuration(raw(cells, 'duration'));
        let endsAt: string;

        if (cellText(endCell) !== '') {
            const endMinutes = parseTimeCell(endCell);
            if (endMinutes === null) {
                return fail('bad_time');
            }
            const endDate = dateWithinCell(endCell, context.dayFirst, date1904)
                ?? (endMinutes <= startMinutes ? addDays(date, 1) : date);
            endsAt = toLocalDateTime(endDate, endMinutes);
        } else if (duration !== null && duration > 0) {
            const total = startMinutes + Math.round(duration);
            endsAt = toLocalDateTime(addDays(date, Math.floor(total / 1440)), total % 1440);
        } else {
            return fail('missing_end');
        }

        const startsAt = toLocalDateTime(date, startMinutes);
        if (endsAt <= startsAt) {
            return fail('end_before_start');
        }
        if (!base.title && base.people.length === 0) {
            return fail('empty_slot');
        }
        if (base.people.length > LIMITS.peoplePerSlot) {
            return fail('too_many_people');
        }
        if ((base.title?.length ?? 0) > LIMITS.title || (base.stage?.length ?? 0) > LIMITS.stage
            || (base.description?.length ?? 0) > LIMITS.longText || base.people.some(name => name.length > LIMITS.name)) {
            return fail('too_long');
        }

        return {...base, startsAt, endsAt};
    });
};

const locateHeader = (sheet: SpreadsheetSheet, context: ImportContext) => {
    for (let index = 0; index < Math.min(HEADER_SEARCH_ROWS, sheet.rows.length); index++) {
        const cells = sheet.rows[index].cells;
        const scheduleColumns = findColumns(cells, context.scheduleAliases);
        if (isScheduleHeader(scheduleColumns)) {
            return {kind: 'schedule' as const, index, scheduleColumns};
        }
        const peopleColumns = findColumns(cells, context.peopleAliases);
        if (peopleColumns.name !== undefined) {
            return {kind: 'people' as const, index, peopleColumns};
        }
    }
    return null;
};

export const buildImportPlan = (sheets: SpreadsheetSheet[], context: ImportContext): ImportPlan => {
    const plan: ImportPlan = {people: [], slots: [], newStages: [], unrecognisedSheets: [], hasAmbiguousDates: false};

    sheets.forEach(sheet => {
        const header = locateHeader(sheet, context);
        if (!header) {
            if (sheet.rows.length > 0) {
                plan.unrecognisedSheets.push(sheet.name);
            }
            return;
        }

        const dataRows = sheet.rows.slice(header.index + 1);
        if (header.kind === 'schedule') {
            const {date, start, end} = header.scheduleColumns;
            plan.hasAmbiguousDates = plan.hasAmbiguousDates || dataRows.some(({cells}) => [date, start, end]
                .some(column => column !== undefined && isAmbiguousDate(cells[column])));
            plan.slots.push(...slotRows(sheet, dataRows, header.scheduleColumns, context));
        } else {
            plan.people.push(...peopleRows(sheet, dataRows, header.peopleColumns, context));
        }
    });

    const seenPeople = new Set<string>();
    plan.people = plan.people.map(person => {
        if (person.status === 'error') {
            return person;
        }
        const key = nameKey(person.name);
        if (seenPeople.has(key)) {
            return {...person, status: 'duplicate'};
        }
        seenPeople.add(key);
        return seenPeople.size > MAX_IMPORT_PEOPLE ? {...person, status: 'error', error: 'too_many_rows'} : person;
    });

    const knownNames = new Set([...context.profiles.keys(), ...seenPeople]);
    const newPeople = new Set<string>();
    const seenSlots = new Set(context.existingSlotKeys);
    const stages = new Map<string, string>();
    let accepted = 0;

    plan.slots = plan.slots.map(slot => {
        if (slot.status === 'error') {
            return slot;
        }
        const key = slotKey(slot.startsAt, slot.endsAt, slot.stage, slot.title, slot.people);
        if (seenSlots.has(key)) {
            return {...slot, status: 'duplicate'};
        }
        seenSlots.add(key);
        accepted++;
        if (accepted > MAX_IMPORT_SLOTS) {
            return {...slot, status: 'error', error: 'too_many_rows'};
        }

        const stageKey = nameKey(slot.stage ?? '');
        if (stageKey && !context.stageNames.has(stageKey) && !stages.has(stageKey)) {
            stages.set(stageKey, slot.stage!);
        }

        const unknown = slot.people.filter(name => {
            const key = nameKey(name);
            if (knownNames.has(key) || newPeople.has(key)) {
                return false;
            }
            newPeople.add(key);
            return true;
        });

        return {...slot, newPeople: unknown};
    });

    plan.newStages = [...stages.values()];

    return plan;
};

export const peopleToAdd = (plan: ImportPlan): number => plan.people.filter(person => person.status === 'new' || person.status === 'add').length
    + new Set(plan.slots.flatMap(slot => slot.newPeople.map(nameKey))).size;

export const importPayload = (plan: ImportPlan) => {
    const people = plan.people.filter(person => person.status === 'new' || person.status === 'add' || person.status === 'update');
    const slots = plan.slots.filter(slot => slot.status === 'new');

    return {
        rows: {people, slots},
        payload: {
            people: people.map(person => ({
                name: person.name,
                tagline: person.tagline,
                bio: person.bio,
                links: person.links,
                billing_tier: person.billingTier,
                role_label: person.roleLabel,
                is_hidden: person.isHidden,
            })),
            slots: slots.map(slot => ({
                starts_at: slot.startsAt,
                ends_at: slot.endsAt,
                stage: slot.stage,
                title: slot.title,
                description: slot.description,
                people: slot.people,
            })),
        },
    };
};
