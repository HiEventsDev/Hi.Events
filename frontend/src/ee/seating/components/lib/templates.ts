import {t} from "@lingui/macro";
import {withGeneratedSeats} from "./generateSeats.ts";
import {
    BlockElement,
    LabelElement,
    ObjectElement,
    ObjectKind,
    RowElement,
    SEAT_MAP_SCHEMA_VERSION,
    SeatMapArea,
    SeatMapElement,
    SeatMapLayout,
    SeatMapBand,
    SeatOverride,
    TableElement,
    ZoneElement,
} from "./types.ts";

export type SeatMapTemplateName = 'theatre' | 'banquet' | 'club' | 'conference' | 'thrust' | 'empty';

export const SEAT_MAP_TEMPLATE_NAMES: SeatMapTemplateName[] = ['theatre', 'banquet', 'club', 'conference', 'thrust', 'empty'];

const PREMIUM = 'b_premium';
const STANDARD = 'b_standard';
const VALUE = 'b_value';

const defaultBands = (): SeatMapBand[] => [
    {key: PREMIUM, name: t`Premium`, color: '#40296c'},
    {key: STANDARD, name: t`Standard`, color: '#755fb1'},
    {key: VALUE, name: t`Value`, color: '#b9a9d8'},
];

type WithoutGenerated<T> = Omit<T, 'id' | 'type' | 'seats'>;

class TemplateBuilder {
    private nextId = 1;
    private readonly areas: SeatMapArea[] = [];

    area(name: string, level: number, focal: SeatMapArea['focal'], elements: SeatMapElement[]): this {
        this.areas.push({id: `a${this.areas.length + 1}`, name, level, focal, elements});
        return this;
    }

    layout(): SeatMapLayout {
        return withGeneratedSeats({schema: SEAT_MAP_SCHEMA_VERSION, bands: defaultBands(), areas: this.areas});
    }

    block(params: Partial<WithoutGenerated<BlockElement>> & Pick<BlockElement, 'name' | 'x' | 'y' | 'rows' | 'cols'>): BlockElement {
        return {
            id: this.id(), type: 'block', rotation: 0, spacing: 24, rowSpacing: 30, curve: 0, taper: 0, aisles: [],
            rowLabelStyle: 'alpha', startRow: 'A', startSeat: 1, numbering: 'seq', band: STANDARD, seatSize: 18,
            overrides: {}, seats: [], ...params,
        };
    }

    accessibleRow(x: number, y: number, count: number, spacing: number, curve: number): RowElement {
        const overrides: Record<string, SeatOverride> = {};
        for (let index = 0; index < count; index++) {
            overrides[`0.${index}`] = {acc: true};
        }
        return {
            id: this.id(), type: 'row', name: t`Accessible`, x, y, rotation: 0, count, spacing, curve, aisles: [],
            rowLabelStyle: 'alpha', startRow: 'W', startSeat: 1, numbering: 'seq', band: STANDARD, seatSize: 22,
            overrides, seats: [],
        };
    }

    table(params: Partial<WithoutGenerated<TableElement>> & Pick<TableElement, 'label' | 'shape' | 'x' | 'y' | 'chairs'>): TableElement {
        return {
            id: this.id(), type: 'table', rotation: 0, d: 96, w: 150, h: 70, gap: 18, band: STANDARD, seatSize: 18,
            overrides: {}, seats: [], ...params,
        };
    }

    zone(label: string, pts: [number, number][], capacity: number, band: string): ZoneElement {
        return {id: this.id('z'), type: 'zone', label, pts, capacity, band};
    }

    object(kind: ObjectKind, x: number, y: number, w: number, h: number, label = '', rotation = 0): ObjectElement {
        return {id: this.id(), type: 'object', kind, x, y, w, h, rotation, label};
    }

    label(text: string, x: number, y: number, size: number): LabelElement {
        return {id: this.id(), type: 'label', text, x, y, size, rotation: 0};
    }

    private id(prefix = 'e'): string {
        return `${prefix}${this.nextId++}`;
    }
}

const theatre = (): SeatMapLayout => {
    const b = new TemplateBuilder();
    return b
        .area(t`Stalls`, 0, {x: 520, y: 80}, [
            b.object('stage', 520, 80, 520, 84, t`Stage`),
            b.block({name: t`Stalls A–D`, x: 520, y: 250, rows: 4, cols: 20, curve: -30, taper: 1, aisles: [7, 14], band: PREMIUM}),
            b.block({name: t`Stalls E–K`, x: 520, y: 400, rows: 7, cols: 22, curve: -34, taper: 1, aisles: [8, 16], startRow: 'E'}),
            b.block({name: t`Left wing`, section: t`Left`, x: 100, y: 330, rows: 5, cols: 4, curve: -8, taper: 1, rotation: 28}),
            b.block({name: t`Right wing`, section: t`Right`, x: 940, y: 330, rows: 5, cols: 4, curve: -8, taper: 1, rotation: -28}),
            b.accessibleRow(520, 640, 6, 42, -10),
            b.object('pillar', 130, 660, 36, 36),
            b.object('pillar', 910, 660, 36, 36),
            b.object('entrance', 520, 760, 180, 34, t`Foyer`),
        ])
        .area(t`Balcony`, 1, {x: 520, y: 80}, [
            b.object('wall', 520, 170, 780, 14),
            b.block({name: t`Balcony`, x: 520, y: 280, rows: 3, cols: 24, curve: -26, taper: 1, aisles: [9, 17], band: VALUE}),
            b.object('bar', 520, 430, 240, 56, t`Balcony bar`),
            b.object('entrance', 300, 520, 160, 32, t`Stairs`),
            b.object('entrance', 740, 520, 160, 32, t`Stairs`),
        ])
        .layout();
};

