import {t} from "@lingui/macro";
import {alphaLabel} from "../lib/generateSeats.ts";
import {Point} from "../lib/geometry.ts";
import {
    BlockElement,
    LabelElement,
    ObjectElement,
    ObjectKind,
    SeatMapArea,
    TableElement,
    ZoneElement,
} from "../lib/types.ts";
import {newId} from "./ops/elements.ts";

const SEAT_SPACING = 26;
const ROW_SPACING = 32;
const SEAT_SIZE = 18;
const MIN_DRAG = 10;
const MIN_OBJECT_SIZE = 24;

export interface DragRect {
    from: Point;
    to: Point;
}

const nextStartRow = (area: SeatMapArea): string => {
    const usedRows = area.elements.reduce((total, element) => {
        if (element.type === 'row') {
            return total + 1;
        }
        return element.type === 'block' ? total + element.rows : total;
    }, 0);
    return alphaLabel(usedRows);
};

export const newRows = (area: SeatMapArea, band: string, {from, to}: DragRect): BlockElement | null => {
    const width = Math.abs(to.x - from.x);
    const height = Math.abs(to.y - from.y);
    if (width < MIN_DRAG && height < MIN_DRAG) {
        return null;
    }
    const cols = Math.floor(width / SEAT_SPACING) + 1;
    const rows = Math.floor(height / ROW_SPACING) + 1;

    return {
        id: newId('e'),
        type: 'block',
        name: t`Section`,
        x: from.x + (Math.sign(to.x - from.x) * (cols - 1) * SEAT_SPACING) / 2,
        y: to.y < from.y ? from.y - (rows - 1) * ROW_SPACING : from.y,
        rotation: 0,
        rows,
        cols,
        spacing: SEAT_SPACING,
        rowSpacing: ROW_SPACING,
        curve: 0,
        taper: 0,
        aisles: [],
        rowLabelStyle: 'alpha',
        startRow: nextStartRow(area),
        startSeat: 1,
        numbering: 'seq',
        band,
        seatSize: SEAT_SIZE,
        overrides: {},
        seats: [],
    };
};

export const newTable = (area: SeatMapArea, band: string, point: Point): TableElement => ({
    id: newId('e'),
    type: 'table',
    shape: 'round',
    label: `T${area.elements.filter(element => element.type === 'table').length + 1}`,
    x: point.x,
    y: point.y,
    rotation: 0,
    chairs: 8,
    d: 90,
    w: 150,
    h: 70,
    gap: 16,
    band,
    seatSize: SEAT_SIZE,
    overrides: {},
    seats: [],
});

export const newZone = (band: string, pts: [number, number][]): ZoneElement => ({
    id: newId('e'),
    type: 'zone',
    label: t`Standing`,
    pts,
    capacity: 100,
    band,
});

const OBJECT_LABELS: Record<ObjectKind, () => string> = {
    stage: () => t`Stage`,
    floor: () => t`Dance floor`,
    bar: () => t`Bar`,
    entrance: () => t`Entrance`,
    pillar: () => '',
    wall: () => '',
};

export const newObject = (kind: ObjectKind, {from, to}: DragRect): ObjectElement | null => {
    const width = Math.abs(to.x - from.x);
    const height = Math.abs(to.y - from.y);
    if (width < MIN_DRAG && height < MIN_DRAG) {
        return null;
    }
    return {
        id: newId('e'),
        type: 'object',
        kind,
        x: (from.x + to.x) / 2,
        y: (from.y + to.y) / 2,
        w: Math.max(MIN_OBJECT_SIZE, width),
        h: Math.max(MIN_OBJECT_SIZE, height),
        rotation: 0,
        label: OBJECT_LABELS[kind](),
    };
};

export const newLabel = (point: Point): LabelElement => ({
    id: newId('e'),
    type: 'label',
    text: t`Label`,
    x: point.x,
    y: point.y,
    rotation: 0,
    size: 20,
});
