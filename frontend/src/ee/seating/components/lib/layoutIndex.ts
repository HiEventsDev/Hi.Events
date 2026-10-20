import {CompanionRuleSeat} from "./companionRule.ts";
import {
    GeneratedSeat,
    isSeatedElement,
    SeatedElement,
    SeatMapArea,
    SeatMapLayout,
    SeatMapBand,
    ZoneElement,
} from "./types.ts";

export interface IndexedSeat {
    seat: GeneratedSeat;
    element: SeatedElement;
    area: SeatMapArea;
}

export interface IndexedZone {
    zone: ZoneElement;
    area: SeatMapArea;
}

export interface LayoutIndex {
    seats: Map<string, IndexedSeat>;
    zones: Map<string, IndexedZone>;
    bands: Map<string, SeatMapBand>;
    segments: string[][];
}

const rowSegments = (element: SeatedElement): string[][] => {
    if (element.type === 'table') {
        return [];
    }

    const segments: string[][] = [];
    let current: string[] = [];
    let previousRow: number | null = null;

    for (const seat of element.seats) {
        if (previousRow !== null && previousRow !== seat.row && current.length > 0) {
            segments.push(current);
            current = [];
        }
        current.push(seat.uid);
        previousRow = seat.row;

        if (seat.gapAfter) {
            segments.push(current);
            current = [];
        }
    }

    if (current.length > 0) {
        segments.push(current);
    }

    return segments;
};

export const indexLayout = (layout: SeatMapLayout): LayoutIndex => {
    const index: LayoutIndex = {
        seats: new Map(),
        zones: new Map(),
        bands: new Map(layout.bands.map(band => [band.key, band])),
        segments: [],
    };

    for (const area of layout.areas) {
        for (const element of area.elements) {
            if (element.type === 'zone') {
                index.zones.set(element.id, {zone: element, area});
            }
            if (isSeatedElement(element)) {
                element.seats.forEach(seat => index.seats.set(seat.uid, {seat, element, area}));
                index.segments.push(...rowSegments(element));
            }
        }
    }

    return index;
};

export const seatLabel = (index: LayoutIndex, uid: string): string => {
    const seat = index.seats.get(uid);
    if (seat) {
        return `${seat.area.name} · ${seat.seat.label}`;
    }
    const zone = index.zones.get(uid);
    return zone ? `${zone.area.name} · ${zone.zone.label}` : uid;
};

export const companionRuleSeats = (index: LayoutIndex, uids: string[]): CompanionRuleSeat[] =>
    uids.map(uid => {
        const seat = index.seats.get(uid)?.seat;
        return {acc: seat?.acc ?? false, comp: seat?.comp ?? false};
    });

export const hasCompanionSeats = (index: LayoutIndex): boolean =>
    [...index.seats.values()].some(({seat}) => seat.comp);

export const bandOf = (index: LayoutIndex, uid: string): string | undefined =>
    index.seats.get(uid)?.seat.band ?? index.zones.get(uid)?.zone.band;
