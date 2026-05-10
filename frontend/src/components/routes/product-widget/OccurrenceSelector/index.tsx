import {t} from "@lingui/macro";
import {useState, useMemo, useEffect, useRef} from "react";
import {Button, UnstyledButton} from "@mantine/core";
import {IconCalendar, IconCheck, IconChevronLeft, IconChevronRight, IconClock, IconList} from "@tabler/icons-react";
import dayjs from "dayjs";
import isToday from "dayjs/plugin/isToday";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence, EventOccurrenceStatus, EventType, IdParam, RecurrenceRule} from "../../../../types.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import './OccurrenceSelector.scss';

const buildRecurrenceSummary = (rule?: RecurrenceRule): string | null => {
    if (!rule) return null;

    const dayLabels: Record<string, string> = {
        monday: t`Monday`, tuesday: t`Tuesday`, wednesday: t`Wednesday`,
        thursday: t`Thursday`, friday: t`Friday`, saturday: t`Saturday`, sunday: t`Sunday`,
    };

    switch (rule.frequency) {
        case 'daily':
            return rule.interval > 1 ? t`Every ${rule.interval} days` : t`Daily`;
        case 'weekly': {
            const days = (rule.days_of_week || []).map(d => dayLabels[d] || d);
            if (days.length === 0) return rule.interval > 1 ? t`Every ${rule.interval} weeks` : t`Weekly`;
            return days.length === 1
                ? (rule.interval > 1 ? t`Every ${rule.interval} weeks on ${days[0]}` : t`Every ${days[0]}`)
                : (rule.interval > 1 ? t`Every ${rule.interval} weeks on ${days.join(', ')}` : t`Every ${days.join(', ')}`);
        }
        case 'monthly':
            return rule.interval > 1 ? t`Every ${rule.interval} months` : t`Monthly`;
        case 'yearly':
            return rule.interval > 1 ? t`Every ${rule.interval} years` : t`Yearly`;
        default:
            return null;
    }
};

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(isToday);

interface OccurrenceSelectorProps {
    event: Event;
    selectedOccurrenceId?: IdParam;
    onSelect: (occurrenceId: IdParam) => void;
    colors?: {
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
        background?: string;
    };
}

type ViewMode = 'calendar' | 'list';

const OCCURRENCES_PER_PAGE = 10;

const getActiveOccurrences = (occurrences: EventOccurrence[]): EventOccurrence[] => {
    return occurrences.filter(
        occ => (occ.status === EventOccurrenceStatus.ACTIVE || occ.status === EventOccurrenceStatus.SOLD_OUT) && !occ.is_past
    );
};

interface DateGroup {
    dateKey: string;
    label: string;
    occurrences: EventOccurrence[];
}

const groupOccurrencesByDate = (occurrences: EventOccurrence[], tz: string): DateGroup[] => {
    const map = new Map<string, EventOccurrence[]>();
    occurrences.forEach(occ => {
        const dateKey = dayjs.utc(occ.start_date).tz(tz).format('YYYY-MM-DD');
        if (!map.has(dateKey)) map.set(dateKey, []);
        map.get(dateKey)!.push(occ);
    });

    const today = dayjs().tz(tz).startOf('day');
    const tomorrow = today.add(1, 'day');

    return Array.from(map.entries()).map(([dateKey, occs]) => {
        const date = dayjs(dateKey);
        let label: string;
        if (date.isSame(today, 'day')) {
            label = t`Today` + ' — ' + formatDateWithLocale(occs[0].start_date, 'dayName', tz);
        } else if (date.isSame(tomorrow, 'day')) {
            label = t`Tomorrow` + ' — ' + formatDateWithLocale(occs[0].start_date, 'dayName', tz);
        } else {
            label = formatDateWithLocale(occs[0].start_date, 'dayName', tz);
        }
        return {dateKey, label, occurrences: occs};
    });
};

