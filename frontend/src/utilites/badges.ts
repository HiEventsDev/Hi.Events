import {t} from "@lingui/macro";
import {
    BadgeBackMode,
    BadgeDesign,
    BadgeFieldSource,
    BadgeLayout,
    BadgeQuery,
    BadgeSheet,
    BadgeSize,
    BadgeTemplate,
    BadgeTextSize,
    IdParam,
    Image,
} from "../types.ts";

export const MM_TO_PX = 96 / 25.4;

export const BADGE_SIZE_DIMENSIONS: Record<Exclude<BadgeSize, 'CUSTOM'>, { width: number; height: number }> = {
    A6_PORTRAIT: {width: 105, height: 148},
    A7_PORTRAIT: {width: 74, height: 105},
    IN_4X3_LANDSCAPE: {width: 101.6, height: 76.2},
    IN_3X4_PORTRAIT: {width: 76.2, height: 101.6},
    IN_4X6_PORTRAIT: {width: 101.6, height: 152.4},
    CR80_PORTRAIT: {width: 53.98, height: 85.6},
    CR80_LANDSCAPE: {width: 85.6, height: 53.98},
    LABEL_62X100: {width: 62, height: 100},
};

export const SHEET_DIMENSIONS: Record<Exclude<BadgeSheet, 'NONE'>, { width: number; height: number }> = {
    A4: {width: 210, height: 297},
    LETTER: {width: 215.9, height: 279.4},
};

export const BADGE_TEMPLATES: BadgeTemplate[] = ['CLASSIC', 'BANNER', 'MINIMAL', 'BOLD', 'SIDEBAR', 'ARTWORK'];

export const BADGE_ACCENT_SWATCHES = [
    '#111827',
    '#1d4ed8',
    '#0e7490',
    '#047857',
    '#4d7c0f',
    '#b45309',
    '#c2410c',
    '#b91c1c',
    '#be185d',
    '#6d28d9',
];

export const MAX_BADGE_LINES = 5;

export const getBadgeTemplateLabel = (template: BadgeTemplate): string => ({
    CLASSIC: t`Classic`,
    BANNER: t`Banner`,
    MINIMAL: t`Minimal`,
    BOLD: t`Bold`,
    SIDEBAR: t`Sidebar`,
    ARTWORK: t`Artwork`,
}[template]);

export const getBadgeTemplateDescription = (template: BadgeTemplate): string => ({
    CLASSIC: t`Logo up top, big name, ticket colour along the bottom`,
    BANNER: t`A coloured or photo header with your event name`,
    MINIMAL: t`Quiet and typographic, lots of white space`,
    BOLD: t`Your brand colour edge to edge, with a huge name`,
    SIDEBAR: t`A vertical ticket-colour strip down the side`,
    ARTWORK: t`Your artwork full bleed behind a name card`,
}[template]);

export const getBadgeSizeLabel = (size: BadgeSize): string => ({
    A6_PORTRAIT: t`A6 portrait (105 × 148 mm)`,
    A7_PORTRAIT: t`A7 portrait (74 × 105 mm)`,
    IN_4X3_LANDSCAPE: t`4 × 3 in landscape`,
    IN_3X4_PORTRAIT: t`3 × 4 in portrait`,
    IN_4X6_PORTRAIT: t`4 × 6 in portrait`,
    CR80_PORTRAIT: t`ID card portrait (CR80)`,
    CR80_LANDSCAPE: t`ID card landscape (CR80)`,
    LABEL_62X100: t`62 mm label roll (62 × 100 mm)`,
    CUSTOM: t`Custom size`,
}[size]);

export const getBadgeSheetLabel = (sheet: BadgeSheet): string => ({
    A4: t`A4 sheets`,
    LETTER: t`US Letter sheets`,
    NONE: t`One badge per page`,
}[sheet]);

