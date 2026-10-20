import {t} from "@lingui/macro";
import {isSeatedElement, SeatMapElement, SeatMapLayout} from "../lib/types.ts";

export interface LayoutProblem {
    message: string;
    areaId: string;
    elementIds: string[];
}

const requiredText = (element: SeatMapElement): string => {
    switch (element.type) {
        case 'row':
        case 'block':
            return element.name.trim() && element.startRow.trim();
        case 'table':
        case 'zone':
            return element.label.trim();
        case 'label':
            return element.text.trim();
        case 'object':
            return element.kind;
    }
};

export const findLayoutProblem = (layout: SeatMapLayout): LayoutProblem | null => {
    const firstAreaId = layout.areas[0].id;
    if (layout.bands.some(band => band.name.trim() === '')) {
        return {message: t`Every band needs a name`, areaId: firstAreaId, elementIds: []};
    }
    const areaNames = layout.areas.map(area => area.name.trim().toLowerCase());
    if (areaNames.some((name, index) => name === '' || areaNames.indexOf(name) !== index)) {
        return {message: t`Every area needs its own name`, areaId: firstAreaId, elementIds: []};
    }

    for (const area of layout.areas) {
        const unnamed = area.elements.find(element => requiredText(element) === '');
        if (unnamed) {
            return {message: t`An item in ${area.name} is missing its name`, areaId: area.id, elementIds: [unnamed.id]};
        }

        const owners = new Map<string, string>();
        for (const element of area.elements.filter(isSeatedElement)) {
            for (const seat of element.seats) {
                const owner = owners.get(seat.label);
                if (owner) {
                    return {
                        message: t`Two seats in ${area.name} are both called ${seat.label}. Give one of the highlighted sections a section name or a different first row.`,
                        areaId: area.id,
                        elementIds: [...new Set([owner, element.id])],
                    };
                }
                owners.set(seat.label, element.id);
            }
        }
    }
    return null;
};
