import React, {useMemo, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Popover, Text} from "@mantine/core";
import {
    IconChevronLeft,
    IconChevronRight,
    IconDotsVertical,
    IconPlus,
    IconX,
} from "@tabler/icons-react";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {EventOccurrence} from "../../../../../types.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {OccurrenceMenuItems, OccurrenceMenuActions} from "../OccurrenceMenu";
import classes from "./CalendarView.module.scss";

dayjs.extend(utc);
dayjs.extend(timezone);

interface CalendarViewProps {
    occurrences: EventOccurrence[];
    eventTimezone: string;
    menuActions?: OccurrenceMenuActions;
    onOccurrenceClick?: (occurrenceId: number) => void;
    onCreate?: (defaultDate: string) => void;
}

const MAX_DOTS = 3;

export const CalendarView = ({
    occurrences,
    eventTimezone,
    menuActions,
    onOccurrenceClick,
    onCreate,
}: CalendarViewProps) => {
    const [currentMonth, setCurrentMonth] = useState(() => dayjs().startOf('month'));
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const cellRefs = useRef<Record<string, HTMLDivElement | null>>({});

    const occurrencesByDate = useMemo(() => {
        const map: Record<string, EventOccurrence[]> = {};
        occurrences.forEach(occ => {
            const dateKey = dayjs.utc(occ.start_date).tz(eventTimezone).format('YYYY-MM-DD');
            if (!map[dateKey]) map[dateKey] = [];
            map[dateKey].push(occ);
        });
        return map;
    }, [occurrences, eventTimezone]);

    const calendarDays = useMemo(() => {
        const startOfMonth = currentMonth.startOf('month');
        const startDay = startOfMonth.day();
        const offset = startDay === 0 ? 6 : startDay - 1;
        const gridStart = startOfMonth.subtract(offset, 'day');

        const days: { date: dayjs.Dayjs; isCurrentMonth: boolean }[] = [];
        for (let i = 0; i < 42; i++) {
            const date = gridStart.add(i, 'day');
            days.push({
                date,
                isCurrentMonth: date.month() === currentMonth.month(),
            });
        }
        return days;
    }, [currentMonth]);

    const todayStr = dayjs().format('YYYY-MM-DD');

    const dayNames = useMemo(() => {
        const start = dayjs().startOf('week').add(1, 'day');
        return Array.from({length: 7}, (_, i) =>
            start.add(i, 'day').format('ddd')
        );
    }, []);

    const selectedOccurrences = selectedDate ? (occurrencesByDate[selectedDate] || []) : [];

    const handleCellClick = (dateStr: string) => {
        setSelectedDate(prev => prev === dateStr ? null : dateStr);
    };

    const closeAndDo = (fn: () => void) => {
        setSelectedDate(null);
        fn();
    };

    const popoverMenuActions: OccurrenceMenuActions | undefined = useMemo(() => {
        if (!menuActions) return undefined;
        return {
            ...menuActions,
            onEdit: (id: number) => closeAndDo(() => menuActions.onEdit(id)),
            onCancel: (id: number) => closeAndDo(() => menuActions.onCancel(id)),
            onDelete: (id: number) => closeAndDo(() => menuActions.onDelete(id)),
            onNavigate: (path: string) => closeAndDo(() => menuActions.onNavigate(path)),
            onDuplicate: menuActions.onDuplicate
                ? (occ: EventOccurrence) => closeAndDo(() => menuActions.onDuplicate!(occ))
                : undefined,
            onMessage: menuActions.onMessage
                ? (id: number) => closeAndDo(() => menuActions.onMessage!(id))
                : undefined,
            onCheckIn: menuActions.onCheckIn
                ? (id: number) => closeAndDo(() => menuActions.onCheckIn!(id))
                : undefined,
            onReactivate: menuActions.onReactivate
                ? (occ: EventOccurrence) => closeAndDo(() => menuActions.onReactivate!(occ))
                : undefined,
            onShare: menuActions.onShare
                ? (occ: EventOccurrence) => closeAndDo(() => menuActions.onShare!(occ))
                : undefined,
        };
    }, [menuActions]);

    const renderPopoverContent = () => {
        if (!selectedDate) return null;

        return (
            <div className={classes.popoverContent}>
                <div className={classes.popoverHeader}>
                    <span className={classes.popoverTitle}>
                        {dayjs(selectedDate).format('dddd, MMMM D')}
                    </span>
                    <ActionIcon variant="subtle" size="xs" onClick={() => setSelectedDate(null)}>
                        <IconX size={14}/>
                    </ActionIcon>
                </div>
                {selectedOccurrences.length === 0 ? (
                    <div className={classes.popoverEmpty}>
                        <Text size="sm" c="dimmed" mb="sm">{t`No occurrences on this date`}</Text>
                        {onCreate && (
                            <Button
                                size="compact-sm"
                                variant="light"
                                leftSection={<IconPlus size={14}/>}
                                onClick={() => closeAndDo(() => onCreate(selectedDate))}
                            >
                                {t`Add a date`}
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className={classes.popoverList}>
                        {selectedOccurrences.map(occ => {
                            const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', eventTimezone);
                            const endTime = occ.end_date
                                ? formatDateWithLocale(occ.end_date, 'timeOnly', eventTimezone)
                                : null;

                            return (
                                <div key={occ.id} className={classes.popoverRow}>
                                    <div
                                        className={classes.popoverRowInfo}
                                        onClick={() => closeAndDo(() => onOccurrenceClick?.(occ.id as number))}
                                        style={onOccurrenceClick ? {cursor: 'pointer'} : undefined}
                                    >
                                        <div className={classes.popoverStatusDot} data-status={occ.status}/>
                                        <div className={classes.popoverRowDetails}>
                                            <span className={classes.popoverTime}>
                                                {startTime}{endTime && <> – {endTime}</>}
                                            </span>
                                            {occ.label && (
                                                <span className={classes.popoverLabel}>{occ.label}</span>
                                            )}
                                            <span className={classes.popoverCapacity}>
                                                {occ.capacity != null ? (
                                                    <>{occ.used_capacity ?? 0} / {occ.capacity}</>
                                                ) : t`Unlimited`}
                                            </span>
                                        </div>
                                    </div>
                                    {popoverMenuActions && (
                                        <Menu shadow="md" width={200} position="bottom-end">
                                            <Menu.Target>
                                                <ActionIcon variant="subtle" size="sm">
                                                    <IconDotsVertical size={14}/>
                                                </ActionIcon>
                                            </Menu.Target>
                                            <Menu.Dropdown>
                                                <OccurrenceMenuItems occurrence={occ} actions={popoverMenuActions}/>
                                            </Menu.Dropdown>
                                        </Menu>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className={classes.calendarContainer}>
            <div className={classes.monthNav}>
                <ActionIcon variant="subtle" onClick={() => setCurrentMonth(m => m.subtract(1, 'month'))}>
                    <IconChevronLeft size={18}/>
                </ActionIcon>
                <span className={classes.monthLabel}>
                    {currentMonth.format('MMMM YYYY')}
                </span>
                <ActionIcon variant="subtle" onClick={() => setCurrentMonth(m => m.add(1, 'month'))}>
                    <IconChevronRight size={18}/>
                </ActionIcon>
            </div>

            <div className={classes.calendarGrid}>
                {dayNames.map(name => (
                    <div key={name} className={classes.dayHeader}>{name}</div>
                ))}

                {calendarDays.map(({date, isCurrentMonth}) => {
                    const dateStr = date.format('YYYY-MM-DD');
                    const dayOccs = occurrencesByDate[dateStr] || [];
                    const isSelected = selectedDate === dateStr;
                    const isToday = dateStr === todayStr;

                    const cellClasses = [
                        classes.dayCell,
                        !isCurrentMonth && classes.otherMonth,
                        isSelected && classes.selected,
                        isToday && classes.today,
                    ].filter(Boolean).join(' ');

                    return (
                        <Popover
                            key={dateStr}
                            opened={isSelected && (selectedOccurrences.length > 0 || dayOccs.length === 0)}
                            onClose={() => setSelectedDate(null)}
                            position="bottom"
                            withArrow
                            shadow="lg"
                            width={320}
                            trapFocus={false}
                            clickOutsideEvents={['mousedown']}
                        >
                            <Popover.Target>
                                <div
                                    ref={(el) => { cellRefs.current[dateStr] = el; }}
                                    className={cellClasses}
                                    onClick={() => handleCellClick(dateStr)}
                                >
                                    <div className={classes.dayNumber}>{date.date()}</div>
                                    {dayOccs.length > 0 && (
                                        <div className={classes.dayDots}>
                                            {dayOccs.slice(0, MAX_DOTS).map(occ => {
                                                const fillPct = occ.capacity
                                                    ? Math.min(100, Math.round(((occ.used_capacity ?? 0) / occ.capacity) * 100))
                                                    : 0;
                                                return (
                                                    <div
                                                        key={occ.id}
                                                        className={classes.occurrenceDot}
                                                        data-status={occ.status}
                                                        style={{'--fill-pct': fillPct} as React.CSSProperties}
                                                    >
                                                        {formatDateWithLocale(occ.start_date, 'timeOnly', eventTimezone)}
                                                        {occ.label && ` · ${occ.label}`}
                                                    </div>
                                                );
                                            })}
                                            {dayOccs.length > MAX_DOTS && (
                                                <span className={classes.moreCount}>
                                                    +{dayOccs.length - MAX_DOTS} {t`more`}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </Popover.Target>
                            <Popover.Dropdown p={0}>
                                {renderPopoverContent()}
                            </Popover.Dropdown>
                        </Popover>
                    );
                })}
            </div>
        </div>
    );
};
