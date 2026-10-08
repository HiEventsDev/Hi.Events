import {t} from "@lingui/macro";
import {KeyboardEvent, ReactNode, useEffect, useMemo, useRef, useState} from "react";
import {Loader, UnstyledButton} from "@mantine/core";
import {DatePicker, DatesProvider} from "@mantine/dates";
import {IconCalendar, IconClock, IconMapPin} from "@tabler/icons-react";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence, EventOccurrenceStatus, EventType, IdParam, RecurrenceRule} from "../../../../types.ts";
import {useGetEventOccurrencesPublic} from "../../../../queries/useGetEventOccurrencesPublic.ts";
import {getEventLocationDisplay} from "../../../../utilites/effectiveLocation.ts";
import {formatDateWithLocale, formatOccurrenceEnd, getSafeLocale} from "../../../../utilites/dates.ts";
import {localeFormats} from "../../../../utilites/dateLocales.ts";
import {getClientLocale} from "../../../../locales.ts";
import './OccurrenceSelector.scss';

dayjs.extend(utc);
dayjs.extend(timezone);

const LOW_AVAILABILITY_THRESHOLD = 10;
const STRIP_SIZE = 7;

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

interface OccurrenceSelectorProps {
    event: Event;
    selectedOccurrenceId?: IdParam;
    pendingInitialOccurrenceId?: IdParam;
    onSelect: (occurrenceId: IdParam, occurrence?: EventOccurrence) => void;
    onClearSelection: () => void;
    colors?: {
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
        background?: string;
    };
    productSlot?: ReactNode;
    isProductsLoading?: boolean;
    waitlistAvailable?: boolean;
}

const dateKey = (occ: EventOccurrence, tz: string): string =>
    dayjs.utc(occ.start_date).tz(tz).format('YYYY-MM-DD');

const byStartDate = (a: EventOccurrence, b: EventOccurrence): number =>
    a.start_date < b.start_date ? -1 : a.start_date > b.start_date ? 1 : 0;

const isBookable = (occ: EventOccurrence): boolean =>
    occ.status === EventOccurrenceStatus.ACTIVE && !occ.is_past;

const isSelectable = (occ: EventOccurrence, waitlistAvailable?: boolean): boolean =>
    isBookable(occ) || (occ.status === EventOccurrenceStatus.SOLD_OUT && !!waitlistAvailable);

const sameId = (a?: IdParam, b?: IdParam): boolean =>
    a !== undefined && a !== null && b !== undefined && b !== null && Number(a) === Number(b);

const getActiveOccurrences = (occurrences: EventOccurrence[]): EventOccurrence[] =>
    occurrences.filter(
        occ => (occ.status === EventOccurrenceStatus.ACTIVE || occ.status === EventOccurrenceStatus.SOLD_OUT) && !occ.is_past
    );

const capacityInfo = (occ: EventOccurrence): {label: string; low: boolean} | null => {
    if (!isBookable(occ)) return null;
    const capacity = occ.available_capacity;
    if (capacity === null || capacity === undefined || capacity <= 0) return null;
    const low = capacity <= LOW_AVAILABILITY_THRESHOLD;
    return {label: low ? t`Only ${capacity} left` : t`${capacity} spots left`, low};
};

const defaultSlot = (slots: EventOccurrence[], waitlistAvailable?: boolean): EventOccurrence | undefined =>
    slots.find(isBookable) ?? slots.find(occ => isSelectable(occ, waitlistAvailable));

const isListed = (occ: EventOccurrence): boolean =>
    occ.status === EventOccurrenceStatus.ACTIVE || occ.status === EventOccurrenceStatus.SOLD_OUT;

const timeRange = (occ: EventOccurrence, tz: string, locale: string): string => {
    const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', tz, locale);
    const endTime = occ.end_date ? formatOccurrenceEnd(occ.end_date, occ.start_date, tz, locale) : null;
    return `${startTime}${endTime ? ` – ${endTime}` : ''}`;
};

const dateCardMeta = (slots: EventOccurrence[], tz: string, locale: string, waitlistAvailable?: boolean): string => {
    const bookable = slots.filter(isBookable);
    if (bookable.length === 0) {
        return waitlistAvailable ? t`Waitlist` : t`Sold out`;
    }
    if (bookable.length === 1) {
        return formatDateWithLocale(bookable[0].start_date, 'timeOnly', tz, locale);
    }
    const count = bookable.length;
    return t`${count} times`;
};

