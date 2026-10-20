import {
    BlockElement,
    GeneratedSeat,
    isSeatedElement,
    NumberingDirection,
    RowElement,
    RowNumbering,
    SeatedElement,
    SeatMapLayout,
    TableElement,
} from "./types.ts";

interface LocalPoint {
    x: number;
    y: number;
    a: number;
}

type RowShape = Pick<RowElement, 'spacing' | 'curve' | 'aisles' | 'aisleWidth'>;
type RowLabels = Pick<RowElement, 'rowLabelStyle' | 'startRow' | 'rowLabels'>;

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const ALPHABET_WITHOUT_I_AND_O = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

const round2 = (value: number) => Math.round(value * 100) / 100;

export const rotate = (x: number, y: number, degrees: number): [number, number] => {
    const radians = (degrees * Math.PI) / 180;
    const cos = Math.cos(radians);
    const sin = Math.sin(radians);
    return [x * cos - y * sin, x * sin + y * cos];
};

export const alphaLabel = (index: number, alphabet = ALPHABET): string => {
    let remaining = Math.max(0, index);
    let label = '';
    do {
        label = alphabet[remaining % alphabet.length] + label;
        remaining = Math.floor(remaining / alphabet.length) - 1;
    } while (remaining >= 0);
    return label;
};

const alphabetIndex = (startRow: string, alphabet: string): number => {
    const letter = startRow.toUpperCase().charAt(0);
    if (letter < 'A' || letter > 'Z') {
        return 0;
    }
    return [...alphabet].findIndex(candidate => candidate >= letter);
};

export const rowLabel = ({rowLabelStyle, startRow, rowLabels}: RowLabels, rowIndex: number): string => {
    const override = rowLabels?.[rowIndex];
    if (override) {
        return override;
    }
    if (rowLabelStyle === 'none') {
        return '';
    }
    if (rowLabelStyle === 'num') {
        return String((parseInt(startRow, 10) || 1) + rowIndex);
    }
    const alphabet = rowLabelStyle === 'alphaSkipIO' ? ALPHABET_WITHOUT_I_AND_O : ALPHABET;
    return alphaLabel(alphabetIndex(startRow, alphabet) + rowIndex, alphabet);
};

const firstWithParity = (start: number, parity: 0 | 1) => (start % 2 === parity ? start : start + 1);

const numbersInSeatOrder = (count: number, numbering: RowNumbering, start: number): number[] => {
    if (numbering === 'odd') {
        const half = Math.floor(count / 2);
        return Array.from({length: count}, (_, index) => (index < half ? 2 * (half - index) : 2 * (index - half) + 1));
    }
    if (numbering === 'oddOnly' || numbering === 'evenOnly') {
        const first = firstWithParity(start, numbering === 'oddOnly' ? 1 : 0);
        return Array.from({length: count}, (_, index) => first + 2 * index);
    }
    return Array.from({length: count}, (_, index) => start + index);
};

const seatNumbers = (count: number, numbering: RowNumbering, start: number, direction: NumberingDirection | undefined): number[] => {
    const numbers = numbersInSeatOrder(count, numbering, start);
    return direction === 'rtl' ? numbers.reverse() : numbers;
};

export const firstRowSeatNumbers = (element: RowElement | BlockElement): number[] =>
    seatNumbers(
        element.type === 'row' ? element.count : Math.max(1, element.cols),
        element.numbering === 'cont' ? 'seq' : element.numbering,
        element.startSeat,
        element.direction,
    );

const rowPoints = (count: number, {spacing, curve, aisles, aisleWidth}: RowShape): LocalPoint[] => {
    const offsets: number[] = [];
    let cursor = 0;
    for (let index = 0; index < count; index++) {
        offsets.push(cursor);
        cursor += spacing + (aisles.includes(index + 1) ? aisleWidth ?? spacing : 0);
    }
    const width = offsets[count - 1] ?? 0;
    return offsets.map(offset => {
        const t = width ? offset / width : 0.5;
        const slope = width ? (curve * 4 * (1 - 2 * t)) / width : 0;
        return {
            x: offset - width / 2,
            y: curve * 4 * t * (1 - t),
            a: (Math.atan(slope) * 180) / Math.PI,
        };
    });
};

