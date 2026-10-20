import '@mantine/schedule/styles.css';
import {useMemo} from "react";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import {ResourcesDayView, ScheduleEventData, ScheduleResourceData} from "@mantine/schedule";
import {Event} from "../../../../../types.ts";
import {EventLineup, LineupEntry, ScheduleItem} from "../../../../../api/lineup.client.ts";
import {useUpsertScheduleItem} from "../../../../../mutations/useUpsertScheduleItem.ts";
import {initialsFor, profilePhoto, scheduleItemTitle, toEventLocal} from "../../../../../utilites/lineup.ts";
import {DATE_INPUT_FORMAT, DAY_BOUNDARY_HOUR, LOCAL_DATETIME_FORMAT} from "../../../../../utilites/lineupSchedule.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {LINEUP_ENTRY_DRAG_TYPE, SPACE_COLORS, UNASSIGNED_SPACE} from "./scheduleConstants.ts";
import {ScheduleItemDraft} from "./ScheduleItemModal.tsx";
import classes from "../Lineup.module.scss";

interface ScheduleCalendarProps {
    event: Event;
    lineup: EventLineup;
    date: string;
    onDateChange: (date: string) => void;
    readOnly: boolean;
    onCreate: (draft: Partial<ScheduleItemDraft>) => void;
    onEdit: (item: ScheduleItem) => void;
}

const DEFAULT_SLOT_MINUTES = 60;

const formatLocalTime = (local: string) => formatDateWithLocale(local, 'timeOnly', 'UTC');

const shiftLocal = (local: string, minutes: number) =>
    dayjs.utc(local.replace(' ', 'T')).add(minutes, 'minute').format(LOCAL_DATETIME_FORMAT);

const toCalendarTime = (local: string) => shiftLocal(local, -DAY_BOUNDARY_HOUR * 60);

const fromCalendarTime = (calendarTime: string) => shiftLocal(calendarTime, DAY_BOUNDARY_HOUR * 60);

const spaceIdFromResource = (resourceId?: string | number): number | null =>
    resourceId === undefined || resourceId === UNASSIGNED_SPACE ? null : Number(resourceId);