export const getBadgeFieldSourceLabel = (source: BadgeFieldSource): string => ({
    FIRST_NAME: t`First name`,
    LAST_NAME: t`Last name`,
    FULL_NAME: t`Full name`,
    TICKET_TYPE: t`Ticket type`,
    QUESTION: t`Registration question`,
    OCCURRENCE_DATE: t`Event date`,
    CUSTOM_TEXT: t`Custom text`,
}[source]);

export const getBadgeTextSizeLabel = (size: BadgeTextSize): string => ({
    XL: t`Huge`,
    L: t`Large`,
    M: t`Medium`,
    S: t`Small`,
}[size]);

export const getBadgeBackModeLabel = (mode: BadgeBackMode): string => ({
    NONE: t`Single-sided`,
    SAME: t`Same on both sides`,
    INFO: t`Event info on the back`,
}[mode]);

export const getBadgeDimensions = (layout: Pick<BadgeLayout, 'size' | 'custom_width_mm' | 'custom_height_mm'>) => {
    if (layout.size === 'CUSTOM') {
        return {
            width: layout.custom_width_mm || 100,
            height: layout.custom_height_mm || 140,
        };
    }

    return BADGE_SIZE_DIMENSIONS[layout.size];
};

export interface SheetGrid {
    pageWidth: number;
    pageHeight: number;
    columns: number;
    rows: number;
    perPage: number;
    offsetX: number;
    offsetY: number;
}

export const computeSheetGrid = (badge: { width: number; height: number }, sheet: BadgeSheet): SheetGrid => {
    if (sheet !== 'NONE') {
        const page = SHEET_DIMENSIONS[sheet];
        const columns = Math.floor((page.width + 0.01) / badge.width);
        const rows = Math.floor((page.height + 0.01) / badge.height);

        if (columns > 0 && rows > 0) {
            return {
                pageWidth: page.width,
                pageHeight: page.height,
                columns,
                rows,
                perPage: columns * rows,
                offsetX: (page.width - columns * badge.width) / 2,
                offsetY: (page.height - rows * badge.height) / 2,
            };
        }
    }

    return {
        pageWidth: badge.width,
        pageHeight: badge.height,
        columns: 1,
        rows: 1,
        perPage: 1,
        offsetX: 0,
        offsetY: 0,
    };
};

export const chunk = <T, >(items: T[], size: number): T[][] => {
    const chunks: T[][] = [];
    for (let i = 0; i < items.length; i += size) {
        chunks.push(items.slice(i, i + size));
    }
    return chunks;
};

export const mirrorRowsForDuplex = <T, >(page: (T | null)[], columns: number): (T | null)[] => {
    const mirrored: (T | null)[] = [];
    for (let i = 0; i < page.length; i += columns) {
        const row = page.slice(i, i + columns);
        while (row.length < columns) {
            row.push(null);
        }
        mirrored.push(...row.reverse());
    }
    return mirrored;
};

export type BadgeBackPrinting = 'NONE' | 'DUPLEX' | 'FOLD_OVER';

export type BadgeFold = 'SIDE_BY_SIDE' | 'STACKED';

export const getDesignBackPrinting = (layout: Pick<BadgeLayout, 'back_mode' | 'fold_over'>): BadgeBackPrinting => {
    if (layout.back_mode === 'NONE') {
        return 'NONE';
    }
    return layout.fold_over ? 'FOLD_OVER' : 'DUPLEX';
};

export const getBadgeFold = (badge: { width: number; height: number }): BadgeFold => (
    badge.width > badge.height ? 'STACKED' : 'SIDE_BY_SIDE'
);

export const getPrintUnitSize = (badge: { width: number; height: number }, backPrinting: BadgeBackPrinting) => {
    if (backPrinting !== 'FOLD_OVER') {
        return badge;
    }
    return getBadgeFold(badge) === 'STACKED'
        ? {width: badge.width, height: badge.height * 2}
        : {width: badge.width * 2, height: badge.height};
};

export interface BadgePrintPage<T> {
    key: string;
    side: 'front' | 'back' | 'fold';
    cells: (T | null)[];
}

