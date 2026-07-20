import {t} from "@lingui/macro";
import {ReactNode, useEffect, useMemo, useRef, useState} from "react";
import {Loader, UnstyledButton} from "@mantine/core";
import {DatePicker, DatesProvider} from "@mantine/dates";
import {IconCheck, IconClock, IconMapPin} from "@tabler/icons-react";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence, EventOccurrenceStatus, EventType, IdParam, RecurrenceRule} from "../../../../types.ts";
import {EventLocationDisplay, getEventLocationDisplay} from "../../../../utilites/effectiveLocation.ts";
import {formatDateWithLocale, formatOccurrenceEnd, getSafeLocale} from "../../../../utilites/dates.ts";
import {localeFormats} from "../../../../utilites/dateLocales.ts";
import {getClientLocale} from "../../../../locales.ts";
import './OccurrenceSelector.scss';

dayjs.extend(utc);
dayjs.extend(timezone);

const LOW_AVAILABILITY_THRESHOLD = 10;

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
    onSelect: (occurrenceId: IdParam) => void;
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

const SlotRow = ({
    occ,
    tz,
    locale,
    selected,
    onSelect,
    waitlistAvailable,
    locationDisplay,
}: {
    occ: EventOccurrence;
    tz: string;
    locale: string;
    selected: boolean;
    onSelect: (occurrenceId: IdParam) => void;
    waitlistAvailable?: boolean;
    locationDisplay?: EventLocationDisplay | null;
}) => {
    const soldOut = occ.status === EventOccurrenceStatus.SOLD_OUT;
    const cancelled = occ.status === EventOccurrenceStatus.CANCELLED;
    const selectable = isSelectable(occ, waitlistAvailable);
    const inProgress = !cancelled && !occ.is_past && dayjs.utc(occ.start_date).isBefore(dayjs());

    const startTime = formatDateWithLocale(occ.start_date, 'timeOnly', tz, locale);
    const endTime = occ.end_date ? formatOccurrenceEnd(occ.end_date, occ.start_date, tz, locale) : null;
    const timeLabel = `${startTime}${endTime ? ` – ${endTime}` : ''}`;

    const spots = capacityInfo(occ);
    const locationLabel = locationDisplay
        ? (locationDisplay.isOnline ? t`Online` : locationDisplay.short)
        : null;

    const statusLabel = cancelled
        ? t`Cancelled`
        : soldOut
            ? (selectable ? t`Sold Out, waitlist available` : t`Sold Out`)
            : spots?.label ?? null;
    const ariaLabel = [
        timeLabel,
        inProgress ? t`In progress` : null,
        occ.label,
        locationLabel,
        statusLabel,
        selected ? t`Selected` : null,
    ].filter(Boolean).join(', ');

    return (
        <UnstyledButton
            className={[
                'hi-time-slot',
                selected ? 'hi-time-slot-selected' : '',
                !selectable ? 'hi-time-slot-disabled' : '',
            ].filter(Boolean).join(' ')}
            disabled={!selectable}
            aria-pressed={selectable ? selected : undefined}
            aria-label={ariaLabel}
            onClick={() => {
                if (selectable && occ.id) onSelect(occ.id);
            }}
        >
            <div className="hi-time-slot-main">
                <div className="hi-time-slot-time">
                    {selected ? <IconCheck size={15}/> : <IconClock size={15}/>}
                    <span className="hi-time-slot-time-text">{timeLabel}</span>
                    {occ.label && <span className="hi-time-slot-label">{occ.label}</span>}
                </div>
                {locationLabel && (
                    <div className="hi-time-slot-location">
                        <IconMapPin size={12}/>
                        <span>{locationLabel}</span>
                    </div>
                )}
            </div>
            <div className="hi-time-slot-meta">
                {inProgress && (
                    <span className="hi-time-slot-in-progress">{t`In progress`}</span>
                )}
                {cancelled && (
                    <span className="hi-time-slot-cancelled">{t`Cancelled`}</span>
                )}
                {soldOut && (
                    <span className="hi-time-slot-sold-out">{t`Sold Out`}</span>
                )}
                {soldOut && selectable && (
                    <span className="hi-time-slot-waitlist">{t`Waitlist`}</span>
                )}
                {spots && (
                    <span className={`hi-time-slot-spots${spots.low ? ' hi-time-slot-spots-low' : ''}`}>
                        {spots.label}
                    </span>
                )}
            </div>
        </UnstyledButton>
    );
};

