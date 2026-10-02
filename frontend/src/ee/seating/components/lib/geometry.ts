import {rotate} from "./generateSeats.ts";
import {isSeatedElement, SeatMapArea, SeatMapElement} from "./types.ts";

export interface Point {
    x: number;
    y: number;
}

export interface Bounds {
    x: number;
    y: number;
    width: number;
    height: number;
}

const SEAT_MARGIN = 3;
const EMPTY_ELEMENT_SIZE = 40;
const LABEL_CHARACTER_WIDTH = 0.62;
const LABEL_LINE_HEIGHT = 1.3;
const LABEL_MIN_WIDTH = 60;

const boundsOf = (points: [number, number][]): Bounds => {
    const xs = points.map(([x]) => x);
    const ys = points.map(([, y]) => y);
    const x = Math.min(...xs);
    const y = Math.min(...ys);
    return {x, y, width: Math.max(...xs) - x, height: Math.max(...ys) - y};
};

const rotatedBox = (cx: number, cy: number, width: number, height: number, rotation: number): Bounds =>
    boundsOf(
        [[-width / 2, -height / 2], [width / 2, -height / 2], [width / 2, height / 2], [-width / 2, height / 2]]
            .map(([x, y]) => rotate(x, y, rotation))
            .map(([x, y]) => [cx + x, cy + y]),
    );

const measureElement = (element: SeatMapElement): Bounds => {
    if (element.type === 'zone') {
        return boundsOf(element.pts);
    }
    if (element.type === 'object') {
        return rotatedBox(element.x, element.y, element.w, element.h, element.rotation);
    }
    if (element.type === 'label') {
        const width = Math.max(LABEL_MIN_WIDTH, element.text.length * element.size * LABEL_CHARACTER_WIDTH);
        return rotatedBox(element.x, element.y, width, element.size * LABEL_LINE_HEIGHT, element.rotation);
    }

    const reach = element.seatSize / 2 + SEAT_MARGIN;
    const points = element.seats.flatMap(seat => [
        [seat.x - reach, seat.y - reach],
        [seat.x + reach, seat.y + reach],
    ] as [number, number][]);

    if (element.type === 'table') {
        const surface = (element.shape === 'round' ? element.d : Math.max(element.w, element.h)) / 2;
        points.push([element.x - surface, element.y - surface], [element.x + surface, element.y + surface]);
    }
    if (points.length === 0) {
        return {x: element.x - EMPTY_ELEMENT_SIZE / 2, y: element.y - EMPTY_ELEMENT_SIZE / 2, width: EMPTY_ELEMENT_SIZE, height: EMPTY_ELEMENT_SIZE};
    }
    return boundsOf(points);
};

const boundsCache = new WeakMap<SeatMapElement, Bounds>();

export const elementBounds = (element: SeatMapElement): Bounds => {
    const cached = boundsCache.get(element);
    if (cached) {
        return cached;
    }
    const bounds = measureElement(element);
    boundsCache.set(element, bounds);
    return bounds;
};

export const unionBounds = (bounds: Bounds[]): Bounds | null =>
    bounds.length === 0
        ? null
        : boundsOf(bounds.flatMap(box => [[box.x, box.y], [box.x + box.width, box.y + box.height]] as [number, number][]));

export const areaBounds = (area: SeatMapArea, padding = 40): Bounds => {
    const union = unionBounds(area.elements.map(elementBounds));
    if (!union) {
        return {x: 0, y: 0, width: 1000, height: 700};
    }
    return {x: union.x - padding, y: union.y - padding, width: union.width + padding * 2, height: union.height + padding * 2};
};

export const boundsBetween = (a: Point, b: Point): Bounds => ({
    x: Math.min(a.x, b.x),
    y: Math.min(a.y, b.y),
    width: Math.abs(b.x - a.x),
    height: Math.abs(b.y - a.y),
});

export const boundsCenter = (bounds: Bounds): Point => ({x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height / 2});

export const boundsContain = (bounds: Bounds, point: Point): boolean =>
    point.x >= bounds.x && point.x <= bounds.x + bounds.width && point.y >= bounds.y && point.y <= bounds.y + bounds.height;

const polygonContains = (points: [number, number][], point: Point): boolean => {
    let inside = false;
    for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
        const [xi, yi] = points[i];
        const [xj, yj] = points[j];
        if ((yi > point.y) !== (yj > point.y) && point.x < ((xj - xi) * (point.y - yi)) / (yj - yi) + xi) {
            inside = !inside;
        }
    }
    return inside;
};

export const elementAt = (area: SeatMapArea, point: Point): SeatMapElement | undefined =>
    [...area.elements].reverse().find(element =>
        element.type === 'zone' ? polygonContains(element.pts, point) : boundsContain(elementBounds(element), point),
    );

export const seatsWithin = (area: SeatMapArea, bounds: Bounds): string[] =>
    area.elements
        .filter(isSeatedElement)
        .flatMap(element => element.seats.filter(seat => boundsContain(bounds, seat)).map(seat => seat.uid));
