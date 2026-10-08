import {isSeatedElement, SeatMapLayout, SeatMapBand} from "../../lib/types.ts";
import {newId} from "./elements.ts";

const BAND_COLORS = ['#40296c', '#755fb1', '#b9a9d8', '#0e7490', '#b45309', '#be123c', '#15803d', '#475569'];

export const addBand = (layout: SeatMapLayout, name: string): SeatMapLayout => ({
    ...layout,
    bands: [...layout.bands, {key: newId('b_'), name, color: BAND_COLORS[layout.bands.length % BAND_COLORS.length]}],
});

export const updateBand = (layout: SeatMapLayout, key: string, patch: Partial<Omit<SeatMapBand, 'key'>>): SeatMapLayout => ({
    ...layout,
    bands: layout.bands.map(band => (band.key === key ? {...band, ...patch} : band)),
});

export const removeBand = (layout: SeatMapLayout, key: string): SeatMapLayout => ({
    ...layout,
    bands: layout.bands.filter(band => band.key !== key),
});

export const bandUsage = (layout: SeatMapLayout): Record<string, number> => {
    const usage: Record<string, number> = {};
    const count = (band: string, amount: number) => {
        usage[band] = (usage[band] ?? 0) + amount;
    };
    layout.areas.forEach(area => area.elements.forEach(element => {
        if (element.type === 'zone') {
            count(element.band, element.capacity);
        }
        if (isSeatedElement(element)) {
            count(element.band, 0);
            element.seats.forEach(seat => count(seat.band, 1));
        }
    }));
    return usage;
};
