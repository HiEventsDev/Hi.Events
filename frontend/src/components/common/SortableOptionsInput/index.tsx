import {useState} from "react";
import {ActionIcon, Group, Text, TextInput} from "@mantine/core";
import {IconGripVertical, IconPlus, IconX} from "@tabler/icons-react";
import {
    closestCenter,
    DndContext,
    PointerSensor,
    TouchSensor,
    UniqueIdentifier,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {arrayMove, SortableContext, useSortable, verticalListSortingStrategy} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {t} from "@lingui/macro";
import classes from "./SortableOptionsInput.module.scss";

interface OptionItem {
    id: number;
    value: string;
}

let _idCounter = 0;
const nextId = () => ++_idCounter;

const toItems = (values: string[]): OptionItem[] =>
    values.map(v => ({id: nextId(), value: v}));

interface SortableOptionRowProps {
    item: OptionItem;
    onRemove: () => void;
}

const SortableOptionRow = ({item, onRemove}: SortableOptionRowProps) => {
    const {attributes, listeners, setNodeRef, transform, transition, isDragging} =
        useSortable({id: item.id as UniqueIdentifier});

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`${classes.optionRow} ${isDragging ? classes.dragging : ''}`}
        >
            <span
                {...attributes}
                {...listeners}
                className={classes.dragHandle}
                aria-label={t`Drag to reorder`}
            >
                <IconGripVertical size={14}/>
            </span>
            <Text size="sm" className={classes.optionLabel}>{item.value}</Text>
            <ActionIcon
                size="xs"
                variant="subtle"
                color="red"
                onClick={onRemove}
                aria-label={t`Remove option`}
            >
                <IconX size={12}/>
            </ActionIcon>
        </div>
    );
};

interface SortableOptionsInputProps {
    label?: string;
    value: string[];
    onChange: (value: string[]) => void;
}

export const SortableOptionsInput = ({label, value, onChange}: SortableOptionsInputProps) => {
    const [items, setItems] = useState<OptionItem[]>(() => toItems(value));
    const [inputValue, setInputValue] = useState('');

    const sensors = useSensors(useSensor(PointerSensor), useSensor(TouchSensor));

    const handleDragEnd = (event: any) => {
        const {active, over} = event;
        if (over && active.id !== over.id) {
            const oldIndex = items.findIndex(i => i.id === active.id);
            const newIndex = items.findIndex(i => i.id === over.id);
            const reordered = arrayMove(items, oldIndex, newIndex);
            setItems(reordered);
            onChange(reordered.map(i => i.value));
        }
    };

    const handleAdd = () => {
        const trimmed = inputValue.trim();
        if (!trimmed) return;
        const newItem: OptionItem = {id: nextId(), value: trimmed};
        const updated = [...items, newItem];
        setItems(updated);
        onChange(updated.map(i => i.value));
        setInputValue('');
    };

    const handleRemove = (id: number) => {
        const updated = items.filter(i => i.id !== id);
        setItems(updated);
        onChange(updated.map(i => i.value));
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleAdd();
        }
    };

    return (
        <div>
            {label && (
                <Text component="label" size="sm" fw={500} mb={4} display="block">
                    {label}
                </Text>
            )}
            <Group gap="xs" mb={items.length > 0 ? 'xs' : 0} align="flex-end">
                <TextInput
                    placeholder={t`Type an option and press Enter`}
                    value={inputValue}
                    onChange={e => setInputValue(e.currentTarget.value)}
                    onKeyDown={handleKeyDown}
                    style={{flex: 1}}
                    size="sm"
                />
                <ActionIcon
                    onClick={handleAdd}
                    disabled={!inputValue.trim()}
                    variant="light"
                    size="lg"
                    aria-label={t`Add option`}
                >
                    <IconPlus size={16}/>
                </ActionIcon>
            </Group>

            {items.length > 0 && (
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={items.map(i => i.id) as UniqueIdentifier[]}
                        strategy={verticalListSortingStrategy}
                    >
                        <div className={classes.optionsList}>
                            {items.map(item => (
                                <SortableOptionRow
                                    key={item.id}
                                    item={item}
                                    onRemove={() => handleRemove(item.id)}
                                />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}
        </div>
    );
};