export const buildBadgePrintPages = <T, >(items: T[], grid: SheetGrid, backPrinting: BadgeBackPrinting): BadgePrintPage<T>[] => (
    chunk(items, grid.perPage).flatMap((page, index): BadgePrintPage<T>[] => {
        if (backPrinting === 'FOLD_OVER') {
            return [{key: `fold-${index}`, side: 'fold', cells: page}];
        }
        const front: BadgePrintPage<T> = {key: `front-${index}`, side: 'front', cells: page};
        if (backPrinting === 'NONE') {
            return [front];
        }
        return [front, {key: `back-${index}`, side: 'back', cells: mirrorRowsForDuplex(page, grid.columns)}];
    })
);

export const countBadgePrintPages = (badgeCount: number, grid: SheetGrid, backPrinting: BadgeBackPrinting): number => (
    Math.ceil(badgeCount / grid.perPage) * (backPrinting === 'DUPLEX' ? 2 : 1)
);

const hexToRgb = (hex: string): [number, number, number] | null => {
    const match = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
    if (!match) {
        return null;
    }
    const value = parseInt(match[1], 16);
    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
};

const relativeLuminance = (hex: string): number => {
    const rgb = hexToRgb(hex);
    if (!rgb) {
        return 1;
    }
    const [r, g, b] = rgb.map((channel) => {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export const readableTextOn = (background: string): string => (
    relativeLuminance(background) > 0.42 ? '#111827' : '#ffffff'
);

export interface BadgeImages {
    logoUrl?: string;
    artworkUrl?: string;
    sponsorsUrl?: string;
}

export const getBadgeImages = (images: Image[] | undefined): BadgeImages => {
    const byType = (type: Image['type']) => images?.find((image) => image.type === type)?.url;

    return {
        logoUrl: byType('BADGE_LOGO') ?? byType('TICKET_LOGO'),
        artworkUrl: byType('BADGE_ARTWORK'),
        sponsorsUrl: byType('BADGE_SPONSORS'),
    };
};

export interface BadgePrintOptions extends BadgeQuery {
    sheet?: BadgeSheet;
    sample?: boolean;
}

export const buildBadgePrintUrl = (eventId: IdParam, options: BadgePrintOptions = {}): string => {
    const params = new URLSearchParams();
    if (options.attendee_ids?.length) params.set('attendee_ids', options.attendee_ids.join(','));
    if (options.product_ids?.length) params.set('product_ids', options.product_ids.join(','));
    if (options.order_id) params.set('order_id', String(options.order_id));
    if (options.event_occurrence_id) params.set('event_occurrence_id', String(options.event_occurrence_id));
    if (options.only_unprinted) params.set('only_unprinted', '1');
    if (options.sort_by) params.set('sort_by', options.sort_by);
    if (options.sheet) params.set('sheet', options.sheet);
    if (options.sample) params.set('sample', '1');
    const qs = params.toString();
    return `/manage/event/${eventId}/badges/print${qs ? `?${qs}` : ''}`;
};

export const openBadgePrintWindow = (eventId: IdParam, options: BadgePrintOptions = {}) => {
    window?.open(buildBadgePrintUrl(eventId, options), '_blank');
};

export const DEFAULT_BADGE_DESIGN: BadgeDesign = {
    template: 'CLASSIC',
    size: 'A6_PORTRAIT',
    custom_width_mm: null,
    custom_height_mm: null,
    sheet: 'A4',
    accent_color: '#111827',
    background_color: null,
    text_color: null,
    font_family: 'Inter',
    text_align: null,
    uppercase_names: false,
    lines: [
        {source: 'FIRST_NAME', size: 'XL'},
        {source: 'LAST_NAME', size: 'L'},
    ],
    show_logo: true,
    show_qr: true,
    show_ribbon: true,
    show_seat: true,
    show_event_name: true,
    show_sponsors: true,
    footer_text: null,
    ribbons: [],
    back_mode: 'NONE',
    back_text: null,
    fold_over: false,
};