const locationDisplayKey = (display: EventLocationDisplay | null): string => {
    if (!display) return 'none';
    if (display.isOnline) return 'online';
    return `${display.short ?? ''}|${display.full ?? ''}`;
};

const TimeSlotList = ({
    event,
    slots,
    tz,
    locale,
    selectedOccurrenceId,
    onSelect,
    waitlistAvailable,
}: {
    event: Event;
    slots: EventOccurrence[];
    tz: string;
    locale: string;
    selectedOccurrenceId?: IdParam;
    onSelect: (occurrenceId: IdParam) => void;
    waitlistAvailable?: boolean;
}) => {
    if (slots.length === 0) {
        return (
            <div className="hi-slot-empty">
                {t`Select a date to see available times`}
            </div>
        );
    }

    const first = slots[0];
    const slotCount = slots.filter(isBookable).length;
    const dayName = formatDateWithLocale(first.start_date, 'dayName', tz, locale);
    const monthShort = formatDateWithLocale(first.start_date, 'monthShort', tz, locale);
    const dayOfMonth = formatDateWithLocale(first.start_date, 'dayOfMonth', tz, locale);

    const slotDisplays = slots.map((occ) => getEventLocationDisplay(event, occ));
    const locationsVary = new Set(slotDisplays.map(locationDisplayKey)).size > 1;
    const sharedDisplay = locationsVary ? null : slotDisplays[0];

    return (
        <div className="hi-slot-panel-inner">
            <div className="hi-slot-header" aria-live="polite">
                <div className="hi-slot-date-badge" aria-hidden="true">
                    <span className="hi-slot-badge-month">{monthShort}</span>
                    <span className="hi-slot-badge-day">{dayOfMonth}</span>
                </div>
                <div className="hi-slot-header-text">
                    <div className="hi-slot-header-day">{dayName}</div>
                    <div className="hi-slot-header-count">
                        {slotCount === 0
                            ? t`Sold out`
                            : slotCount === 1 ? t`1 time available` : t`${slotCount} times available`}
                    </div>
                    {sharedDisplay && (
                        <div className="hi-slot-header-location">
                            <IconMapPin size={13}/>
                            {sharedDisplay.isOnline ? (
                                <span>{t`Online`}</span>
                            ) : sharedDisplay.mapsUrl ? (
                                <a
                                    href={sharedDisplay.mapsUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="hi-slot-header-location-link"
                                >
                                    {sharedDisplay.short}
                                </a>
                            ) : (
                                <span>{sharedDisplay.short}</span>
                            )}
                        </div>
                    )}
                </div>
            </div>
            <div className="hi-slot-list" role="group" aria-label={t`Available times on ${dayName}`}>
                {slots.map((occ, index) => (
                    <SlotRow
                        key={occ.id}
                        occ={occ}
                        tz={tz}
                        locale={locale}
                        selected={sameId(selectedOccurrenceId, occ.id)}
                        onSelect={onSelect}
                        waitlistAvailable={waitlistAvailable}
                        locationDisplay={locationsVary ? slotDisplays[index] : null}
                    />
                ))}
            </div>
        </div>
    );
};

const ProductsPane = ({
    event,
    daySlots,
    selectedOccurrence,
    tz,
    locale,
    onSelect,
    isLoading,
    waitlistAvailable,
    children,
}: {
    event: Event;
    daySlots: EventOccurrence[];
    selectedOccurrence: EventOccurrence;
    tz: string;
    locale: string;
    onSelect: (occurrenceId: IdParam) => void;
    isLoading?: boolean;
    waitlistAvailable?: boolean;
    children?: ReactNode;
}) => {
    const switcherSlots = daySlots.filter(
        occ => isBookable(occ) || occ.status === EventOccurrenceStatus.SOLD_OUT
    );
    const dayName = formatDateWithLocale(selectedOccurrence.start_date, 'dayName', tz, locale);
    const monthShort = formatDateWithLocale(selectedOccurrence.start_date, 'monthShort', tz, locale);
    const dayOfMonth = formatDateWithLocale(selectedOccurrence.start_date, 'dayOfMonth', tz, locale);
    const startTime = formatDateWithLocale(selectedOccurrence.start_date, 'timeOnly', tz, locale);
    const endTime = selectedOccurrence.end_date
        ? formatOccurrenceEnd(selectedOccurrence.end_date, selectedOccurrence.start_date, tz, locale)
        : null;
    const timeLabel = `${startTime}${endTime ? ` – ${endTime}` : ''}`;
    const spots = capacityInfo(selectedOccurrence);
    const locationDisplay = getEventLocationDisplay(event, selectedOccurrence);

    return (
        <div className="hi-products-pane">
            <div className="hi-slot-header">
                <div className="hi-slot-date-badge" aria-hidden="true">
                    <span className="hi-slot-badge-month">{monthShort}</span>
                    <span className="hi-slot-badge-day">{dayOfMonth}</span>
                </div>
                <div className="hi-slot-header-text">
                    <div className="hi-slot-header-day">{dayName}</div>
                    <div className="hi-slot-header-time">
                        <IconClock size={13}/>
                        <span>{timeLabel}</span>
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
            </div>

            {switcherSlots.length > 1 && (
                <div className="hi-time-switcher" role="group" aria-label={t`Change time`}>
                    {switcherSlots.map(occ => {
                        const isSelected = sameId(occ.id, selectedOccurrence.id);
                        const soldOut = occ.status === EventOccurrenceStatus.SOLD_OUT;
                        const chipDisabled = soldOut && !waitlistAvailable;
                        const chipTime = formatDateWithLocale(occ.start_date, 'timeOnly', tz, locale);
                        return (
                            <UnstyledButton
                                key={occ.id}
                                className={`hi-time-chip${isSelected ? ' hi-time-chip-active' : ''}${soldOut ? ' hi-time-chip-sold-out' : ''}`}
                                disabled={chipDisabled}
                                aria-pressed={chipDisabled ? undefined : isSelected}
                                aria-label={soldOut
                                    ? (chipDisabled ? t`${chipTime}, Sold Out` : t`${chipTime}, Sold Out, waitlist available`)
                                    : undefined}
                                onClick={() => occ.id && onSelect(occ.id)}
                            >
                                {chipTime}
                                {soldOut && (
                                    <span className="hi-time-chip-sold-out-label">{t`Sold Out`}</span>
                                )}
                            </UnstyledButton>
                        );
                    })}
                </div>
            )}

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

const OccurrencePicker = ({
    event,
    selectedOccurrenceId,
    pendingInitialOccurrenceId,
    onSelect,
    activeOccurrences,
    productSlot,
    isProductsLoading,
    waitlistAvailable,
}: OccurrenceSelectorProps & {activeOccurrences: EventOccurrence[]}) => {
    const tz = event.timezone;
    const locale = getSafeLocale(getClientLocale());
    const occurrences = event.occurrences || [];

    const {occurrencesByDate, calendarDays, soldOutOnlyDays} = useMemo(() => {
        const byDate: Record<string, EventOccurrence[]> = {};
        for (const occ of occurrences) {
            if (occ.is_past) continue;
            if (
                occ.status !== EventOccurrenceStatus.ACTIVE &&
                occ.status !== EventOccurrenceStatus.SOLD_OUT &&
                occ.status !== EventOccurrenceStatus.CANCELLED
            ) continue;
            const key = dateKey(occ, tz);
            (byDate[key] ||= []).push(occ);
        }

        const shown = new Set<string>();
        const soldOutOnly = new Set<string>();
        for (const key of Object.keys(byDate)) {
            byDate[key].sort(byStartDate);
            const bookable = byDate[key].some(isBookable);
            const anyBookableOrSoldOut = byDate[key].some(
                o => o.status === EventOccurrenceStatus.ACTIVE || o.status === EventOccurrenceStatus.SOLD_OUT
            );
            if (anyBookableOrSoldOut) shown.add(key);
            if (anyBookableOrSoldOut && !bookable) soldOutOnly.add(key);
        }

        return {occurrencesByDate: byDate, calendarDays: shown, soldOutOnlyDays: soldOutOnly};
    }, [occurrences, tz]);

    const defaultFocusedDate = () => {
        if (selectedOccurrenceId) {
            const occ = occurrences.find(o => sameId(o.id, selectedOccurrenceId));
            if (occ) return dateKey(occ, tz);
        }
        return dateKey(activeOccurrences[0], tz);
    };

    const [focusedDate, setFocusedDate] = useState<string>(defaultFocusedDate);
    const [displayedMonth, setDisplayedMonth] = useState<string>(
        () => dayjs(defaultFocusedDate()).startOf('month').format('YYYY-MM-DD')
    );

    useEffect(() => {
        if (!selectedOccurrenceId) return;
        const occ = occurrences.find(o => sameId(o.id, selectedOccurrenceId));
        if (!occ) return;
        const key = dateKey(occ, tz);
        setFocusedDate(key);
        setDisplayedMonth(dayjs(key).startOf('month').format('YYYY-MM-DD'));
    }, [selectedOccurrenceId]);

    useEffect(() => {
        if (selectedOccurrenceId || pendingInitialOccurrenceId) {
            return;
        }
        const bookable = (occurrencesByDate[focusedDate] || []).filter(isBookable);
        if (bookable.length === 1 && bookable[0].id) {
            onSelect(bookable[0].id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [focusedDate, occurrencesByDate, selectedOccurrenceId, pendingInitialOccurrenceId]);

    const focusDay = (day: string) => {
        setFocusedDate(day);
        const daySlots = occurrencesByDate[day] || [];
        const currentSelection = daySlots.find(o => sameId(o.id, selectedOccurrenceId));
        if (currentSelection && isSelectable(currentSelection, waitlistAvailable)) {
            return;
        }
        const bookable = daySlots.filter(isBookable);
        if (bookable.length === 1 && bookable[0].id) {
            onSelect(bookable[0].id);
        }
    };

    const slotPanelRef = useRef<HTMLDivElement>(null);
    const skipFirstScroll = useRef(true);
    useEffect(() => {
        if (skipFirstScroll.current) {
            skipFirstScroll.current = false;
            return;
        }
        if (typeof window === 'undefined') return;
        if (window.matchMedia('(min-width: 768px)').matches) return;

        const node = slotPanelRef.current;
        if (!node) return;
        const rect = node.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        if (rect.top >= 0 && rect.bottom <= viewportHeight) return;

        const handle = window.requestAnimationFrame(() => {
            node.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
        return () => window.cancelAnimationFrame(handle);
    }, [focusedDate]);

    const todayKey = dayjs().tz(tz).format('YYYY-MM-DD');
    const minDateKey = [todayKey, ...calendarDays].reduce((min, key) => (key < min ? key : min));
    const focusedSlots = occurrencesByDate[focusedDate] || [];

    const selectedOccurrence = selectedOccurrenceId
        ? occurrences.find(o => sameId(o.id, selectedOccurrenceId))
        : null;
    const showingProducts = !!(
        productSlot && selectedOccurrence && dateKey(selectedOccurrence, tz) === focusedDate
    );
    const pendingAutoSelect = !!(
        productSlot && !showingProducts && focusedSlots.filter(isBookable).length === 1
    );

    const displayedYearMonth = displayedMonth.slice(0, 7);
    const monthHasDates = useMemo(
        () => Array.from(calendarDays).some(key => key.startsWith(displayedYearMonth)),
        [calendarDays, displayedYearMonth]
    );

    const dayAvailabilityLabel = (key: string): string => {
        const daySlots = occurrencesByDate[key];
        const baseLabel = daySlots && daySlots.length > 0
            ? formatDateWithLocale(daySlots[0].start_date, 'dayName', tz, locale)
            : dayjs(key).locale(locale).format(localeFormats[locale].dayName);

        if (!calendarDays.has(key)) return baseLabel;
        if (soldOutOnlyDays.has(key)) return `${baseLabel}, ${t`sold out`}`;
        const slotCount = (daySlots || []).filter(isBookable).length;
        return `${baseLabel}, ${slotCount === 1 ? t`1 time available` : t`${slotCount} times available`}`;
    };

    return (
        <div className="hi-occurrence-body">
            <div className="hi-slot-panel" ref={slotPanelRef}>
                {showingProducts ? (
                    <ProductsPane
                        event={event}
                        daySlots={focusedSlots}
                        selectedOccurrence={selectedOccurrence!}
                        tz={tz}
                        locale={locale}
                        onSelect={onSelect}
                        isLoading={isProductsLoading}
                        waitlistAvailable={waitlistAvailable}
                    >
                        {productSlot}
                    </ProductsPane>
                ) : pendingAutoSelect ? (
                    <div className="hi-slot-loading">
                        <Loader size="sm" color="var(--widget-primary-color, #228be6)"/>
                    </div>
                ) : (
                    <TimeSlotList
                        event={event}
                        slots={focusedSlots}
                        tz={tz}
                        locale={locale}
                        selectedOccurrenceId={selectedOccurrenceId}
                        onSelect={onSelect}
                        waitlistAvailable={waitlistAvailable}
                    />
                )}
            </div>

            <div className="hi-calendar-panel">
                <DatesProvider settings={{locale, firstDayOfWeek: 1, consistentWeeks: true}}>
                    <DatePicker
                        className="hi-occurrence-datepicker"
                        size="md"
                        value={focusedDate}
                        onChange={(value) => {
                            if (value) focusDay(value);
                        }}
                        date={displayedMonth}
                        onDateChange={setDisplayedMonth}
                        minDate={minDateKey}
                        allowDeselect={false}
                        highlightToday
                        hideOutsideDates
                        excludeDate={(date) => !calendarDays.has(date)}
                        getDayProps={(date) => (soldOutOnlyDays.has(date) ? {'data-sold-out': 'true'} : {})}
                        getDayAriaLabel={dayAvailabilityLabel}
                        renderDay={(date) => {
                            const daySlots = occurrencesByDate[date] || [];
                            const bookableCount = daySlots.filter(isBookable).length;
                            const soldOut = calendarDays.has(date) && bookableCount === 0;
                            return (
                                <span className="hi-day">
                                    <span className="hi-day-number">{dayjs(date).date()}</span>
                                    {bookableCount === 1 && <span className="hi-day-dot"/>}
                                    {bookableCount > 1 && <span className="hi-day-count">{bookableCount}</span>}
                                    {soldOut && <span className="hi-day-sold-out-dot"/>}
                                </span>
                            );
                        }}
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

                {!monthHasDates && (
                    <div className="hi-calendar-no-dates">
                        {t`No dates available this month. Try navigating to another month.`}
                    </div>
                )}

                <div className="hi-calendar-legend">
                    <span className="hi-calendar-legend-dot" aria-hidden="true"/>
                    <span>{t`Dates with sessions`}</span>
                </div>
            </div>
        </div>
    );
};

export const OccurrenceSelector = ({
    event,
    selectedOccurrenceId,
    pendingInitialOccurrenceId,
    onSelect,
    colors,
    productSlot,
    isProductsLoading,
    waitlistAvailable,
}: OccurrenceSelectorProps) => {
    const occurrences = event.occurrences || [];
    const activeOccurrences = getActiveOccurrences(occurrences).sort(byStartDate);

    if (event.type !== EventType.RECURRING || activeOccurrences.length === 0) {
        return null;
    }

    const tz = event.timezone;
    const locale = getSafeLocale(getClientLocale());
    const timezoneAbbr = formatDateWithLocale(activeOccurrences[0].start_date, 'timezone', tz, locale);
    const recurrenceSummary = buildRecurrenceSummary(event.recurrence_rule);

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
                <div className="hi-occurrence-selector-heading">
                    <h2 className="hi-occurrence-selector-title">
                        {t`Select a Date & Time`}
                    </h2>
                    {recurrenceSummary && (
                        <span className="hi-occurrence-selector-summary">{recurrenceSummary}</span>
                    )}
                </div>
                {timezoneAbbr && (
                    <span className="hi-occurrence-timezone" title={tz}>
                        {t`Times shown in ${timezoneAbbr}`}
                    </span>
                )}
            </div>

            <OccurrencePicker
                event={event}
                selectedOccurrenceId={selectedOccurrenceId}
                pendingInitialOccurrenceId={pendingInitialOccurrenceId}
                onSelect={onSelect}
                activeOccurrences={activeOccurrences}
                productSlot={productSlot}
                isProductsLoading={isProductsLoading}
                waitlistAvailable={waitlistAvailable}
            />
        </div>
    );
};

export default OccurrenceSelector;
