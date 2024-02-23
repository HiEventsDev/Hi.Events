import {useState} from 'react';
import {arrayMove} from '@dnd-kit/sortable';
import {IdParam} from "../types.ts";

type OnSortEndCallback = (newArray: IdParam[]) => void;

type UseDragItemsHandlerProps = {
    initialItemIds: IdParam[];
    onSortEnd: OnSortEndCallback;
};

export const useDragItemsHandler = ({initialItemIds, onSortEnd}: UseDragItemsHandlerProps) => {
    const [items, setItems] = useState(initialItemIds);

    const handleDragEnd = (event: any) => {
        const {active, over} = event;

        if (over && active.id !== over.id) {
            const oldIndex = items.indexOf(active.id);
            const newIndex = items.indexOf(over.id);
            const newArray = arrayMove(items, oldIndex, newIndex);

            setItems(newArray);
            onSortEnd(newArray);
        }
    };

    return {items, setItems, handleDragEnd};
};
