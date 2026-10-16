import {useMemo} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Loader} from "@mantine/core";
import {IconChevronLeft, IconChevronRight, IconMapPin, IconPencil} from "@tabler/icons-react";
import dayjs from "dayjs";
import {Event} from "../../../../../types.ts";
import {EventLineup, EventSpace, ScheduleItem} from "../../../../../api/lineup.client.ts";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {createLazyModule} from "../../../../../utilites/lazyModule.ts";
import {prefetchOnIdle} from "../../../../../utilites/helpers.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {DATE_INPUT_FORMAT, scheduleDayKey} from "../../../../../utilites/lineupSchedule.ts";
import {ScheduleItemDraft} from "./ScheduleItemModal.tsx";
import {SPACE_COLORS} from "./scheduleConstants.ts";
import classes from "../Lineup.module.scss";

const calendarModule = createLazyModule(() => import("./ScheduleCalendar.tsx"));
prefetchOnIdle(calendarModule.load);

const shiftDay = (date: string, days: number) => dayjs(date).add(days, 'day').format(DATE_INPUT_FORMAT);

export const initialScheduleDate = (event: Event, lineup: EventLineup): string => {
    const firstItem = [...lineup.schedule_items].sort((a, b) => a.starts_at.localeCompare(b.starts_at))[0];
    const anchor = firstItem?.starts_at ?? event.next_occurrence_start_date ?? event.start_date;

    if (anchor) {
        return scheduleDayKey(anchor, event.timezone);
    }

    return dayjs().tz(event.timezone).format(DATE_INPUT_FORMAT);
};

interface TimetablePanelProps {
    event: Event;
    lineup: EventLineup;
    date: string;
    onDateChange: (date: string) => void;
    onCreate: (draft: Partial<ScheduleItemDraft>) => void;
    onEdit: (item: ScheduleItem) => void;
    onEditSpace: (space: EventSpace) => void;
}

export const TimetablePanel = ({event, lineup, date, onDateChange, onCreate, onEdit, onEditSpace}: TimetablePanelProps) => {
    const {isReadOnly} = useAccess();
    const calendar = calendarModule.useModule();

    const days = useMemo(() => {
        const counts = new Map<string, number>();
        lineup.schedule_items.forEach(item => {
            const key = scheduleDayKey(item.starts_at, event.timezone);
            counts.set(key, (counts.get(key) ?? 0) + 1);
        });
        if (!counts.has(date)) {
            counts.set(date, 0);
        }
        return [...counts.entries()].sort(([a], [b]) => a.localeCompare(b));
    }, [lineup.schedule_items, event.timezone, date]);

    return (
        <div className={classes.timetable}>
            {lineup.spaces.length > 0 && (
                <div className={classes.spaceChips}>
                    {lineup.spaces.map((space, index) => (
                        <button
                            key={space.id}
                            type="button"
                            className={classes.spaceChip}
                            disabled={isReadOnly}
                            onClick={() => onEditSpace(space)}
                            data-testid={`lineup-space-${space.id}`}
                        >
                            <span className={classes.spaceSwatch} style={{background: `var(--mantine-color-${SPACE_COLORS[index % SPACE_COLORS.length]}-5)`}}/>
                            {space.name}
                            {space.venue?.name && (
                                <span className={classes.spaceVenue}><IconMapPin size={12}/>{space.venue.name}</span>
                            )}
                            {!isReadOnly && <IconPencil size={12} className={classes.spaceEdit}/>}
                        </button>
                    ))}
                </div>
            )}

            <div className={classes.dayNav}>
                <ActionIcon
                    variant="default"
                    size="lg"
                    radius="xl"
                    aria-label={t`Previous day`}
                    onClick={() => onDateChange(shiftDay(date, -1))}
                >
                    <IconChevronLeft size={16}/>
                </ActionIcon>
                <div className={classes.dayChips} role="tablist" aria-label={t`Days`}>
                    {days.map(([key, count]) => {
                        const noonUtc = dayjs.tz(`${key}T12:00`, event.timezone).utc().format();
                        return (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={key === date}
                                className={classes.dayChip}
                                onClick={() => onDateChange(key)}
                            >
                                <span>{formatDateWithLocale(noonUtc, 'weekdayDate', event.timezone)}</span>
                                {count > 0 && <span className={classes.dayChipCount}>{count}</span>}
                            </button>
                        );
                    })}
                </div>
                <ActionIcon
                    variant="default"
                    size="lg"
                    radius="xl"
                    aria-label={t`Next day`}
                    onClick={() => onDateChange(shiftDay(date, 1))}
                >
                    <IconChevronRight size={16}/>
                </ActionIcon>
            </div>

            <div className={classes.calendarWrap}>
                {calendar
                    ? (
                        <calendar.ScheduleCalendar
                            event={event}
                            lineup={lineup}
                            date={date}
                            onDateChange={onDateChange}
                            readOnly={isReadOnly}
                            onCreate={onCreate}
                            onEdit={onEdit}
                        />
                    )
                    : <div className={classes.calendarLoading}><Loader size="sm"/></div>}
            </div>
        </div>
    );
};