export const ScheduleCalendar = ({event, lineup, date, onDateChange, readOnly, onCreate, onEdit}: ScheduleCalendarProps) => {
    const upsertMutation = useUpsertScheduleItem();
    const tz = event.timezone;

    const entriesById = useMemo(
        () => new Map<number, LineupEntry>(lineup.entries.map(entry => [entry.id, entry])),
        [lineup.entries],
    );

    const resources: ScheduleResourceData[] = useMemo(() => {
        const spaceResources = lineup.spaces.map((space, index) => ({
            id: space.id,
            label: space.name,
            color: SPACE_COLORS[index % SPACE_COLORS.length],
        }));
        const hasUnassigned = lineup.schedule_items.some(item => item.event_space_id === null);

        if (spaceResources.length === 0) {
            return [{id: UNASSIGNED_SPACE, label: t`Schedule`}];
        }

        return hasUnassigned
            ? [...spaceResources, {id: UNASSIGNED_SPACE, label: t`No stage`, color: 'gray'}]
            : spaceResources;
    }, [lineup.spaces, lineup.schedule_items]);

    const colorBySpace = useMemo(
        () => new Map(resources.map(resource => [resource.id, resource.color ?? 'blue'])),
        [resources],
    );

    const events: ScheduleEventData[] = useMemo(() => lineup.schedule_items.map(item => {
        const resourceId = item.event_space_id ?? UNASSIGNED_SPACE;

        return {
            id: item.id,
            title: scheduleItemTitle(item, entriesById) || t`Untitled`,
            start: toCalendarTime(toEventLocal(item.starts_at, tz).format(LOCAL_DATETIME_FORMAT)),
            end: toCalendarTime(toEventLocal(item.ends_at, tz).format(LOCAL_DATETIME_FORMAT)),
            color: colorBySpace.get(resourceId) ?? 'blue',
            resourceId,
            payload: {item},
        };
    }), [lineup.schedule_items, entriesById, colorBySpace, tz]);

    const firstStartOnDate = events
        .map(scheduleEvent => String(scheduleEvent.start))
        .filter(start => start.startsWith(date))
        .sort()[0];

    const move = (itemId: number, start: string, end: string, resourceId?: string | number) => {
        const item = lineup.schedule_items.find(candidate => candidate.id === itemId);

        if (!item) {
            return;
        }

        const spaceId = resourceId === undefined ? item.event_space_id : spaceIdFromResource(resourceId);
        const toUtc = (local: string) => dayjs.tz(local, tz).utc().format(LOCAL_DATETIME_FORMAT);

        upsertMutation.mutate({
            eventId: event.id!,
            itemId: item.id,
            item: {
                title: item.title,
                description: item.description,
                starts_at: start,
                ends_at: end,
                event_space_id: spaceId,
                lineup_entry_ids: item.lineup_entry_ids,
            },
            optimisticItem: {...item, starts_at: toUtc(start), ends_at: toUtc(end), event_space_id: spaceId},
        }, {
            onError: () => showError(t`Could not move this slot. Please try again.`),
        });
    };

    const createFromDrop = (entryId: number, start: string, resourceId?: string | number) => {
        const end = shiftLocal(start, DEFAULT_SLOT_MINUTES);

        upsertMutation.mutate({
            eventId: event.id!,
            item: {
                title: null,
                description: null,
                starts_at: start,
                ends_at: end,
                event_space_id: spaceIdFromResource(resourceId),
                lineup_entry_ids: [entryId],
            },
        }, {
            onError: () => showError(t`Could not schedule this slot. Please try again.`),
        });
    };

    return (
        <div className={classes.calendar}>
            <ResourcesDayView
                date={date}
                onDateChange={value => onDateChange(String(value).slice(0, DATE_INPUT_FORMAT.length))}
                resources={resources}
                events={events}
                mode={readOnly ? 'static' : 'default'}
                labels={{resources: t`Stage`}}
                withHeader={false}
                slotLabelFormat={slot => formatLocalTime(fromCalendarTime(`${date} ${String(slot).slice(-8)}`))}
                intervalMinutes={30}
                slotWidth={72}
                rowHeight={72}
                startScrollTime={firstStartOnDate ? `${firstStartOnDate.slice(11, 13)}:00:00` : '09:00:00'}
                getCurrentTime={() => toCalendarTime(dayjs().tz(tz).format(LOCAL_DATETIME_FORMAT))}
                withEventsDragAndDrop={!readOnly}
                withEventResize={!readOnly}
                withDragSlotSelect={!readOnly}
                eventDragInterval={5}
                eventResizeInterval={5}
                preventEventOverlap
                onEventPlacementRejected={rejection => {
                    const conflict = rejection.conflicts[0]?.title;
                    showError(conflict
                        ? t`That overlaps "${conflict}" on the same stage`
                        : t`That slot can't go there`);
                }}
                renderEventBody={scheduleEvent => {
                    const item = (scheduleEvent.payload as { item: ScheduleItem }).item;
                    const performers = item.lineup_entry_ids
                        .map(id => entriesById.get(id))
                        .filter((entry): entry is LineupEntry => !!entry);
                    const photo = profilePhoto(performers[0]?.profile);

                    return (
                        <span className={classes.calendarEvent}>
                            {performers.length > 0 && (
                                <span className={classes.calendarEventAvatar} style={photo?.avg_colour ? {backgroundColor: photo.avg_colour} : undefined}>
                                    {photo ? <img src={photo.url} alt=""/> : initialsFor(performers[0].profile.name)}
                                </span>
                            )}
                            <span className={classes.calendarEventText}>
                                <span className={classes.calendarEventTitle}>{scheduleEvent.title}</span>
                                <span className={classes.calendarEventTime}>
                                    {formatLocalTime(fromCalendarTime(String(scheduleEvent.start)))}–{formatLocalTime(fromCalendarTime(String(scheduleEvent.end)))}
                                </span>
                            </span>
                        </span>
                    );
                }}
                onEventDrop={({eventId, newStart, newEnd, resourceId}) => move(Number(eventId), fromCalendarTime(newStart), fromCalendarTime(newEnd), resourceId)}
                onEventResize={({eventId, newStart, newEnd}) => move(Number(eventId), fromCalendarTime(newStart), fromCalendarTime(newEnd))}
                onEventClick={scheduleEvent => onEdit((scheduleEvent.payload as { item: ScheduleItem }).item)}
                onTimeSlotClick={({slotStart, slotEnd, resourceId}) => !readOnly && onCreate({
                    start: fromCalendarTime(slotStart),
                    end: shiftLocal(fromCalendarTime(slotEnd), 30),
                    spaceId: spaceIdFromResource(resourceId),
                })}
                onSlotDragEnd={({rangeStart, rangeEnd, resourceId}) => onCreate({
                    start: fromCalendarTime(rangeStart),
                    end: fromCalendarTime(rangeEnd),
                    spaceId: spaceIdFromResource(resourceId),
                })}
                canDropExternalEvent={({dataTransfer}) => dataTransfer.types.includes(LINEUP_ENTRY_DRAG_TYPE)}
                onExternalEventDrop={({dataTransfer, dropDateTime, resourceId}) => {
                    const entryId = Number(dataTransfer.getData(LINEUP_ENTRY_DRAG_TYPE));
                    if (entryId) {
                        createFromDrop(entryId, fromCalendarTime(dropDateTime), resourceId);
                    }
                }}
            />
        </div>
    );
};
