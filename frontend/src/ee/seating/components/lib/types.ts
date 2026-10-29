export const SEAT_MAP_SCHEMA_VERSION = 1;

export interface SeatMapBand {
    key: string;
    name: string;
    color: string;
}

export interface SeatOverride {
    band?: string;
    acc?: boolean;
    comp?: boolean;
    note?: string;
    removed?: boolean;
}

export interface GeneratedSeat {
    uid: string;
    row: number;
    n: string;
    label: string;
    x: number;
    y: number;
    a: number;
    band: string;
    acc: boolean;
    comp: boolean;
    note: string | null;
    gapAfter: boolean;
}

export type RowLabelStyle = 'alpha' | 'alphaSkipIO' | 'num' | 'none';
export type RowNumbering = 'seq' | 'odd' | 'oddOnly' | 'evenOnly';
export type NumberingDirection = 'ltr' | 'rtl';
export type BlockNumbering = RowNumbering | 'cont';
export type ObjectKind = 'stage' | 'floor' | 'bar' | 'entrance' | 'pillar' | 'wall';

interface ElementBase {
    id: string;
    x: number;
    y: number;
    rotation: number;
}

interface SeatedElementBase extends ElementBase {
    band: string;
    seatSize: number;
    overrides: Record<string, SeatOverride>;
    seats: GeneratedSeat[];
}

interface RowLabelling {
    name: string;
    section?: string;
    spacing: number;
    curve: number;
    aisles: number[];
    aisleWidth?: number;
    rowLabelStyle: RowLabelStyle;
    startRow: string;
    startSeat: number;
    direction?: NumberingDirection;
    rowLabels?: Record<string, string>;
}

export interface RowElement extends SeatedElementBase, RowLabelling {
    type: 'row';
    count: number;
    numbering: RowNumbering;
}

export interface BlockElement extends SeatedElementBase, RowLabelling {
    type: 'block';
    rows: number;
    cols: number;
    rowSpacing: number;
    taper: number;
    numbering: BlockNumbering;
}

export interface TableElement extends SeatedElementBase {
    type: 'table';
    shape: 'round' | 'rect';
    label: string;
    chairs: number;
    d: number;
    w: number;
    h: number;
    gap: number;
}

export interface ZoneElement {
    id: string;
    type: 'zone';
    label: string;
    pts: [number, number][];
    capacity: number;
    band: string;
}

export interface ObjectElement extends ElementBase {
    type: 'object';
    kind: ObjectKind;
    w: number;
    h: number;
    label: string;
}

export interface LabelElement extends ElementBase {
    type: 'label';
    text: string;
    size: number;
}

export type SeatedElement = RowElement | BlockElement | TableElement;
export type SeatMapElement = SeatedElement | ZoneElement | ObjectElement | LabelElement;

export interface SeatMapArea {
    id: string;
    name: string;
    level: number;
    focal: {x: number; y: number} | null;
    elements: SeatMapElement[];
}

export interface SeatMapLayout {
    schema: typeof SEAT_MAP_SCHEMA_VERSION;
    bands: SeatMapBand[];
    areas: SeatMapArea[];
}

export const isSeatedElement = (element: SeatMapElement): element is SeatedElement =>
    element.type === 'row' || element.type === 'block' || element.type === 'table';