const banquet = (): SeatMapLayout => {
    const b = new TemplateBuilder();
    const rounds = [550, 710, 870].flatMap((y, row) =>
        [170, 410, 650, 890].map((x, column) => b.table({
            label: `T${row * 4 + column + 1}`, shape: 'round', x, y, chairs: row === 0 ? 10 : 8,
            band: row === 0 ? PREMIUM : STANDARD,
        })),
    );
    return b
        .area(t`Banquet hall`, 0, {x: 520, y: 70}, [
            b.object('stage', 520, 70, 320, 70, t`Stage`),
            b.object('floor', 520, 220, 380, 160, t`Dance floor`),
            b.object('bar', 1010, 280, 70, 300, t`Bar`),
            b.table({label: t`Head`, shape: 'rect', x: 520, y: 390, chairs: 8, w: 300, h: 80, band: PREMIUM}),
            ...rounds,
            b.object('entrance', 520, 970, 180, 34, t`Entrance`),
        ])
        .layout();
};

const club = (): SeatMapLayout => {
    const b = new TemplateBuilder();
    const booths = (y: number, prefix: string) => [200, 400, 640, 840].map((x, index) =>
        b.table({label: `${prefix}${index + 1}`, shape: 'rect', x, y, chairs: 6, w: 130, h: 60, gap: 15, band: PREMIUM}),
    );
    return b
        .area(t`Main floor`, 0, {x: 520, y: 90}, [
            b.object('stage', 520, 90, 620, 100, t`Stage`),
            b.zone(t`Front pit`, [[250, 190], [790, 190], [820, 340], [220, 340]], 220, PREMIUM),
            b.zone(t`Main floor`, [[180, 350], [860, 350], [910, 620], [130, 620]], 480, STANDARD),
            b.object('bar', 80, 470, 64, 280, t`Bar`),
            b.object('bar', 960, 470, 64, 280, t`Bar`),
            ...booths(720, 'B'),
            b.object('pillar', 120, 200, 36, 36),
            b.object('pillar', 920, 200, 36, 36),
            b.object('entrance', 520, 820, 200, 34, t`Entrance`),
            b.label(t`Booths`, 520, 640, 18),
        ])
        .area(t`Mezzanine`, 1, {x: 520, y: 90}, [
            b.zone(t`Mezzanine standing`, [[200, 180], [840, 180], [840, 300], [200, 300]], 140, PREMIUM),
            b.object('wall', 520, 166, 660, 12),
            ...booths(370, 'M'),
            b.object('bar', 520, 470, 240, 56, t`Mezzanine bar`),
            b.object('entrance', 150, 470, 150, 32, t`Stairs`),
        ])
        .layout();
};

const conference = (): SeatMapLayout => {
    const b = new TemplateBuilder();
    const section = (name: string, x: number, y: number, rows: number, startRow: string, startSeat: number, band: string) =>
        b.block({name, x, y, rows, cols: 8, spacing: 26, rowSpacing: 34, startRow, startSeat, band});
    return b
        .area(t`Ballroom`, 0, {x: 520, y: 70}, [
            b.object('stage', 520, 70, 440, 74, t`Stage & screen`),
            section(t`Front left`, 300, 230, 5, 'A', 1, PREMIUM),
            section(t`Front right`, 740, 230, 5, 'A', 9, PREMIUM),
            section(t`Rear left`, 300, 430, 6, 'F', 1, STANDARD),
            section(t`Rear right`, 740, 430, 6, 'F', 9, STANDARD),
            b.accessibleRow(520, 660, 6, 44, 0),
            b.object('entrance', 520, 730, 180, 32, t`Doors`),
        ])
        .layout();
};

const thrust = (): SeatMapLayout => {
    const b = new TemplateBuilder();
    return b
        .area(t`Studio`, 0, {x: 520, y: 380}, [
            b.object('stage', 520, 380, 280, 200, t`Thrust stage`),
            b.block({name: t`Front`, x: 520, y: 560, rows: 5, cols: 14, curve: -26, taper: 2, aisles: [7], band: PREMIUM}),
            b.block({name: t`House left`, section: t`Left`, x: 330, y: 380, rows: 4, cols: 8, curve: -14, taper: 1, rotation: 90}),
            b.block({name: t`House right`, section: t`Right`, x: 710, y: 380, rows: 4, cols: 8, curve: -14, taper: 1, rotation: -90}),
            b.block({name: t`Rear bench`, section: t`Rear`, x: 520, y: 210, rows: 2, cols: 12, curve: 22, taper: -1, rotation: 180, band: VALUE}),
            b.zone(t`Standing gallery`, [[90, 95], [270, 85], [290, 205], [120, 230], [75, 160]], 60, VALUE),
            b.object('wall', 520, 60, 820, 16),
            b.object('wall', 930, 400, 16, 700),
            b.object('pillar', 180, 520, 40, 40),
            b.object('pillar', 860, 520, 40, 40),
            b.table({label: t`Press`, shape: 'rect', x: 800, y: 790, chairs: 4, h: 60, gap: 14, rotation: -14}),
            b.object('entrance', 170, 770, 150, 32, t`Entrance`, 12),
        ])
        .layout();
};

const empty = (): SeatMapLayout => {
    const b = new TemplateBuilder();
    return b.area(t`Area 1`, 0, {x: 520, y: 90}, [b.object('stage', 520, 90, 480, 80, t`Stage`)]).layout();
};

const TEMPLATES: Record<SeatMapTemplateName, () => SeatMapLayout> = {theatre, banquet, club, conference, thrust, empty};

export const createSeatMapFromTemplate = (name: SeatMapTemplateName): SeatMapLayout => TEMPLATES[name]();
