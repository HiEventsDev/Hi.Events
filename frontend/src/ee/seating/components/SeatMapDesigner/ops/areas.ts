import {generateSeats} from "../../lib/generateSeats.ts";
import {isSeatedElement, SeatMapArea, SeatMapLayout} from "../../lib/types.ts";
import {newId} from "./elements.ts";

const uniqueAreaName = (layout: SeatMapLayout, base: string): string => {
    const taken = new Set(layout.areas.map(area => area.name.toLowerCase()));
    let name = base;
    for (let suffix = 2; taken.has(name.toLowerCase()); suffix++) {
        name = `${base} ${suffix}`;
    }
    return name;
};

export const addArea = (layout: SeatMapLayout, baseName: string): {layout: SeatMapLayout; areaId: string} => {
    const area: SeatMapArea = {
        id: newId('a'),
        name: uniqueAreaName(layout, baseName),
        level: Math.max(...layout.areas.map(existing => existing.level)) + 1,
        focal: null,
        elements: [],
    };
    return {layout: {...layout, areas: [...layout.areas, area]}, areaId: area.id};
};

export const duplicateArea = (layout: SeatMapLayout, areaId: string): {layout: SeatMapLayout; areaId: string} => {
    const source = layout.areas.find(area => area.id === areaId);
    if (!source) {
        return {layout, areaId};
    }
    const copy: SeatMapArea = {
        ...source,
        id: newId('a'),
        name: uniqueAreaName(layout, source.name),
        level: source.level + 1,
        elements: source.elements.map(element => {
            const renamed = {...element, id: newId('e')};
            return isSeatedElement(renamed) ? {...renamed, seats: generateSeats(renamed)} : renamed;
        }),
    };
    return {layout: {...layout, areas: [...layout.areas, copy]}, areaId: copy.id};
};

export const removeArea = (layout: SeatMapLayout, areaId: string): SeatMapLayout => ({
    ...layout,
    areas: layout.areas.filter(area => area.id !== areaId),
});

export const renameArea = (layout: SeatMapLayout, areaId: string, name: string): SeatMapLayout => ({
    ...layout,
    areas: layout.areas.map(area => (area.id === areaId ? {...area, name} : area)),
});

export const moveElementsToArea = (layout: SeatMapLayout, fromAreaId: string, toAreaId: string, ids: string[]): SeatMapLayout => {
    const moving = (layout.areas.find(area => area.id === fromAreaId)?.elements ?? []).filter(element => ids.includes(element.id));
    return {
        ...layout,
        areas: layout.areas.map(area => {
            if (area.id === fromAreaId) {
                return {...area, elements: area.elements.filter(element => !ids.includes(element.id))};
            }
            return area.id === toAreaId ? {...area, elements: [...area.elements, ...moving]} : area;
        }),
    };
};

export const withStageFocalPoints = (layout: SeatMapLayout): SeatMapLayout => ({
    ...layout,
    areas: layout.areas.map(area => {
        const stage = area.elements.find(element => element.type === 'object' && element.kind === 'stage');
        return stage && stage.type === 'object' ? {...area, focal: {x: stage.x, y: stage.y}} : area;
    }),
});
