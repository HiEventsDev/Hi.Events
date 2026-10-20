import {useId, useRef} from "react";
import {t} from "@lingui/macro";
import {Announcements, ScreenReaderInstructions, UniqueIdentifier} from "@dnd-kit/core";

export const useSortableAccessibility = (ids: UniqueIdentifier[], getLabel: (id: UniqueIdentifier) => string) => {
    const id = useId();
    const hasMoved = useRef(false);
    const total = ids.length;
    const position = (itemId: UniqueIdentifier) => ids.indexOf(itemId) + 1;

    const announcements: Announcements = {
        onDragStart: ({active}) => {
            hasMoved.current = false;
            const label = getLabel(active.id);
            const current = position(active.id);
            return t`Picked up ${label}, position ${current} of ${total}.`;
        },
        onDragOver: ({active, over}) => {
            if (!over || (!hasMoved.current && over.id === active.id)) {
                return undefined;
            }
            hasMoved.current = true;
            const label = getLabel(active.id);
            const target = position(over.id);
            return t`${label} moved to position ${target} of ${total}.`;
        },
        onDragEnd: ({active, over}) => {
            const label = getLabel(active.id);
            if (!over) {
                return t`${label} dropped.`;
            }
            const target = position(over.id);
            return t`${label} dropped at position ${target} of ${total}.`;
        },
        onDragCancel: ({active}) => {
            const label = getLabel(active.id);
            return t`Move cancelled. ${label} is back in its original position.`;
        },
    };

    const screenReaderInstructions: ScreenReaderInstructions = {
        draggable: t`To move this item, press space or enter. Use the arrow keys to change its position, then press space or enter again to drop it, or escape to cancel.`,
    };

    return {id, accessibility: {announcements, screenReaderInstructions}};
};
