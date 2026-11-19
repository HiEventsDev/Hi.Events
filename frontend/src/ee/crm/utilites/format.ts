import dayjs from "dayjs";
import {getClientLocale, toIntlLocale} from "../../../locales.ts";
import {CrmContact, CrmSegmentDefinition, CrmSegmentPresetKey, CrmSegmentReference} from "../types.ts";

export const contactDisplayName = (contact: Pick<CrmContact, 'full_name' | 'email'>): string => contact.full_name || contact.email;

export const getContactId = (contact: Pick<CrmContact, 'id'>): string => String(contact.id);

export const contactInitials = (contact: Pick<CrmContact, 'full_name' | 'email'>): string => {
    const words = (contact.full_name || contact.email).split(/[\s@.]+/).filter(Boolean);

    return words.slice(0, 2).map(word => word[0]?.toUpperCase()).join('');
};

const AVATAR_COLORS = ['violet', 'grape', 'indigo', 'blue', 'cyan', 'teal', 'green', 'orange', 'pink', 'red'];

export const contactAvatarColor = (contact: Pick<CrmContact, 'email'>): string => {
    let hash = 0;

    for (const character of contact.email) {
        hash = (hash * 31 + character.charCodeAt(0)) | 0;
    }

    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
};

const formatNumber = (value: number, options?: Intl.NumberFormatOptions): string =>
    new Intl.NumberFormat(toIntlLocale(getClientLocale()), options).format(value);

export const formatCount = (value: number): string => formatNumber(value);

export const formatDecimal = (value: number): string => formatNumber(value, {maximumFractionDigits: 1});

export const formatPercent = (value: number): string => `${formatDecimal(value)}%`;

export const monthLabel = (month: string, locale: string): string => dayjs(`${month}-01`).locale(locale).format('MMM YYYY');

type SegmentSelection =
    | { kind: 'all' }
    | { kind: 'preset', preset: CrmSegmentPresetKey }
    | { kind: 'saved', segmentId: number };

export const selectionToReference = (selection: SegmentSelection): CrmSegmentReference | undefined => {
    switch (selection.kind) {
        case 'preset':
            return {preset: selection.preset};
        case 'saved':
            return {segmentId: selection.segmentId};
        default:
            return undefined;
    }
};

export const parseSegmentParam = (value: string | null): SegmentSelection => {
    if (value?.startsWith('preset:')) {
        return {kind: 'preset', preset: value.slice(7) as CrmSegmentPresetKey};
    }
    if (value?.startsWith('saved:') && Number(value.slice(6)) > 0) {
        return {kind: 'saved', segmentId: Number(value.slice(6))};
    }
    return {kind: 'all'};
};

export const segmentParam = (selection: SegmentSelection): string | null => {
    if (selection.kind === 'preset') {
        return `preset:${selection.preset}`;
    }
    if (selection.kind === 'saved') {
        return `saved:${selection.segmentId}`;
    }
    return null;
};

export const parseDefinitionParam = (value: string | undefined): CrmSegmentDefinition | null => {
    if (!value) {
        return null;
    }
    try {
        const parsed = JSON.parse(value);
        return (parsed?.match === 'all' || parsed?.match === 'any') && Array.isArray(parsed.rules) ? parsed : null;
    } catch {
        return null;
    }
};