const tablePoints = (table: TableElement): LocalPoint[] => {
    const count = Math.max(2, table.chairs);
    if (table.shape === 'round') {
        const radius = table.d / 2 + table.gap;
        return Array.from({length: count}, (_, index) => {
            const theta = (index / count) * Math.PI * 2 - Math.PI / 2;
            return {x: Math.cos(theta) * radius, y: Math.sin(theta) * radius, a: (theta * 180) / Math.PI + 90};
        });
    }

    const {w, h, gap} = table;
    const perSide = Math.max(0, Math.round((count * h) / (2 * (w + h))));
    const top = Math.floor((count - 2 * perSide) / 2);
    const bottom = count - 2 * perSide - top;
    const along = (length: number, index: number, total: number) => (length * (index + 0.5)) / total;

    return [
        ...Array.from({length: top}, (_, i) => ({x: -w / 2 + along(w, i, top), y: -(h / 2 + gap), a: 0})),
        ...Array.from({length: perSide}, (_, i) => ({x: w / 2 + gap, y: -h / 2 + along(h, i, perSide), a: -90})),
        ...Array.from({length: bottom}, (_, i) => ({x: w / 2 - along(w, i, bottom), y: h / 2 + gap, a: 180})),
        ...Array.from({length: perSide}, (_, i) => ({x: -(w / 2 + gap), y: h / 2 - along(h, i, perSide), a: 90})),
    ];
};

interface SeatSlot {
    row: number;
    index: number;
    point: LocalPoint;
    n: string;
    label: string;
    aisleAfter: boolean;
}

const rowSlots = (element: RowElement | BlockElement, row: number, count: number, curve: number, yOffset: number, start: number): SeatSlot[] => {
    const numbers = seatNumbers(count, element.numbering === 'cont' ? 'seq' : element.numbering, start, element.direction);
    const label = rowLabel(element, row);

    return rowPoints(count, {...element, curve}).map((point, index) => {
        const n = String(numbers[index]);
        return {
            row,
            index,
            point: {...point, y: point.y + yOffset},
            n,
            label: [element.section, label ? `${label}-${n}` : n].filter(Boolean).join(' · '),
            aisleAfter: element.aisles.includes(index + 1),
        };
    });
};

const slotsFor = (element: SeatedElement): SeatSlot[] => {
    if (element.type === 'row') {
        return rowSlots(element, 0, element.count, element.curve, 0, element.startSeat);
    }

    if (element.type === 'table') {
        return tablePoints(element).map((point, index) => ({
            row: 0,
            index,
            point,
            n: String(index + 1),
            label: `${element.label}-${index + 1}`,
            aisleAfter: false,
        }));
    }

    const slots: SeatSlot[] = [];
    let continuousStart = element.startSeat;
    for (let row = 0; row < element.rows; row++) {
        const count = Math.max(1, element.cols + element.taper * row);
        const start = element.numbering === 'cont' ? continuousStart : element.startSeat;
        slots.push(...rowSlots(element, row, count, element.curve * (1 + row * 0.05), row * element.rowSpacing, start));
        continuousStart += count;
    }
    return slots;
};

export const seatKey = (row: number, index: number) => `${row}.${index}`;

export const generateSeats = (element: SeatedElement): GeneratedSeat[] => {
    const slots = slotsFor(element);
    const isRemoved = (slot: SeatSlot) => element.overrides[seatKey(slot.row, slot.index)]?.removed === true;

    return slots.flatMap((slot, position) => {
        if (isRemoved(slot)) {
            return [];
        }
        const override = element.overrides[seatKey(slot.row, slot.index)] ?? {};
        const [dx, dy] = rotate(slot.point.x, slot.point.y, element.rotation);
        const next = slots[position + 1];
        const isNextNeighbourRemoved = next !== undefined && next.row === slot.row && next.index === slot.index + 1 && isRemoved(next);

        return [{
            uid: `${element.id}.${seatKey(slot.row, slot.index)}`,
            row: slot.row,
            n: slot.n,
            label: slot.label,
            x: round2(element.x + dx),
            y: round2(element.y + dy),
            a: round2(element.rotation + slot.point.a),
            band: override.band ?? element.band,
            acc: override.acc ?? false,
            comp: override.comp ?? false,
            note: override.note ?? null,
            gapAfter: slot.aisleAfter || isNextNeighbourRemoved,
        }];
    });
};

export const translateSeats = (seats: GeneratedSeat[], dx: number, dy: number): GeneratedSeat[] =>
    seats.map(seat => ({...seat, x: round2(seat.x + dx), y: round2(seat.y + dy)}));

export const withGeneratedSeats = (layout: SeatMapLayout): SeatMapLayout => ({
    ...layout,
    areas: layout.areas.map(area => ({
        ...area,
        elements: area.elements.map(element =>
            isSeatedElement(element) ? {...element, seats: generateSeats(element)} : element,
        ),
    })),
});

export const countSeats = (layout: SeatMapLayout): number =>
    layout.areas.reduce(
        (total, area) => total + area.elements.reduce(
            (areaTotal, element) => areaTotal + (isSeatedElement(element) ? element.seats.length : 0),
            0,
        ),
        0,
    );
