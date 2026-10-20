import {useMemo} from "react";
import {t} from "@lingui/macro";
import {Avatar, Tooltip, VisuallyHidden} from "@mantine/core";
import {IconEyeOff, IconGripVertical} from "@tabler/icons-react";
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
import {Event} from "../../../../types.ts";
import {EventLineup, LineupEntry} from "../../../../api/lineup.client.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useSortableAccessibility} from "../../../../hooks/useSortableAccessibility.ts";
import {useSortLineupEntries} from "../../../../mutations/useSortLineupEntries.ts";
import {billingTierLabel, initialsFor, profilePhoto} from "../../../../utilites/lineup.ts";
import {showError} from "../../../../utilites/notifications.tsx";
import {LINEUP_ENTRY_DRAG_TYPE} from "./ScheduleBuilder/scheduleConstants.ts";
import {QuickAddPerson} from "./QuickAddPerson.tsx";
import classes from "./Lineup.module.scss";

interface PeopleRailProps {
    event: Event;
    lineup: EventLineup;
    title: string;
    selectedEntryId: number | null;
    onOpenEntry: (entryId: number) => void;
}

export const PeopleRail = ({event, lineup, title, selectedEntryId, onOpenEntry}: PeopleRailProps) => {
    const {isReadOnly} = useAccess();
    const sortMutation = useSortLineupEntries();
    const {id: dndId, accessibility} = useSortableAccessibility(
        lineup.entries.map(entry => entry.id),
        id => lineup.entries.find(entry => entry.id === id)?.profile.name ?? '',
    );
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(TouchSensor, {activationConstraint: {delay: 150, tolerance: 6}}),
        useSensor(KeyboardSensor, {coordinateGetter: sortableKeyboardCoordinates}),
    );

    const slotCounts = useMemo(() => {
        const counts = new Map<number, number>();
        lineup.schedule_items.forEach(item => item.lineup_entry_ids.forEach(id => counts.set(id, (counts.get(id) ?? 0) + 1)));
        return counts;
    }, [lineup.schedule_items]);

    const handleDragEnd = ({active, over}: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const ids = lineup.entries.map(entry => entry.id);
        const sortedIds = arrayMove(ids, ids.indexOf(Number(active.id)), ids.indexOf(Number(over.id)));

        sortMutation.mutate({eventId: event.id!, sortedIds}, {
            onError: () => showError(t`Could not save the new order. Please try again.`),
        });
    };

    return (
        <aside className={classes.rail}>
            <div className={classes.railHeader}>
                <span className={classes.railTitle}>{title}</span>
                <span className={classes.railCount}>{lineup.entries.length}</span>
            </div>

            <QuickAddPerson
                event={event}
                lineup={lineup}
                onAdded={(entry, isNewProfile) => isNewProfile && onOpenEntry(entry.id)}
            />

            {lineup.entries.length === 0
                ? <p className={classes.railEmpty}>{isReadOnly ? t`Nobody on the lineup yet.` : t`Nobody yet. Search your saved profiles or type a new name above.`}</p>
                : (
                    <DndContext id={dndId} accessibility={accessibility} sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                        <SortableContext items={lineup.entries.map(entry => entry.id)} strategy={verticalListSortingStrategy}>
                            <ul className={classes.railList} data-testid="lineup-people-list">
                                {lineup.entries.map(entry => (
                                    <RailRow
                                        key={entry.id}
                                        entry={entry}
                                        slotCount={slotCounts.get(entry.id) ?? 0}
                                        selected={entry.id === selectedEntryId}
                                        sortable={!isReadOnly}
                                        onOpen={() => onOpenEntry(entry.id)}
                                    />
                                ))}
                            </ul>
                        </SortableContext>
                    </DndContext>
                )}

            {lineup.entries.length > 1 && !isReadOnly && (
                <p className={classes.railHint}>{t`Drag the handle to reorder. Drag a name onto the timetable to schedule it.`}</p>
            )}
        </aside>
    );
};

interface RailRowProps {
    entry: LineupEntry;
    slotCount: number;
    selected: boolean;
    sortable: boolean;
    onOpen: () => void;
}

const RailRow = ({entry, slotCount, selected, sortable, onOpen}: RailRowProps) => {
    const {attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging} = useSortable({
        id: entry.id,
        disabled: !sortable,
    });
    const photo = profilePhoto(entry.profile);
    const label = entry.profile.name;

    return (
        <li
            ref={setNodeRef}
            className={classes.railRow}
            data-selected={selected || undefined}
            data-dragging={isDragging || undefined}
            data-hidden={entry.is_hidden || undefined}
            data-testid={`lineup-entry-${entry.id}`}
            style={{transform: CSS.Transform.toString(transform), transition}}
        >
            {sortable && (
                <button
                    type="button"
                    ref={setActivatorNodeRef}
                    className={classes.dragHandle}
                    aria-label={t`Drag to reorder ${label}`}
                    {...attributes}
                    {...listeners}
                >
                    <IconGripVertical size={14}/>
                </button>
            )}
            <button
                type="button"
                className={classes.railIdentity}
                onClick={onOpen}
                draggable={sortable}
                onDragStart={dragEvent => {
                    dragEvent.dataTransfer.setData(LINEUP_ENTRY_DRAG_TYPE, String(entry.id));
                    dragEvent.dataTransfer.effectAllowed = 'copy';
                }}
            >
                <Avatar
                    src={photo?.url}
                    alt=""
                    size={34}
                    radius="md"
                    color="primary"
                    style={photo?.avg_colour ? {backgroundColor: photo.avg_colour} : undefined}
                >
                    {initialsFor(entry.profile.name)}
                </Avatar>
                <span className={classes.entryText}>
                    <span className={classes.entryName}>{entry.profile.name}</span>
                    <span className={classes.railMeta}>
                        <span className={classes.tierDot} data-tier={entry.billing_tier.toLowerCase()}/>
                        {billingTierLabel(entry.billing_tier)}
                        <span className={classes.railMetaSep}>·</span>
                        <span data-unscheduled={slotCount === 0 || undefined} className={classes.railSlots}>
                            {slotCount === 0 ? t`Not scheduled` : slotCount === 1 ? t`1 slot` : t`${slotCount} slots`}
                        </span>
                    </span>
                </span>
                {entry.is_hidden && (
                    <>
                        <Tooltip label={t`Hidden from your event page`}>
                            <IconEyeOff size={14} className={classes.railHiddenIcon} aria-hidden/>
                        </Tooltip>
                        <VisuallyHidden>{t`Hidden from your event page`}</VisuallyHidden>
                    </>
                )}
            </button>
        </li>
    );
};