const DateCard = ({
    date,
    slots,
    tz,
    locale,
    todayKey,
    selected,
    tabbable,
    ariaLabel,
    waitlistAvailable,
    onPick,
}: {
    date: string;
    slots: EventOccurrence[];
    tz: string;
    locale: string;
    todayKey: string;
    selected: boolean;
    tabbable: boolean;
    ariaLabel: string;
    waitlistAvailable?: boolean;
    onPick: (date: string) => void;
}) => {
    const first = slots[0];
    const tomorrowKey = dayjs(todayKey).add(1, 'day').format('YYYY-MM-DD');
    const weekday = date === todayKey
        ? t`Today`
        : date === tomorrowKey
            ? t`Tomorrow`
            : dayjs.utc(first.start_date).tz(tz).locale(locale).format('ddd');
    const soldOut = !slots.some(isBookable);

    return (
        <UnstyledButton
            className={[
                'hi-date-card',
                selected ? 'hi-date-card-selected' : '',
                soldOut ? 'hi-date-card-sold-out' : '',
            ].filter(Boolean).join(' ')}
            role="radio"
            aria-checked={selected}
            aria-label={ariaLabel}
            tabIndex={tabbable ? 0 : -1}
            data-date={date}
            onClick={() => onPick(date)}
        >
            <span className="hi-date-card-weekday">{weekday}</span>
            <span className="hi-date-card-date">
                <span className="hi-date-card-day">{formatDateWithLocale(first.start_date, 'dayOfMonth', tz, locale)}</span>
                <span className="hi-date-card-month">{formatDateWithLocale(first.start_date, 'monthShort', tz, locale)}</span>
            </span>
            <span className="hi-date-card-meta">{dateCardMeta(slots, tz, locale, waitlistAvailable)}</span>
        </UnstyledButton>
    );
};

const DateStrip = ({
    dates,
    slotsByDate,
    focusedDate,
    tz,
    locale,
    todayKey,
    isLoading,
    showMoreDates,
    calendarOpen,
    waitlistAvailable,
    dayLabel,
    onPick,
    onToggleCalendar,
}: {
    dates: string[];
    slotsByDate: Record<string, EventOccurrence[]>;
    focusedDate: string;
    tz: string;
    locale: string;
    todayKey: string;
    isLoading: boolean;
    showMoreDates: boolean;
    calendarOpen: boolean;
    waitlistAvailable?: boolean;
    dayLabel: (date: string) => string;
    onPick: (date: string) => void;
    onToggleCalendar: () => void;
}) => {
    const scrollerRef = useRef<HTMLDivElement>(null);
    const [overflow, setOverflow] = useState({start: false, end: false});

    const measureOverflow = () => {
        const node = scrollerRef.current;
        if (!node) return;
        const start = node.scrollLeft > 4;
        const end = node.scrollLeft + node.clientWidth < node.scrollWidth - 4;
        setOverflow(current => (current.start === start && current.end === end ? current : {start, end}));
    };

    useEffect(() => {
        measureOverflow();
        if (typeof window === 'undefined') return;
        window.addEventListener('resize', measureOverflow);
        return () => window.removeEventListener('resize', measureOverflow);
    }, [dates.length, showMoreDates]);

    useEffect(() => {
        const scroller = scrollerRef.current;
        const card = scroller?.querySelector<HTMLElement>(`[data-date="${focusedDate}"]`);
        if (!scroller || !card) return;
        const cardStart = card.offsetLeft - scroller.offsetLeft;
        const cardEnd = cardStart + card.offsetWidth;
        if (cardStart < scroller.scrollLeft) {
            scroller.scrollTo({left: cardStart - 16, behavior: 'smooth'});
        } else if (cardEnd > scroller.scrollLeft + scroller.clientWidth) {
            scroller.scrollTo({left: cardEnd - scroller.clientWidth + 16, behavior: 'smooth'});
        }
    }, [focusedDate, dates.length]);

    const handleArrowKeys = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = ['ArrowRight', 'ArrowDown'].includes(event.key)
            ? 1
            : ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 0;
        if (step === 0 || dates.length === 0) return;
        event.preventDefault();
        const currentIndex = Math.max(dates.indexOf(focusedDate), 0);
        const next = dates[(currentIndex + step + dates.length) % dates.length];
        onPick(next);
        scrollerRef.current?.querySelector<HTMLElement>(`[data-date="${next}"]`)?.focus();
    };

    const tabbableDate = dates.includes(focusedDate) ? focusedDate : dates[0];

    return (
        <div
            className={[
                'hi-date-strip',
                overflow.start ? 'hi-date-strip-fade-start' : '',
                overflow.end ? 'hi-date-strip-fade-end' : '',
            ].filter(Boolean).join(' ')}
            aria-busy={isLoading || undefined}
        >
            <div className="hi-date-strip-scroller" ref={scrollerRef} onScroll={measureOverflow}>
                <div
                    className="hi-date-strip-cards"
                    role="radiogroup"
                    aria-label={t`Upcoming dates`}
                    onKeyDown={handleArrowKeys}
                >
                    {dates.map(date => (
                        <DateCard
                            key={date}
                            date={date}
                            slots={slotsByDate[date]}
                            tz={tz}
                            locale={locale}
                            todayKey={todayKey}
                            selected={date === focusedDate}
                            tabbable={date === tabbableDate}
                            ariaLabel={dayLabel(date)}
                            waitlistAvailable={waitlistAvailable}
                            onPick={onPick}
                        />
                    ))}
                </div>
                {isLoading && (
                    <div className="hi-date-card hi-date-card-loading">
                        <Loader size="xs" color="var(--widget-primary-color, #228be6)"/>
                    </div>
                )}
                {showMoreDates && !isLoading && (
                    <UnstyledButton
                        className={`hi-date-card hi-date-card-more${calendarOpen ? ' hi-date-card-selected' : ''}`}
                        aria-expanded={calendarOpen}
                        data-testid="occurrence-more-dates-button"
                        onClick={onToggleCalendar}
                    >
                        <IconCalendar size={20}/>
                        <span className="hi-date-card-meta">
                            {calendarOpen ? t`Hide calendar` : t`More dates`}
                        </span>
                    </UnstyledButton>
                )}
            </div>
        </div>
    );
};

