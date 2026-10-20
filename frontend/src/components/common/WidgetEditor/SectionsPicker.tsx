import {useMemo} from "react";
import {t} from "@lingui/macro";
import {Switch, Text} from "@mantine/core";
import {IconGripVertical} from "@tabler/icons-react";
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import {arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {WidgetSection} from "../../../utilites/widgetConfig.ts";
import {useSortableAccessibility} from "../../../hooks/useSortableAccessibility.ts";
import classes from "./WidgetEditor.module.scss";

const DISPLAY_ORDER: WidgetSection[] = ['header', 'tickets', 'about', 'lineup', 'schedule', 'location', 'organizer', 'custom'];

const sectionLabel = (section: WidgetSection): string => {
    switch (section) {
        case 'tickets':
            return t`Tickets`;
        case 'header':
            return t`Event title, date and image`;
        case 'about':
            return t`Description`;
        case 'location':
            return t`Location`;
        case 'organizer':
            return t`Organizer`;
        case 'lineup':
            return t`Lineup`;
        case 'schedule':
            return t`Schedule`;
        case 'custom':
            return t`Custom homepage sections`;
    }
};

interface SectionRowProps {
    section: WidgetSection;
    enabled: boolean;
    onToggle: (enabled: boolean) => void;
}

const SectionRow = ({section, enabled, onToggle}: SectionRowProps) => {
    const {attributes, listeners, setNodeRef, transform, transition, isDragging} = useSortable({id: section});
    const label = sectionLabel(section);

    return (
        <li
            ref={setNodeRef}
            className={classes.sectionRow}
            data-dragging={isDragging ? '' : undefined}
            data-enabled={enabled ? '' : undefined}
            style={{transform: CSS.Transform.toString(transform), transition}}
        >
            <button type="button" className={classes.dragHandle} aria-label={t`Reorder ${label}`} {...attributes} {...listeners}>
                <IconGripVertical size={16}/>
            </button>
            <Text size="sm" className={classes.sectionRowLabel}>{label}</Text>
            {section === 'tickets'
                ? <Text size="xs" c="dimmed">{t`Always shown`}</Text>
                : (
                    <Switch
                        size="sm"
                        checked={enabled}
                        onChange={(event) => onToggle(event.currentTarget.checked)}
                        aria-label={label}
                        data-testid={`widget-section-${section}-switch`}
                    />
                )}
        </li>
    );
};

interface SectionsPickerProps {
    value: WidgetSection[] | undefined;
    onChange: (sections: WidgetSection[]) => void;
}

export const SectionsPicker = ({value, onChange}: SectionsPickerProps) => {
    const enabled = useMemo(() => {
        const selected = value?.length ? value : ['tickets' as const];
        return selected.includes('tickets') ? selected : [...selected, 'tickets' as const];
    }, [value]);
    const order = useMemo(() => {
        const result: WidgetSection[] = [...enabled];
        DISPLAY_ORDER.forEach((section, index) => {
            if (result.includes(section)) {
                return;
            }
            const previous = DISPLAY_ORDER.slice(0, index).reverse().find(candidate => result.includes(candidate));
            result.splice(previous ? result.indexOf(previous) + 1 : 0, 0, section);
        });
        return result;
    }, [enabled]);
    const {id: dndId, accessibility} = useSortableAccessibility(order, id => sectionLabel(id as WidgetSection));

    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(TouchSensor, {activationConstraint: {delay: 150, tolerance: 6}}),
        useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
    );

    const emit = (nextOrder: WidgetSection[], nextEnabled: Set<WidgetSection>) => {
        onChange(nextOrder.filter(section => nextEnabled.has(section)));
    };

    const onDragEnd = ({active, over}: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }
        const from = order.indexOf(active.id as WidgetSection);
        const to = order.indexOf(over.id as WidgetSection);
        emit(arrayMove(order, from, to), new Set(enabled));
    };

    const toggle = (section: WidgetSection, isEnabled: boolean) => {
        const next = new Set(enabled);
        if (isEnabled) {
            next.add(section);
        } else {
            next.delete(section);
        }
        emit(order, next);
    };

    return (
        <DndContext id={dndId} accessibility={accessibility} sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
            <SortableContext items={order} strategy={verticalListSortingStrategy}>
                <ul className={classes.sectionList}>
                    {order.map(section => (
                        <SectionRow
                            key={section}
                            section={section}
                            enabled={enabled.includes(section)}
                            onToggle={(isEnabled) => toggle(section, isEnabled)}
                        />
                    ))}
                </ul>
            </SortableContext>
        </DndContext>
    );
};
