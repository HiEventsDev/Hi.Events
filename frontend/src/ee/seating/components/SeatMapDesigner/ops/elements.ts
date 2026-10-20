import {generateSeats, translateSeats} from "../../lib/generateSeats.ts";
import {boundsCenter, elementBounds, unionBounds} from "../../lib/geometry.ts";
import {isSeatedElement, SeatedElement, SeatMapElement, SeatMapLayout, SeatOverride} from "../../lib/types.ts";

export type AlignEdge = 'left' | 'centerX' | 'right' | 'top' | 'centerY' | 'bottom';

const DUPLICATE_OFFSET = 30;

const ID_LENGTH = 11;

export const newId = (prefix: string) => prefix + (
    Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2)
).slice(0, ID_LENGTH);

export const regenerate = <T extends SeatMapElement>(element: T): T =>
    isSeatedElement(element) ? {...element, seats: generateSeats(element)} : element;

const mapElements = (layout: SeatMapLayout, areaId: string, change: (elements: SeatMapElement[]) => SeatMapElement[]): SeatMapLayout => ({
    ...layout,
    areas: layout.areas.map(area => (area.id === areaId ? {...area, elements: change(area.elements)} : area)),
});

const translate = <T extends SeatMapElement>(element: T, dx: number, dy: number): T => {
    if (element.type === 'zone') {
        return {...element, pts: element.pts.map(([x, y]) => [x + dx, y + dy])};
    }
    const moved = {...element, x: element.x + dx, y: element.y + dy};
    return isSeatedElement(moved) ? {...moved, seats: translateSeats(moved.seats, dx, dy)} : moved;
};

export const addElement = (layout: SeatMapLayout, areaId: string, element: SeatMapElement): SeatMapLayout =>
    mapElements(layout, areaId, elements => [...elements, regenerate(element)]);

export const updateElements = (
    layout: SeatMapLayout,
    areaId: string,
    ids: string[],
    change: (element: SeatMapElement) => SeatMapElement,
): SeatMapLayout =>
    mapElements(layout, areaId, elements => {
        const targets = new Set(ids);
        return elements.map(element => (targets.has(element.id) ? regenerate(change(element)) : element));
    });

const translateElements = (layout: SeatMapLayout, areaId: string, ids: string[], shift: (element: SeatMapElement) => [number, number]): SeatMapLayout =>
    mapElements(layout, areaId, elements => {
        const targets = new Set(ids);
        return elements.map(element => (targets.has(element.id) ? translate(element, ...shift(element)) : element));
    });

export const patchElements = (layout: SeatMapLayout, areaId: string, ids: string[], patch: Record<string, unknown>): SeatMapLayout =>
    updateElements(layout, areaId, ids, element => ({...element, ...patch}) as SeatMapElement);

export const moveElements = (layout: SeatMapLayout, areaId: string, ids: string[], dx: number, dy: number): SeatMapLayout =>
    translateElements(layout, areaId, ids, () => [dx, dy]);

export const removeElements = (layout: SeatMapLayout, areaId: string, ids: string[]): SeatMapLayout =>
    mapElements(layout, areaId, elements => {
        const targets = new Set(ids);
        return elements.filter(element => !targets.has(element.id));
    });

export const duplicateElements = (layout: SeatMapLayout, areaId: string, ids: string[]): {layout: SeatMapLayout; ids: string[]} => {
    const copies = (layout.areas.find(area => area.id === areaId)?.elements ?? [])
        .filter(element => ids.includes(element.id))
        .map(element => regenerate({...translate(element, DUPLICATE_OFFSET, DUPLICATE_OFFSET), id: newId('e')}));

    return {
        layout: mapElements(layout, areaId, elements => [...elements, ...copies]),
        ids: copies.map(copy => copy.id),
    };
};

export const alignElements = (layout: SeatMapLayout, areaId: string, ids: string[], edge: AlignEdge): SeatMapLayout => {
    const targets = new Set(ids);
    const selected = (layout.areas.find(area => area.id === areaId)?.elements ?? []).filter(element => targets.has(element.id));
    const union = unionBounds(selected.map(elementBounds));
    if (!union) {
        return layout;
    }
    const target = boundsCenter(union);

    return translateElements(layout, areaId, ids, element => {
        const box = elementBounds(element);
        const center = boundsCenter(box);
        const shift: Record<AlignEdge, [number, number]> = {
            left: [union.x - box.x, 0],
            centerX: [target.x - center.x, 0],
            right: [union.x + union.width - (box.x + box.width), 0],
            top: [0, union.y - box.y],
            centerY: [0, target.y - center.y],
            bottom: [0, union.y + union.height - (box.y + box.height)],
        };
        return [Math.round(shift[edge][0]), Math.round(shift[edge][1])];
    });
};

const cleanOverride = (element: SeatedElement, override: SeatOverride): SeatOverride | null => {
    const clean: SeatOverride = {
        ...(override.band && override.band !== element.band ? {band: override.band} : {}),
        ...(override.acc ? {acc: true} : {}),
        ...(override.comp && !override.acc ? {comp: true} : {}),
        ...(override.note ? {note: override.note} : {}),
        ...(override.removed ? {removed: true} : {}),
    };
    return Object.keys(clean).length > 0 ? clean : null;
};

export const setSeatOverrides = (layout: SeatMapLayout, areaId: string, seatUids: string[], patch: SeatOverride): SeatMapLayout => {
    const elementIds = [...new Set(seatUids.map(uid => uid.split('.')[0]))];

    return updateElements(layout, areaId, elementIds, element => {
        if (!isSeatedElement(element)) {
            return element;
        }
        const overrides = {...element.overrides};
        seatUids
            .filter(uid => uid.startsWith(`${element.id}.`))
            .map(uid => uid.slice(element.id.length + 1))
            .forEach(key => {
                const merged = cleanOverride(element, {...overrides[key], ...patch});
                if (merged) {
                    overrides[key] = merged;
                } else {
                    delete overrides[key];
                }
            });
        return {...element, overrides};
    });
};

export const setRowLabel = (layout: SeatMapLayout, areaId: string, elementId: string, row: number, label: string): SeatMapLayout =>
    updateElements(layout, areaId, [elementId], element => {
        if (element.type !== 'row' && element.type !== 'block') {
            return element;
        }
        const others = Object.fromEntries(Object.entries(element.rowLabels ?? {}).filter(([key]) => key !== String(row)));
        const rowLabels = label ? {...others, [row]: label} : others;
        return {...element, rowLabels: Object.keys(rowLabels).length > 0 ? rowLabels : undefined};
    });

export const restoreRemovedSeats = (layout: SeatMapLayout, areaId: string, elementId: string): SeatMapLayout =>
    updateElements(layout, areaId, [elementId], element => {
        if (!isSeatedElement(element)) {
            return element;
        }
        const overrides = Object.fromEntries(
            Object.entries(element.overrides)
                .map(([key, override]) => [key, cleanOverride(element, {...override, removed: false})] as const)
                .filter((entry): entry is [string, SeatOverride] => entry[1] !== null),
        );
        return {...element, overrides};
    });