const TimeChips = ({
    slots,
    dayName,
    tz,
    locale,
    selectedOccurrenceId,
    onSelect,
    waitlistAvailable,
}: {
    slots: EventOccurrence[];
    dayName: string;
    tz: string;
    locale: string;
    selectedOccurrenceId?: IdParam;
    onSelect: (occurrenceId: IdParam, occurrence?: EventOccurrence) => void;
    waitlistAvailable?: boolean;
}) => (
    <div className="hi-time-switcher" role="group" aria-label={t`Available times on ${dayName}`}>
        {slots.map(occ => {
            const selected = sameId(occ.id, selectedOccurrenceId);
            const soldOut = occ.status === EventOccurrenceStatus.SOLD_OUT;
            const selectable = isSelectable(occ, waitlistAvailable);
            const inProgress = !occ.is_past && dayjs.utc(occ.start_date).isBefore(dayjs());
            const spots = capacityInfo(occ);
            const status = soldOut
                ? (selectable ? t`Waitlist` : t`Sold out`)
                : inProgress ? t`In progress` : spots?.label ?? null;
            const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', tz, locale);
            const ariaLabel = [
                timeRange(occ, tz, locale),
                occ.label,
                soldOut ? (selectable ? t`Sold Out, waitlist available` : t`Sold Out`) : status,
            ].filter(Boolean).join(', ');

            return (
                <UnstyledButton
                    key={occ.id}
                    className={[
                        'hi-time-chip',
                        selected ? 'hi-time-chip-active' : '',
                        soldOut ? 'hi-time-chip-sold-out' : '',
                    ].filter(Boolean).join(' ')}
                    disabled={!selectable}
                    aria-pressed={selectable ? selected : undefined}
                    aria-label={ariaLabel}
                    onClick={() => occ.id && onSelect(occ.id, occ)}
                >
                    <span className="hi-time-chip-time">
                        {startTime}
                        {occ.label && <span className="hi-time-slot-label">{occ.label}</span>}
                    </span>
                    {status && (
                        <span className={`hi-time-chip-status${soldOut || spots?.low ? ' hi-time-chip-status-alert' : ''}`}>
                            {status}
                        </span>
                    )}
                </UnstyledButton>
            );
        })}
    </div>
);