const CalendarView = ({
    occurrences,
    event,
    selectedOccurrenceId,
    onSelect,
}: {
    occurrences: EventOccurrence[];
    event: Event;
    selectedOccurrenceId?: IdParam;
    onSelect: (occurrenceId: IdParam) => void;
}) => {
    const tz = event.timezone;
    const activeOccurrences = getActiveOccurrences(occurrences);

    const selectedOccurrence = selectedOccurrenceId
        ? occurrences.find(o => o.id === selectedOccurrenceId)
        : null;

    const initialMonth = selectedOccurrence
        ? dayjs.utc(selectedOccurrence.start_date).tz(tz).startOf('month')
        : activeOccurrences.length > 0
            ? dayjs.utc(activeOccurrences[0].start_date).tz(tz).startOf('month')
            : dayjs().tz(tz).startOf('month');

    const [currentMonth, setCurrentMonth] = useState(initialMonth);
    const [selectedDate, setSelectedDate] = useState<string | null>(() => {
        if (selectedOccurrence) {
            return dayjs.utc(selectedOccurrence.start_date).tz(tz).format('YYYY-MM-DD');
        }
        return null;
    });

    useEffect(() => {
        if (selectedOccurrence) {
            const dateKey = dayjs.utc(selectedOccurrence.start_date).tz(tz).format('YYYY-MM-DD');
            setSelectedDate(dateKey);
            setCurrentMonth(dayjs.utc(selectedOccurrence.start_date).tz(tz).startOf('month'));
        }
    }, [selectedOccurrenceId]);

    // Close the time-slots overlay on ESC. We don't tie this to outside-click
    // because clicking another calendar day should switch the visible date,
    // not first-close-then-reopen the overlay (which would feel laggy).
    useEffect(() => {
        if (!selectedDate) return;

        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setSelectedDate(null);
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [selectedDate]);

    // When a user scrolls past the calendar and taps a date at the bottom of
    // the page, the overlay mounts in place — covering a calendar grid that's
    // already half-off-screen — so the back button and first time slots end up
    // below the fold. Scroll the overlay into view if any of it is clipped.
    const overlayRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (!selectedDate || typeof window === 'undefined') return;
        const node = overlayRef.current;
        if (!node) return;

        const rect = node.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const fullyVisible = rect.top >= 0 && rect.bottom <= viewportHeight;
        if (fullyVisible) return;

        // Defer to the next frame so the entrance animation starts in lockstep
        // with the scroll rather than juddering against it.
        const handle = window.requestAnimationFrame(() => {
            node.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
        return () => window.cancelAnimationFrame(handle);
    }, [selectedDate]);

    const occurrencesByDate = useMemo(() => {
        const map: Record<string, EventOccurrence[]> = {};
        for (const occ of occurrences) {
            const dateKey = dayjs.utc(occ.start_date).tz(tz).format('YYYY-MM-DD');
            if (!map[dateKey]) map[dateKey] = [];
            map[dateKey].push(occ);
        }
        return map;
    }, [occurrences, tz]);

    const todayMonth = dayjs().tz(tz).startOf('month');
    const todayKey = dayjs().tz(tz).format('YYYY-MM-DD');
    const isAwayFromToday = !currentMonth.isSame(todayMonth, 'month');

    const daysInMonth = currentMonth.daysInMonth();
    const firstDayOfWeek = currentMonth.day();
    const mondayOffset = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1;
    const weeks: (number | null)[][] = [];
    let currentWeek: (number | null)[] = new Array(mondayOffset).fill(null);

    for (let day = 1; day <= daysInMonth; day++) {
        currentWeek.push(day);
        if (currentWeek.length === 7) {
            weeks.push(currentWeek);
            currentWeek = [];
        }
    }
    if (currentWeek.length > 0) {
        while (currentWeek.length < 7) currentWeek.push(null);
        weeks.push(currentWeek);
    }

    const dayNames = [t`Mo`, t`Tu`, t`We`, t`Th`, t`Fr`, t`Sa`, t`Su`];

    const slotsForSelectedDate = selectedDate ? (occurrencesByDate[selectedDate] || []) : [];
    const timezoneAbbr = activeOccurrences.length > 0
        ? formatDateWithLocale(activeOccurrences[0].start_date, 'timezone', tz)
        : '';

    const overlayOpen = !!(selectedDate && slotsForSelectedDate.length > 0);

    return (
        <div className="hi-calendar-view">
            <div
                className={`hi-calendar-stage${overlayOpen ? ' hi-calendar-stage-inert' : ''}`}
                aria-hidden={overlayOpen}
                // Keeps the calendar laid out so its rendered height (and the
                // surrounding widget height) doesn't jump while the overlay
                // mounts on top. The CSS class disables pointer events and the
                // aria-hidden hides it from screen readers.
            >
                <div className="hi-calendar-header">
                    <UnstyledButton
                        className="hi-calendar-nav"
                        onClick={() => setCurrentMonth(m => m.subtract(1, 'month'))}
                    >
                        <IconChevronLeft size={18}/>
                    </UnstyledButton>
                    <span className="hi-calendar-month-label">
                        {currentMonth.format('MMMM YYYY')}
                    </span>
                    {isAwayFromToday && (
                        <UnstyledButton
                            className="hi-calendar-nav"
                            onClick={() => setCurrentMonth(todayMonth)}
                            style={{fontSize: '0.8rem', fontWeight: 500}}
                        >
                            {t`Today`}
                        </UnstyledButton>
                    )}
                    <UnstyledButton
                        className="hi-calendar-nav"
                        onClick={() => setCurrentMonth(m => m.add(1, 'month'))}
                    >
                        <IconChevronRight size={18}/>
                    </UnstyledButton>
                </div>

                <div className="hi-calendar-grid">
                    {dayNames.map((name, i) => (
                        <div key={i} className="hi-calendar-day-name">{name}</div>
                    ))}
                    {weeks.flat().map((day, i) => {
                        if (day === null) {
                            return <div key={`empty-${i}`} className="hi-calendar-cell hi-calendar-cell-empty"/>;
                        }
                        const dateKey = currentMonth.date(day).format('YYYY-MM-DD');
                        const dayOccurrences = occurrencesByDate[dateKey] || [];
                        const selectableCount = dayOccurrences.filter(
                            o => (o.status === EventOccurrenceStatus.ACTIVE || o.status === EventOccurrenceStatus.SOLD_OUT) && !o.is_past
                        ).length;
                        const hasSelectable = selectableCount > 0;
                        const isSelected = selectedDate === dateKey;
                        const isPast = currentMonth.date(day).isBefore(dayjs().tz(tz), 'day');
                        const isTodayCell = dateKey === todayKey;

                        return (
                            <UnstyledButton
                                key={dateKey}
                                className={[
                                    'hi-calendar-cell',
                                    hasSelectable ? 'hi-calendar-cell-has-events' : '',
                                    isSelected ? 'hi-calendar-cell-selected' : '',
                                    isPast ? 'hi-calendar-cell-past' : '',
                                    isTodayCell ? 'hi-calendar-cell-today' : '',
                                ].filter(Boolean).join(' ')}
                                disabled={!hasSelectable}
                                onClick={() => {
                                    if (hasSelectable) {
                                        setSelectedDate(dateKey);
                                        const selectableSlots = dayOccurrences.filter(
                                            o => (o.status === EventOccurrenceStatus.ACTIVE || o.status === EventOccurrenceStatus.SOLD_OUT) && !o.is_past
                                        );
                                        if (selectableSlots.length === 1 && selectableSlots[0].id) {
                                            onSelect(selectableSlots[0].id);
                                        }
                                    }
                                }}
                            >
                                <span className="hi-calendar-day-number">{day}</span>
                                {hasSelectable && selectableCount === 1 && (
                                    <span className="hi-calendar-dot" />
                                )}
                                {hasSelectable && selectableCount > 1 && (
                                    <span className="hi-calendar-count">{selectableCount}</span>
                                )}
                            </UnstyledButton>
                        );
                    })}
                </div>

                {!selectedDate && Object.keys(occurrencesByDate).every(
                    dateKey => !dateKey.startsWith(currentMonth.format('YYYY-MM'))
                ) && (
                    <div className="hi-calendar-no-dates">
                        {t`No dates available this month. Try navigating to another month.`}
                    </div>
                )}
            </div>

            {overlayOpen && (
                <div
                    ref={overlayRef}
                    className="hi-time-slots-overlay"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t`Select a time`}
                >
                    <div className="hi-time-slots-header">
                        <UnstyledButton
                            className="hi-time-slots-back"
                            onClick={() => setSelectedDate(null)}
                            aria-label={t`Back to calendar`}
                        >
                            <IconChevronLeft size={16}/>
                            <span>{t`Back`}</span>
                        </UnstyledButton>
                        <div className="hi-time-slots-label">
                            <span className="hi-time-slots-date">
                                {formatDateWithLocale(slotsForSelectedDate[0].start_date, 'dayName', tz)}
                            </span>
                            {timezoneAbbr && (
                                <span className="hi-time-slots-tz">{timezoneAbbr}</span>
                            )}
                        </div>
                    </div>
                    <div className="hi-time-slots-list">
                        {slotsForSelectedDate.map(occ => {
                            const isSelectable = (occ.status === EventOccurrenceStatus.ACTIVE || occ.status === EventOccurrenceStatus.SOLD_OUT) && !occ.is_past;
                            const isOccSelected = selectedOccurrenceId === occ.id;
                            const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', tz);
                            const endTime = occ.end_date ? formatDateWithLocale(occ.end_date, 'timeOnly', tz) : null;

                            return (
                                <UnstyledButton
                                    key={occ.id}
                                    className={[
                                        'hi-time-slot',
                                        isOccSelected ? 'hi-time-slot-selected' : '',
                                        !isSelectable ? 'hi-time-slot-disabled' : '',
                                    ].filter(Boolean).join(' ')}
                                    disabled={!isSelectable}
                                    onClick={() => occ.id && onSelect(occ.id)}
                                >
                                    <div className="hi-time-slot-time">
                                        {isOccSelected
                                            ? <IconCheck size={14}/>
                                            : <IconClock size={14}/>
                                        }
                                        {startTime}{endTime ? ` - ${endTime}` : ''}
                                        {occ.label && <span className="hi-time-slot-suffix"> · {occ.label}</span>}
                                    </div>
                                    <div className="hi-time-slot-meta">
                                        {occ.status === EventOccurrenceStatus.CANCELLED && (
                                            <span className="hi-time-slot-cancelled">{t`Cancelled`}</span>
                                        )}
                                        {occ.status === EventOccurrenceStatus.SOLD_OUT && (
                                            <span className="hi-time-slot-sold-out">{t`Sold Out`}</span>
                                        )}
                                        {isSelectable && occ.status === EventOccurrenceStatus.ACTIVE && occ.available_capacity !== null && occ.available_capacity !== undefined && occ.available_capacity > 0 && (
                                            <span className="hi-time-slot-spots">
                                                {t`${occ.available_capacity} spots left`}
                                            </span>
                                        )}
                                    </div>
                                </UnstyledButton>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
};

const ListView = ({
    occurrences,
    event,
    selectedOccurrenceId,
    onSelect,
}: {
    occurrences: EventOccurrence[];
    event: Event;
    selectedOccurrenceId?: IdParam;
    onSelect: (occurrenceId: IdParam) => void;
}) => {
    const tz = event.timezone;
    const [visibleCount, setVisibleCount] = useState(OCCURRENCES_PER_PAGE);
    const activeOccurrences = getActiveOccurrences(occurrences);
    const displayedOccurrences = activeOccurrences.slice(0, visibleCount);
    const hasMore = activeOccurrences.length > visibleCount;

    const timezoneAbbr = activeOccurrences.length > 0
        ? formatDateWithLocale(activeOccurrences[0].start_date, 'timezone', tz)
        : '';

    const groups = useMemo(
        () => groupOccurrencesByDate(displayedOccurrences, tz),
        [displayedOccurrences, tz]
    );

    return (
        <div className="hi-list-view">
            {timezoneAbbr && (
                <div className="hi-list-timezone">{timezoneAbbr}</div>
            )}
            {groups.map(group => (
                <div key={group.dateKey} className="hi-list-date-group">
                    <div className="hi-list-date-header">
                        <span className="hi-list-date-label">{group.label}</span>
                        {group.occurrences.length > 1 && (
                            <span className="hi-list-date-count">
                                {group.occurrences.length}
                            </span>
                        )}
                    </div>
                    {group.occurrences.map(occ => {
                        const isSoldOut = occ.status === EventOccurrenceStatus.SOLD_OUT;
                        const isSelected = selectedOccurrenceId === occ.id;
                        const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', tz);
                        const endTime = occ.end_date ? formatDateWithLocale(occ.end_date, 'timeOnly', tz) : null;

                        return (
                            <UnstyledButton
                                key={occ.id}
                                className={[
                                    'hi-occurrence-item',
                                    isSelected ? 'hi-occurrence-item-selected' : '',
                                    isSoldOut ? 'hi-occurrence-item-sold-out' : '',
                                ].filter(Boolean).join(' ')}
                                onClick={() => {
                                    if (occ.id) {
                                        onSelect(occ.id);
                                    }
                                }}
                            >
                                <div className="hi-occurrence-date-badge">
                                    <span className="hi-occurrence-month">
                                        {formatDateWithLocale(occ.start_date, 'monthShort', tz)}
                                    </span>
                                    <span className="hi-occurrence-day">
                                        {formatDateWithLocale(occ.start_date, 'dayOfMonth', tz)}
                                    </span>
                                </div>
                                <div className="hi-occurrence-details">
                                    <div className="hi-occurrence-time">
                                        <IconClock size={13}/>
                                        {startTime}{endTime ? ` - ${endTime}` : ''}
                                        {occ.label && <span className="hi-occurrence-label"> · {occ.label}</span>}
                                    </div>
                                </div>
                                <div className="hi-occurrence-status">
                                    {isSoldOut && (
                                        <span className="hi-occurrence-sold-out-badge">{t`Sold Out`}</span>
                                    )}
                                    {!isSoldOut && occ.available_capacity !== null && occ.available_capacity !== undefined && occ.available_capacity > 0 && (
                                        <span className="hi-occurrence-spots">
                                            {t`${occ.available_capacity} left`}
                                        </span>
                                    )}
                                    {isSelected && (
                                        <IconCheck size={16} className="hi-occurrence-check"/>
                                    )}
                                </div>
                            </UnstyledButton>
                        );
                    })}
                </div>
            ))}

            {hasMore && (
                <Button
                    variant="subtle"
                    fullWidth
                    className="hi-occurrence-load-more"
                    onClick={() => setVisibleCount(c => c + OCCURRENCES_PER_PAGE)}
                >
                    {t`Show more dates`}
                </Button>
            )}
        </div>
    );
};

export const OccurrenceSelector = ({
    event,
    selectedOccurrenceId,
    onSelect,
    colors,
}: OccurrenceSelectorProps) => {
    const occurrences = event.occurrences || [];
    const activeOccurrences = getActiveOccurrences(occurrences);

    const shouldDefaultToCalendar = activeOccurrences.length > 14;
    const [viewMode, setViewMode] = useState<ViewMode>(shouldDefaultToCalendar ? 'calendar' : 'list');

    const recurrenceSummary = buildRecurrenceSummary(event.recurrence_rule);

    if (event.type !== EventType.RECURRING || activeOccurrences.length === 0) {
        return null;
    }

    return (
        <div
            className="hi-occurrence-selector"
            style={{
                '--widget-primary-color': colors?.primary,
                '--widget-primary-text-color': colors?.primaryText,
                '--widget-secondary-color': colors?.secondary,
                '--widget-secondary-text-color': colors?.secondaryText,
                '--widget-background-color': colors?.background,
            } as React.CSSProperties}
        >
            <div className="hi-occurrence-selector-header">
                <h2 className="hi-occurrence-selector-title">
                    {t`Select a Date & Time`}
                </h2>
                {recurrenceSummary && (
                    <span className="hi-occurrence-selector-summary">{recurrenceSummary}</span>
                )}
                <div className="hi-occurrence-view-toggle">
                    <UnstyledButton
                        className={`hi-view-toggle-btn ${viewMode === 'list' ? 'active' : ''}`}
                        onClick={() => setViewMode('list')}
                        title={t`List view`}
                    >
                        <IconList size={16}/>
                    </UnstyledButton>
                    <UnstyledButton
                        className={`hi-view-toggle-btn ${viewMode === 'calendar' ? 'active' : ''}`}
                        onClick={() => setViewMode('calendar')}
                        title={t`Calendar view`}
                    >
                        <IconCalendar size={16}/>
                    </UnstyledButton>
                </div>
            </div>

            {viewMode === 'calendar' ? (
                <CalendarView
                    occurrences={occurrences}
                    event={event}
                    selectedOccurrenceId={selectedOccurrenceId}
                    onSelect={onSelect}
                />
            ) : (
                <ListView
                    occurrences={occurrences}
                    event={event}
                    selectedOccurrenceId={selectedOccurrenceId}
                    onSelect={onSelect}
                />
            )}
        </div>
    );
};

export default OccurrenceSelector;
