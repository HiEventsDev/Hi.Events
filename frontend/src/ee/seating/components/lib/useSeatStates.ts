import {useMemo} from "react";
import {isSeatedElement, SeatMapArea} from "./types.ts";

export type SeatVisualState = 'free' | 'selected' | 'unavailable' | 'sold' | 'held' | 'blocked';

export interface SeatStateSources {
    selected?: Set<string>;
    sold?: Set<string>;
    held?: Set<string>;
    blocked?: Set<string>;
    unavailable?: Set<string>;
    sellableBands?: Set<string>;
}

export interface SeatStates {
    stateByUid: Map<string, SeatVisualState>;
    signatureByElement: Map<string, string>;
}

const STATE_CODE: Record<SeatVisualState, string> = {
    free: 'f',
    selected: 's',
    unavailable: 'u',
    sold: 'o',
    held: 'h',
    blocked: 'b',
};

const resolveState = (uid: string, band: string, sources: SeatStateSources): SeatVisualState => {
    if (sources.selected?.has(uid)) return 'selected';
    if (sources.sold?.has(uid)) return 'sold';
    if (sources.held?.has(uid)) return 'held';
    if (sources.blocked?.has(uid)) return 'blocked';
    if (sources.unavailable?.has(uid)) return 'unavailable';
    if (sources.sellableBands && !sources.sellableBands.has(band)) return 'unavailable';
    return 'free';
};

const seatStatesFor = (areas: SeatMapArea[], sources: SeatStateSources): SeatStates => {
    const stateByUid = new Map<string, SeatVisualState>();
    const signatureByElement = new Map<string, string>();

    areas.forEach(area => area.elements.filter(isSeatedElement).forEach(element => {
        let signature = '';
        element.seats.forEach(seat => {
            const state = resolveState(seat.uid, seat.band, sources);
            stateByUid.set(seat.uid, state);
            signature += STATE_CODE[state];
        });
        signatureByElement.set(element.id, signature);
    }));

    return {stateByUid, signatureByElement};
};

export const seatStateOf = (states: SeatStates | undefined, uid: string): SeatVisualState =>
    states?.stateByUid.get(uid) ?? 'free';

export const useSeatStates = (
    areas: SeatMapArea[],
    {selected, sold, held, blocked, unavailable, sellableBands}: SeatStateSources,
): SeatStates => useMemo(
    () => seatStatesFor(areas, {selected, sold, held, blocked, unavailable, sellableBands}),
    [areas, selected, sold, held, blocked, unavailable, sellableBands],
);