const ProductsPane = ({
    event,
    selectedOccurrence,
    tz,
    locale,
    timezoneNote,
    isLoading,
    children,
}: {
    event: Event;
    selectedOccurrence: EventOccurrence;
    tz: string;
    locale: string;
    timezoneNote?: string | null;
    isLoading?: boolean;
    children?: ReactNode;
}) => {
    const dayName = formatDateWithLocale(selectedOccurrence.start_date, 'dayName', tz, locale);
    const spots = capacityInfo(selectedOccurrence);
    const locationDisplay = getEventLocationDisplay(event, selectedOccurrence);

    return (
        <div className="hi-products-pane">
            <div className="hi-slot-header" aria-live="polite">
                <div className="hi-slot-header-day">{dayName}</div>
                <div className="hi-slot-header-time">
                    <IconClock size={13}/>
                    <span>{timeRange(selectedOccurrence, tz, locale)}</span>
                    {timezoneNote && <span>{timezoneNote}</span>}
                    {selectedOccurrence.label && (
                        <span className="hi-time-slot-label">{selectedOccurrence.label}</span>
                    )}
                    {spots && (
                        <span className={`hi-slot-header-spots${spots.low ? ' hi-slot-header-spots-low' : ''}`}>
                            {spots.label}
                        </span>
                    )}
                    {selectedOccurrence.status === EventOccurrenceStatus.SOLD_OUT && (
                        <span className="hi-slot-header-sold-out">{t`Sold Out`}</span>
                    )}
                </div>
                {locationDisplay && (
                    <div className="hi-slot-header-location">
                        <IconMapPin size={13}/>
                        {locationDisplay.isOnline ? (
                            <span>{t`Online`}</span>
                        ) : locationDisplay.mapsUrl ? (
                            <a
                                href={locationDisplay.mapsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="hi-slot-header-location-link"
                            >
                                {locationDisplay.short}
                            </a>
                        ) : (
                            <span>{locationDisplay.short}</span>
                        )}
                    </div>
                )}
            </div>

            <div className={`hi-products-slot${isLoading ? ' hi-products-slot-loading' : ''}`}>
                {isLoading && (
                    <div className="hi-occurrence-loading-overlay">
                        <Loader size="sm" color="var(--widget-primary-color, #228be6)"/>
                    </div>
                )}
                {children}
            </div>
        </div>
    );
};

const monthOf = (key: string): string => key.slice(0, 7);

const OccurrencePicker = ({
    event,
    selectedOccurrenceId,
    pendingInitialOccurrenceId,
    onSelect,
    onClearSelection,
    activeOccurrences,
    productSlot,
    isProductsLoading,
    waitlistAvailable,
}: OccurrenceSelectorProps & {activeOccurrences: EventOccurrence[]}) => {
    const tz = event.timezone;
    const locale = getSafeLocale(getClientLocale());
    const embeddedOccurrences = event.occurrences || [];

    const defaultFocusedDate = () => {
        if (selectedOccurrenceId) {
            const occ = embeddedOccurrences.find(o => sameId(o.id, selectedOccurrenceId));
            if (occ) return dateKey(occ, tz);
        }
        const nextBookable = event.next_occurrence_start_date
            ? activeOccurrences.find(o => dayjs.utc(o.start_date).isSame(dayjs.utc(event.next_occurrence_start_date)))
            : undefined;
        return dateKey(nextBookable ?? activeOccurrences[0], tz);
    };

    const [focusedDate, setFocusedDate] = useState<string>(defaultFocusedDate);
    const [calendarOpen, setCalendarOpen] = useState(false);
    const [displayedMonth, setDisplayedMonth] = useState<string>(
        () => dayjs(defaultFocusedDate()).startOf('month').format('YYYY-MM-DD')
    );

    const embeddedMonthsRef = useRef(new Map<string, EventOccurrence[]>());
    if (event.occurrences_month) {
        embeddedMonthsRef.current.set(event.occurrences_month, embeddedOccurrences);
    }
    const isMonthEmbedded = (monthKey: string) => embeddedMonthsRef.current.has(monthKey);
    const maxDateKey = event.last_occurrence_date
        ? dayjs.utc(event.last_occurrence_date).tz(tz).format('YYYY-MM-DD')
        : undefined;

    const stripMonthKey = dayjs
        .utc(event.next_occurrence_start_date ?? activeOccurrences[0].start_date)
        .tz(tz)
        .format('YYYY-MM');
    const stripNextMonthKey = dayjs(`${stripMonthKey}-01`).add(1, 'month').format('YYYY-MM');
    const stripMonthQuery = useGetEventOccurrencesPublic(event.id, stripMonthKey, tz, !isMonthEmbedded(stripMonthKey));
    const stripMonthLoading = !isMonthEmbedded(stripMonthKey) && stripMonthQuery.isLoading;
    const stripMonthOccurrences = embeddedMonthsRef.current.get(stripMonthKey) ?? stripMonthQuery.data;
    const stripMonthDateCount = new Set(
        getActiveOccurrences(stripMonthOccurrences || [])
            .map(occ => dateKey(occ, tz))
            .filter(key => monthOf(key) === stripMonthKey)
    ).size;
    const stripSpansNextMonth = !stripMonthLoading
        && stripMonthDateCount < STRIP_SIZE
        && !!maxDateKey
        && monthOf(maxDateKey) > stripMonthKey;
    const stripNextMonthQuery = useGetEventOccurrencesPublic(
        event.id,
        stripNextMonthKey,
        tz,
        stripSpansNextMonth && !isMonthEmbedded(stripNextMonthKey),
    );
    const stripLoading = stripMonthLoading
        || (stripSpansNextMonth && !isMonthEmbedded(stripNextMonthKey) && stripNextMonthQuery.isLoading);

    const displayedYearMonth = monthOf(displayedMonth);
    const displayedMonthQuery = useGetEventOccurrencesPublic(
        event.id,
        displayedYearMonth,
        tz,
        calendarOpen && !isMonthEmbedded(displayedYearMonth),
    );

    const displayedMonthLoading = !isMonthEmbedded(displayedYearMonth) && displayedMonthQuery.isLoading;

    const occurrences = useMemo(() => {
        const merged = new Map<number, EventOccurrence>();
        const sources = [
            stripMonthQuery.data,
            stripNextMonthQuery.data,
            displayedMonthQuery.data,
            ...embeddedMonthsRef.current.values(),
            embeddedOccurrences,
        ];
        for (const monthData of sources) {
            for (const occ of monthData || []) {
                if (occ.id !== undefined && occ.id !== null) {
                    merged.set(Number(occ.id), occ);
                }
            }
        }
        return Array.from(merged.values());
    }, [
        embeddedOccurrences,
        stripMonthQuery.data,
        stripNextMonthQuery.data,
        displayedMonthQuery.data,
    ]);

    const {slotsByDate, calendarDays, soldOutOnlyDays} = useMemo(() => {
        const byDate: Record<string, EventOccurrence[]> = {};
        for (const occ of occurrences) {
            if (occ.is_past || !isListed(occ)) continue;
            (byDate[dateKey(occ, tz)] ||= []).push(occ);
        }

        const soldOutOnly = new Set<string>();
        for (const key of Object.keys(byDate)) {
            byDate[key].sort(byStartDate);
            if (!byDate[key].some(isBookable)) soldOutOnly.add(key);
        }

        return {slotsByDate: byDate, calendarDays: new Set(Object.keys(byDate)), soldOutOnlyDays: soldOutOnly};
    }, [occurrences, tz]);

    useEffect(() => {
        if (!selectedOccurrenceId) return;
        const occ = occurrences.find(o => sameId(o.id, selectedOccurrenceId));
        if (!occ) return;
        const key = dateKey(occ, tz);
        setFocusedDate(key);
        setDisplayedMonth(dayjs(key).startOf('month').format('YYYY-MM-DD'));
    }, [selectedOccurrenceId]);

    const clearedOnDateRef = useRef<string | undefined>(undefined);
    const previousSelectedRef = useRef<IdParam | undefined>(selectedOccurrenceId);
    useEffect(() => {
        if (previousSelectedRef.current && !selectedOccurrenceId) {
            clearedOnDateRef.current = focusedDate;
        }
        previousSelectedRef.current = selectedOccurrenceId;
    }, [selectedOccurrenceId]);

    useEffect(() => {
        if (selectedOccurrenceId || pendingInitialOccurrenceId) {
            return;
        }
        const slot = defaultSlot(slotsByDate[focusedDate] || [], waitlistAvailable);
        if (slot?.id && clearedOnDateRef.current !== focusedDate) {
            onSelect(slot.id, slot);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [focusedDate, slotsByDate, selectedOccurrenceId, pendingInitialOccurrenceId]);

    const focusDay = (day: string) => {
        setFocusedDate(day);
        const daySlots = slotsByDate[day] || [];
        const currentSelection = daySlots.find(o => sameId(o.id, selectedOccurrenceId));
        if (currentSelection && isSelectable(currentSelection, waitlistAvailable)) {
            return;
        }
        const slot = defaultSlot(daySlots, waitlistAvailable);
        if (slot?.id) {
            onSelect(slot.id, slot);
        } else if (selectedOccurrenceId) {
            onClearSelection();
        }
    };

    const toggleCalendar = () => {
        if (!calendarOpen) {
            setDisplayedMonth(dayjs(focusedDate).startOf('month').format('YYYY-MM-DD'));
        }
        setCalendarOpen(!calendarOpen);
    };

    const todayKey = dayjs().tz(tz).format('YYYY-MM-DD');
    const minDateKey = [todayKey, ...calendarDays].reduce((min, key) => (key < min ? key : min));
    const focusedSlots = slotsByDate[focusedDate] || [];

    const stripLastMonthKey = stripSpansNextMonth ? stripNextMonthKey : stripMonthKey;
    const nextDates = Array.from(calendarDays)
        .filter(key => monthOf(key) >= stripMonthKey && monthOf(key) <= stripLastMonthKey)
        .sort()
        .slice(0, STRIP_SIZE);
    const lastNextDate = nextDates[nextDates.length - 1];
    const hasMoreDates = !!lastNextDate && (
        (!!maxDateKey && maxDateKey > lastNextDate)
        || Array.from(calendarDays).some(key => key > lastNextDate)
    );
    const stripDates = nextDates.includes(focusedDate) || !calendarDays.has(focusedDate)
        ? nextDates
        : [...nextDates, focusedDate].sort();
    const showDateStep = stripLoading || hasMoreDates || stripDates.length > 1;
    const showTimeStep = focusedSlots.length > 1;

    const selectedOccurrence = selectedOccurrenceId
        ? occurrences.find(o => sameId(o.id, selectedOccurrenceId))
        : null;
    const showingProducts = !!(
        productSlot && selectedOccurrence && dateKey(selectedOccurrence, tz) === focusedDate
    );

    const monthHasDates = useMemo(
        () => Array.from(calendarDays).some(key => key.startsWith(displayedYearMonth)),
        [calendarDays, displayedYearMonth]
    );

    const dayLabel = (key: string): string => {
        const daySlots = slotsByDate[key];
        const baseLabel = daySlots && daySlots.length > 0
            ? formatDateWithLocale(daySlots[0].start_date, 'dayName', tz, locale)
            : dayjs(key).locale(locale).format(localeFormats[locale].dayName);

        if (!calendarDays.has(key)) return baseLabel;
        if (soldOutOnlyDays.has(key)) return `${baseLabel}, ${t`sold out`}`;
        const slotCount = (daySlots || []).filter(isBookable).length;
        return `${baseLabel}, ${slotCount === 1 ? t`1 time available` : t`${slotCount} times available`}`;
    };

    const timezoneAbbr = formatDateWithLocale((focusedSlots[0] ?? activeOccurrences[0]).start_date, 'timezone', tz, locale);
    const timezoneNote = timezoneAbbr ? t`Times in ${timezoneAbbr}` : null;
    const recurrenceSummary = buildRecurrenceSummary(event.recurrence_rule);
    const showHeader = showDateStep || showTimeStep;

    return (
        <>
            {showHeader && (
                <div className="hi-occurrence-selector-header">
                    <div className="hi-occurrence-selector-heading">
                        <h2 className="hi-occurrence-selector-title">
                            {showDateStep ? t`Choose a date` : t`Choose a time`}
                        </h2>
                        {recurrenceSummary && showDateStep && (
                            <span className="hi-occurrence-selector-summary">{recurrenceSummary}</span>
                        )}
                    </div>
                    {timezoneNote && (
                        <span className="hi-occurrence-timezone" title={tz}>{timezoneNote}</span>
                    )}
                </div>
            )}

            {showDateStep && (
                <DateStrip
                    dates={stripDates}
                    slotsByDate={slotsByDate}
                    focusedDate={focusedDate}
                    tz={tz}
                    locale={locale}
                    todayKey={todayKey}
                    isLoading={stripLoading}
                    showMoreDates={hasMoreDates}
                    calendarOpen={calendarOpen}
                    waitlistAvailable={waitlistAvailable}
                    dayLabel={dayLabel}
                    onPick={focusDay}
                    onToggleCalendar={toggleCalendar}
                />
            )}

            {showDateStep && calendarOpen && (
                <div className="hi-calendar-panel" aria-busy={displayedMonthLoading || undefined}>
                    {displayedMonthLoading && (
                        <div className="hi-calendar-month-loading">
                            <Loader size="sm" color="var(--widget-primary-color, #228be6)"/>
                        </div>
                    )}
                    <DatesProvider settings={{locale, firstDayOfWeek: event.organizer?.first_day_of_week ?? 1, consistentWeeks: true}}>
                        <DatePicker
                            className="hi-occurrence-datepicker"
                            size="lg"
                            value={focusedDate}
                            onChange={(value) => {
                                if (!value) return;
                                focusDay(value);
                                setCalendarOpen(false);
                            }}
                            date={displayedMonth}
                            onDateChange={setDisplayedMonth}
                            minDate={minDateKey}
                            maxDate={maxDateKey}
                            allowDeselect={false}
                            highlightToday
                            hideOutsideDates
                            excludeDate={(date) => !calendarDays.has(date)}
                            getDayProps={(date) => (soldOutOnlyDays.has(date) ? {'data-sold-out': 'true'} : {})}
                            getDayAriaLabel={dayLabel}
                            renderDay={(date) => (
                                <span className="hi-day">
                                    <span className="hi-day-number">{dayjs(date).date()}</span>
                                    {calendarDays.has(date) && (
                                        <span className={soldOutOnlyDays.has(date) ? 'hi-day-sold-out-dot' : 'hi-day-dot'}/>
                                    )}
                                </span>
                            )}
                            classNames={{
                                calendarHeader: 'hi-dp-header',
                                calendarHeaderControl: 'hi-dp-nav',
                                calendarHeaderLevel: 'hi-dp-level',
                                weekday: 'hi-dp-weekday',
                                day: 'hi-dp-day',
                                monthsListControl: 'hi-dp-picker-control',
                                yearsListControl: 'hi-dp-picker-control',
                            }}
                        />
                    </DatesProvider>

                    {!monthHasDates && !displayedMonthLoading && (
                        <div className="hi-calendar-no-dates">
                            {t`No dates available this month. Try navigating to another month.`}
                        </div>
                    )}
                </div>
            )}

            {showTimeStep && (
                <TimeChips
                    slots={focusedSlots}
                    dayName={formatDateWithLocale(focusedSlots[0].start_date, 'dayName', tz, locale)}
                    tz={tz}
                    locale={locale}
                    selectedOccurrenceId={selectedOccurrenceId}
                    onSelect={onSelect}
                    waitlistAvailable={waitlistAvailable}
                />
            )}

            {showingProducts ? (
                <ProductsPane
                    event={event}
                    selectedOccurrence={selectedOccurrence!}
                    tz={tz}
                    locale={locale}
                    timezoneNote={showHeader ? null : timezoneNote}
                    isLoading={isProductsLoading}
                >
                    {productSlot}
                </ProductsPane>
            ) : focusedSlots.length > 0 && !defaultSlot(focusedSlots, waitlistAvailable) && (
                <div className="hi-slot-empty">
                    {t`This date is sold out. Please choose another date.`}
                </div>
            )}
        </>
    );
};

export const OccurrenceSelector = ({
    event,
    selectedOccurrenceId,
    pendingInitialOccurrenceId,
    onSelect,
    onClearSelection,
    colors,
    productSlot,
    isProductsLoading,
    waitlistAvailable,
}: OccurrenceSelectorProps) => {
    const activeOccurrences = getActiveOccurrences(event.occurrences || []).sort(byStartDate);

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
            <OccurrencePicker
                event={event}
                selectedOccurrenceId={selectedOccurrenceId}
                pendingInitialOccurrenceId={pendingInitialOccurrenceId}
                onSelect={onSelect}
                onClearSelection={onClearSelection}
                activeOccurrences={activeOccurrences}
                productSlot={productSlot}
                isProductsLoading={isProductsLoading}
                waitlistAvailable={waitlistAvailable}
            />
        </div>
    );
};

export default OccurrenceSelector;
