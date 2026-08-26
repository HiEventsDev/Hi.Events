import React, {useEffect, useId, useMemo, useState} from "react";
import {t} from "@lingui/macro";
import {
    ActionIcon,
    Button,
    CloseButton,
    Drawer,
    NumberInput,
    SegmentedControl,
    Select,
    Stack,
    Text,
    TextInput,
} from "@mantine/core";
import {useForm} from "@mantine/form";
import {modals} from "@mantine/modals";
import {useParams} from "react-router";
import {
    IconAlertTriangle,
    IconChevronLeft,
    IconChevronRight,
    IconPlus,
    IconSparkles,
    IconX,
} from "@tabler/icons-react";
import classNames from "classnames";

import {GenericModalProps, RecurrenceRule, RecurrenceTimeSlot} from "../../../../../types.ts";
import {useGenerateOccurrences} from "../../../../../mutations/useGenerateOccurrences.ts";
import {GET_EVENT_QUERY_KEY, useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../../../../../queries/useGetEventOccurrences.ts";
import {useQueryClient} from "@tanstack/react-query";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import classes from './RecurrenceScheduleDrawer.module.scss';

const MAX_PREVIEW = 1200;

const DAYS_OF_WEEK = [
    {value: 'monday', label: t`Mon`, full: t`Monday`},
    {value: 'tuesday', label: t`Tue`, full: t`Tuesday`},
    {value: 'wednesday', label: t`Wed`, full: t`Wednesday`},
    {value: 'thursday', label: t`Thu`, full: t`Thursday`},
    {value: 'friday', label: t`Fri`, full: t`Friday`},
    {value: 'saturday', label: t`Sat`, full: t`Saturday`},
    {value: 'sunday', label: t`Sun`, full: t`Sunday`},
];

const FREQUENCIES = [
    {value: 'daily', label: t`Daily`},
    {value: 'weekly', label: t`Weekly`},
    {value: 'monthly', label: t`Monthly`},
    {value: 'yearly', label: t`Yearly`},
];

const WEEK_POSITIONS = [
    {value: '1', label: t`First`},
    {value: '2', label: t`Second`},
    {value: '3', label: t`Third`},
    {value: '4', label: t`Fourth`},
    {value: '-1', label: t`Last`},
];

const MONTHS = [
    {value: '1', label: t`January`},
    {value: '2', label: t`February`},
    {value: '3', label: t`March`},
    {value: '4', label: t`April`},
    {value: '5', label: t`May`},
    {value: '6', label: t`June`},
    {value: '7', label: t`July`},
    {value: '8', label: t`August`},
    {value: '9', label: t`September`},
    {value: '10', label: t`October`},
    {value: '11', label: t`November`},
    {value: '12', label: t`December`},
];

const DAY_NUMBER_MAP: Record<string, number> = {
    'sunday': 0, 'monday': 1, 'tuesday': 2, 'wednesday': 3,
    'thursday': 4, 'friday': 5, 'saturday': 6,
};

const frequencyUnitLabel = (frequency: string, interval: number): string => {
    if (interval === 1) {
        switch (frequency) {
            case 'daily': return t`day`;
            case 'weekly': return t`week`;
            case 'monthly': return t`month`;
            case 'yearly': return t`year`;
            default: return '';
        }
    }
    switch (frequency) {
        case 'daily': return t`days`;
        case 'weekly': return t`weeks`;
        case 'monthly': return t`months`;
        case 'yearly': return t`years`;
        default: return '';
    }
};

const getNthWeekdayOfMonth = (year: number, month: number, dayOfWeek: number, position: number): Date | null => {
    if (position === -1) {
        const lastDay = new Date(year, month + 1, 0);
        for (let d = lastDay.getDate(); d >= 1; d--) {
            const candidate = new Date(year, month, d);
            if (candidate.getDay() === dayOfWeek) return candidate;
        }
        return null;
    }
    let count = 0;
    for (let d = 1; d <= 31; d++) {
        const candidate = new Date(year, month, d);
        if (candidate.getMonth() !== month) break;
        if (candidate.getDay() === dayOfWeek) {
            count++;
            if (count === position) return candidate;
        }
    }
    return null;
};

const parseLocalDate = (value: string): Date | null => {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
};

const computePreviewDates = (values: RecurrenceFormValues): Date[] => {
    const dates: Date[] = [];
    const today = parseLocalDate(values.range_start) ?? new Date();
    today.setHours(0, 0, 0, 0);

    const endDate = values.range_type === 'until' && values.range_until
        ? new Date(values.range_until + 'T23:59:59')
        : null;
    const maxCount = values.range_type === 'count'
        ? Math.min(values.range_count || 1, MAX_PREVIEW)
        : MAX_PREVIEW;

    if (values.range_type === 'until' && !endDate) return dates;

    const addCandidate = (date: Date): boolean => {
        if (endDate && date > endDate) return false;
        if (dates.length >= maxCount) return false;
        dates.push(new Date(date));
        return true;
    };

    switch (values.frequency) {
        case 'daily': {
            const current = new Date(today);
            let safety = 0;
            while (dates.length < maxCount && safety < MAX_PREVIEW + 100) {
                if (!addCandidate(current)) break;
                current.setDate(current.getDate() + (values.interval || 1));
                safety++;
            }
            break;
        }
        case 'weekly': {
            const selectedDays = values.days_of_week
                .map(d => DAY_NUMBER_MAP[d])
                .filter(d => d !== undefined)
                .sort((a, b) => a - b);
            if (selectedDays.length === 0) break;

            const weekStart = new Date(today);
            const todayDay = weekStart.getDay();
            const diff = todayDay === 0 ? -6 : 1 - todayDay;
            weekStart.setDate(weekStart.getDate() + diff);

            let safety = 0;
            outer:
            while (dates.length < maxCount && safety < MAX_PREVIEW + 100) {
                for (const dayNum of selectedDays) {
                    const candidate = new Date(weekStart);
                    const offset = dayNum === 0 ? 6 : dayNum - 1;
                    candidate.setDate(weekStart.getDate() + offset);
                    if (candidate >= today) {
                        if (!addCandidate(candidate)) break outer;
                    }
                }
                weekStart.setDate(weekStart.getDate() + 7 * (values.interval || 1));
                safety++;
            }
            break;
        }
        case 'monthly': {
            if (values.monthly_pattern === 'by_day_of_month') {
                const days = values.days_of_month
                    .map(d => parseInt(d))
                    .filter(n => !isNaN(n))
                    .sort((a, b) => a - b);
                if (days.length === 0) break;

                const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                let safety = 0;
                outer2:
                while (dates.length < maxCount && safety < MAX_PREVIEW + 100) {
                    for (const day of days) {
                        const candidate = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
                        if (candidate.getMonth() !== currentMonth.getMonth()) continue;
                        if (candidate >= today) {
                            if (!addCandidate(candidate)) break outer2;
                        }
                    }
                    currentMonth.setMonth(currentMonth.getMonth() + (values.interval || 1));
                    safety++;
                }
            } else {
                const targetDay = DAY_NUMBER_MAP[values.day_of_week] ?? 1;
                const position = parseInt(values.week_position) || 1;

                const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                let safety = 0;
                while (dates.length < maxCount && safety < MAX_PREVIEW + 100) {
                    const candidate = getNthWeekdayOfMonth(
                        currentMonth.getFullYear(), currentMonth.getMonth(), targetDay, position
                    );
                    if (candidate && candidate >= today) {
                        if (!addCandidate(candidate)) break;
                    }
                    currentMonth.setMonth(currentMonth.getMonth() + (values.interval || 1));
                    safety++;
                }
            }
            break;
        }
        case 'yearly': {
            const month = parseInt(values.yearly_month) - 1;
            const day = values.yearly_day;
            let year = today.getFullYear();
            let safety = 0;
            while (dates.length < maxCount && safety < MAX_PREVIEW + 100) {
                const candidate = new Date(year, month, day);
                if (candidate.getMonth() === month && candidate >= today) {
                    if (!addCandidate(candidate)) break;
                }
                if (endDate && candidate > endDate) break;
                year += (values.interval || 1);
                safety++;
            }
            break;
        }
    }

    return dates;
};

const computeEndTime = (startTime: string, durationMinutes: number): string => {
    if (!startTime || !durationMinutes) return '';
    const [h, m] = startTime.split(':').map(Number);
    if (isNaN(h) || isNaN(m)) return '';
    const totalMinutes = h * 60 + m + durationMinutes;
    const endH = Math.floor(totalMinutes / 60) % 24;
    const endM = totalMinutes % 60;
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
};

const computeDurationFromTimes = (startTime: string, endTime: string): number | null => {
    if (!startTime || !endTime) return null;
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = endTime.split(':').map(Number);
    if (isNaN(sh) || isNaN(sm) || isNaN(eh) || isNaN(em)) return null;
    let diff = (eh * 60 + em) - (sh * 60 + sm);
    if (diff <= 0) diff += 24 * 60;
    return diff;
};

const slotWrapsMidnight = (slot: TimeSlotFormValue): boolean => {
    if (!slot.time || !slot.end_time) return false;
    const [sh, sm] = slot.time.split(':').map(Number);
    const [eh, em] = slot.end_time.split(':').map(Number);
    if (isNaN(sh) || isNaN(sm) || isNaN(eh) || isNaN(em)) return false;
    return (eh * 60 + em) - (sh * 60 + sm) <= 0;
};

const formatShortDate = (date: Date): string => {
    return date.toLocaleDateString(undefined, {day: 'numeric', month: 'short'});
};

interface TimeSlotFormValue {
    time: string;
    end_time: string;
    label: string;
}

interface RecurrenceFormValues {
    frequency: string;
    interval: number;
    days_of_week: string[];
    time_slots: TimeSlotFormValue[];
    range_start: string;
    range_type: string;
    range_until: string;
    range_count: number;
    default_capacity: number | undefined;
    monthly_pattern: string;
    days_of_month: string[];
    day_of_week: string;
    week_position: string;
    yearly_month: string;
    yearly_day: number;
}

const formatLocalDate = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const todayLocalDate = (): string => formatLocalDate(new Date());

const IntervalStepper = ({value, onChange}: { value: number; onChange: (value: number) => void }) => (
    <div className={classes.stepper}>
        <button
            type="button"
            aria-label={t`Decrease`}
            disabled={(value || 1) <= 1}
            onClick={() => onChange(Math.max(1, (value || 1) - 1))}
        >
            −
        </button>
        <span>{value || 1}</span>
        <button
            type="button"
            aria-label={t`Increase`}
            onClick={() => onChange((value || 1) + 1)}
        >
            +
        </button>
    </div>
);

const DayDots = ({selected, onToggle}: { selected: string[]; onToggle: (value: string) => void }) => (
    <div className={classes.dayDots}>
        {DAYS_OF_WEEK.map(day => {
            const isSelected = selected.includes(day.value);
            return (
                <button
                    key={day.value}
                    type="button"
                    role="checkbox"
                    aria-checked={isSelected}
                    aria-label={day.full}
                    className={classNames(classes.dayDot, isSelected && classes.selected)}
                    onClick={() => onToggle(day.value)}
                >
                    <span>{day.label}</span>
                </button>
            );
        })}
    </div>
);

const MonthDayGrid = ({selected, onToggle}: { selected: string[]; onToggle: (value: string) => void }) => (
    <div className={classes.monthGrid}>
        {Array.from({length: 31}, (_, i) => String(i + 1)).map(day => {
            const isSelected = selected.includes(day);
            return (
                <button
                    key={day}
                    type="button"
                    role="checkbox"
                    aria-checked={isSelected}
                    aria-label={day}
                    className={classNames(classes.monthCell, isSelected && classes.selected)}
                    onClick={() => onToggle(day)}
                >
                    {day}
                </button>
            );
        })}
    </div>
);

const monthIndexOf = (date: Date): number => date.getFullYear() * 12 + date.getMonth();

const MiniCalendar = ({previewDates, rangeStart}: { previewDates: Date[]; rangeStart: string }) => {
    const [viewMonth, setViewMonth] = useState<number | null>(null);

    const firstMonth = monthIndexOf(previewDates[0]);
    const lastMonth = monthIndexOf(previewDates[previewDates.length - 1]);

    useEffect(() => {
        setViewMonth(current => current === null || current < firstMonth || current > lastMonth
            ? firstMonth
            : current);
    }, [firstMonth, lastMonth]);

    const month = viewMonth === null || viewMonth < firstMonth || viewMonth > lastMonth ? firstMonth : viewMonth;
    const year = Math.floor(month / 12);
    const monthOfYear = month % 12;

    const occurrenceKeys = useMemo(
        () => new Set(previewDates.map(formatLocalDate)),
        [previewDates]
    );

    const startDate = parseLocalDate(rangeStart);
    const firstOfMonth = new Date(year, monthOfYear, 1);
    const gridStart = new Date(firstOfMonth);
    gridStart.setDate(gridStart.getDate() - ((gridStart.getDay() + 6) % 7));

    const cells = Array.from({length: 42}, (_, i) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + i);
        return date;
    });

    return (
        <div className={classes.calendarCard}>
            <div className={classes.calendarHeader}>
                <button
                    type="button"
                    aria-label={t`Previous month`}
                    disabled={month <= firstMonth}
                    onClick={() => setViewMonth(month - 1)}
                >
                    <IconChevronLeft size={14}/>
                </button>
                <span>{firstOfMonth.toLocaleDateString(undefined, {month: 'long', year: 'numeric'})}</span>
                <button
                    type="button"
                    aria-label={t`Next month`}
                    disabled={month >= lastMonth}
                    onClick={() => setViewMonth(month + 1)}
                >
                    <IconChevronRight size={14}/>
                </button>
            </div>
            <div className={classes.calendarWeekdays}>
                {DAYS_OF_WEEK.map(day => <span key={day.value}>{day.label}</span>)}
            </div>
            <div className={classes.calendarGrid}>
                {cells.map((date, i) => {
                    const outside = date.getMonth() !== monthOfYear
                        || (startDate !== null && date < startDate);
                    const isOccurrence = occurrenceKeys.has(formatLocalDate(date));
                    return (
                        <span
                            key={i}
                            className={classNames(
                                classes.calendarCell,
                                outside && classes.outside,
                                isOccurrence && classes.occurrence,
                            )}
                        >
                            {date.getDate()}
                        </span>
                    );
                })}
            </div>
        </div>
    );
};

interface RuleSentence {
    bold: string;
    rest: string;
}

interface RecurrenceScheduleDrawerProps extends GenericModalProps {
    onGenerationStarted: (jobUuid: string, totalCount: number) => void;
}

export const RecurrenceScheduleDrawer = ({onClose, onGenerationStarted}: RecurrenceScheduleDrawerProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const generateMutation = useGenerateOccurrences();
    const queryClient = useQueryClient();
    const errorHandler = useFormErrorResponseHandler();
    const headingId = useId();
    const formId = useId();

    const hasExistingRule = !!event?.recurrence_rule;

    const parseTimeSlotsFromRule = (rule: RecurrenceRule): TimeSlotFormValue[] => {
        const times = rule.times_of_day;
        const fallbackDuration = rule.duration_minutes || 120;

        if (!times?.length) {
            return [{time: '09:00', end_time: computeEndTime('09:00', fallbackDuration), label: ''}];
        }

        return times.map((entry) => {
            if (typeof entry === 'string') {
                return {
                    time: entry,
                    end_time: computeEndTime(entry, fallbackDuration),
                    label: '',
                };
            }
            const duration = entry.duration_minutes || fallbackDuration;
            return {
                time: entry.time,
                end_time: computeEndTime(entry.time, duration),
                label: entry.label || '',
            };
        });
    };

    const form = useForm<RecurrenceFormValues>({
        initialValues: {
            frequency: 'weekly',
            interval: 1,
            days_of_week: [],
            time_slots: [{time: '09:00', end_time: '11:00', label: ''}],
            range_start: todayLocalDate(),
            range_type: 'until',
            range_until: '',
            range_count: 10,
            default_capacity: undefined,
            monthly_pattern: 'by_day_of_month',
            days_of_month: ['1'],
            day_of_week: 'monday',
            week_position: '1',
            yearly_month: String(new Date().getMonth() + 1),
            yearly_day: 1,
        },
        validate: {
            days_of_week: (value, values) => values.frequency === 'weekly' && value.length === 0
                ? t`Pick at least one day of the week`
                : null,
            range_until: (value, values) => values.range_type === 'until' && !value
                ? t`Pick an end date`
                : null,
            time_slots: (value) => value.every(s => !s.time.trim())
                ? t`Add at least one time`
                : null,
            days_of_month: (value, values) => values.frequency === 'monthly'
                && values.monthly_pattern === 'by_day_of_month'
                && value.length === 0
                    ? t`Pick at least one day of the month`
                    : null,
        },
    });

    useEffect(() => {
        if (event?.recurrence_rule) {
            const rule = event.recurrence_rule;
            const earliestOccurrence = event.occurrences?.length
                ? event.occurrences
                    .map(o => o.start_date)
                    .filter((d): d is string => !!d)
                    .sort()[0]
                : undefined;
            const startFromRule = rule.range?.start;
            const fallbackStart = earliestOccurrence
                ? earliestOccurrence.slice(0, 10)
                : todayLocalDate();

            form.setValues({
                frequency: rule.frequency || 'weekly',
                interval: rule.interval || 1,
                days_of_week: rule.days_of_week || [],
                time_slots: parseTimeSlotsFromRule(rule),
                range_start: startFromRule ? startFromRule.slice(0, 10) : fallbackStart,
                range_type: rule.range?.type || 'until',
                range_until: rule.range?.until || '',
                range_count: rule.range?.count || 10,
                default_capacity: rule.default_capacity ?? undefined,
                monthly_pattern: rule.monthly_pattern || 'by_day_of_month',
                days_of_month: rule.days_of_month?.map(String) || ['1'],
                day_of_week: rule.day_of_week || 'monday',
                week_position: String(rule.week_position || 1),
                yearly_month: String(rule.month || new Date().getMonth() + 1),
                yearly_day: rule.days_of_month?.[0] || 1,
            });
            form.resetDirty();
        }
    }, [event]);

    const toggleDayOfWeek = (value: string) => {
        const current = form.values.days_of_week;
        form.setFieldValue('days_of_week', current.includes(value)
            ? current.filter(d => d !== value)
            : [...current, value]);
    };

    const toggleDayOfMonth = (value: string) => {
        const current = form.values.days_of_month;
        form.setFieldValue('days_of_month', current.includes(value)
            ? current.filter(d => d !== value)
            : [...current, value]);
    };

    const handleAddTime = () => {
        const lastSlot = form.values.time_slots[form.values.time_slots.length - 1];
        const defaultStart = lastSlot?.end_time || '09:00';
        const defaultEnd = computeEndTime(defaultStart, 120);
        form.setFieldValue('time_slots', [
            ...form.values.time_slots,
            {time: defaultStart, end_time: defaultEnd, label: ''},
        ]);
    };

    const handleRemoveTime = (index: number) => {
        const updated = form.values.time_slots.filter((_, i) => i !== index);
        form.setFieldValue('time_slots', updated.length > 0 ? updated : [{time: '', end_time: '', label: ''}]);
    };

    const handleSlotChange = (index: number, field: keyof TimeSlotFormValue, value: string) => {
        const updated = [...form.values.time_slots];
        updated[index] = {...updated[index], [field]: value};
        form.setFieldValue('time_slots', updated);
    };

    const previewDates = useMemo(
        () => computePreviewDates(form.values),
        [
            form.values.frequency, form.values.interval, form.values.days_of_week,
            form.values.range_start,
            form.values.range_type, form.values.range_until, form.values.range_count,
            form.values.monthly_pattern, form.values.days_of_month,
            form.values.day_of_week, form.values.week_position,
            form.values.yearly_month, form.values.yearly_day,
        ]
    );

    const validTimes = form.values.time_slots.filter(s => s.time.trim() !== '');
    const totalOccurrences = previewDates.length * Math.max(validTimes.length, 1);
    const exceedsLimit = totalOccurrences > MAX_PREVIEW;

    const handleSubmit = (values: RecurrenceFormValues) => {
        const filteredSlots = values.time_slots.filter(s => s.time.trim() !== '');

        const timesOfDay: RecurrenceTimeSlot[] = filteredSlots.length > 0
            ? filteredSlots.map(s => {
                const duration = computeDurationFromTimes(s.time, s.end_time);
                return {
                    time: s.time,
                    ...(s.label ? {label: s.label} : {}),
                    ...(duration ? {duration_minutes: duration} : {}),
                };
            })
            : [{time: '09:00'}];

        const range: RecurrenceRule['range'] = values.range_type === 'until'
            ? {type: 'until', until: values.range_until}
            : {type: 'count', count: values.range_count};

        if (values.range_start) {
            range.start = values.range_start;
        }

        const existingRule = event?.recurrence_rule;
        const preservedMetadata: Partial<RecurrenceRule> = {};
        if (existingRule?.excluded_occurrences && existingRule.excluded_occurrences.length > 0) {
            preservedMetadata.excluded_occurrences = existingRule.excluded_occurrences;
        }
        if (existingRule?.excluded_dates && existingRule.excluded_dates.length > 0) {
            preservedMetadata.excluded_dates = existingRule.excluded_dates;
        }
        if (existingRule?.additional_dates && existingRule.additional_dates.length > 0) {
            preservedMetadata.additional_dates = existingRule.additional_dates;
        }

        const rule: RecurrenceRule = {
            frequency: values.frequency as RecurrenceRule['frequency'],
            interval: values.interval,
            times_of_day: timesOfDay,
            range,
            default_capacity: values.default_capacity ?? null,
            ...preservedMetadata,
        };

        if (values.frequency === 'weekly') {
            rule.days_of_week = values.days_of_week;
        }

        if (values.frequency === 'monthly') {
            rule.monthly_pattern = values.monthly_pattern as RecurrenceRule['monthly_pattern'];
            if (values.monthly_pattern === 'by_day_of_month') {
                rule.days_of_month = values.days_of_month.map(d => parseInt(d)).filter(n => !isNaN(n));
            } else {
                rule.day_of_week = values.day_of_week;
                rule.week_position = parseInt(values.week_position);
            }
        }

        if (values.frequency === 'yearly') {
            rule.month = parseInt(values.yearly_month);
            rule.days_of_month = [values.yearly_day];
        }

        generateMutation.mutate({eventId, data: {recurrence_rule: rule}}, {
            onSuccess: (response) => {
                if (response.status === 'IN_PROGRESS' && response.job_uuid) {
                    onGenerationStarted(response.job_uuid, totalOccurrences);
                    onClose();
                } else if (response.status === 'FINISHED') {
                    showSuccess(t`Schedule created successfully`);
                    queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]});
                    queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY, eventId]});
                    onClose();
                } else {
                    showError(t`Failed to create schedule. Please try again.`);
                }
            },
            onError: (error: any) => {
                const errors = error?.response?.data?.errors;
                if (error?.response?.status === 422 && errors) {
                    const firstError = Object.values(errors).flat()[0] as string | undefined;
                    showError(firstError || t`Please check the provided information is correct`);
                    errorHandler(form, error);
                } else {
                    showError(error?.response?.data?.message || t`Failed to create schedule`);
                }
            },
        });
    };

    const handleClose = () => {
        if (!form.isDirty()) {
            onClose();
            return;
        }

        modals.openConfirmModal({
            title: t`Discard changes?`,
            children: (
                <Text size="sm">
                    {t`You have unsaved changes. Are you sure you want to discard them?`}
                </Text>
            ),
            labels: {confirm: t`Discard`, cancel: t`Keep editing`},
            confirmProps: {color: 'red'},
            zIndex: 400,
            onConfirm: onClose,
        });
    };

    const handleKeyDown = (keyboardEvent: React.KeyboardEvent) => {
        if ((keyboardEvent.metaKey || keyboardEvent.ctrlKey) && keyboardEvent.key === 'Enter') {
            keyboardEvent.preventDefault();
            (document.getElementById(formId) as HTMLFormElement | null)?.requestSubmit();
        }
    };

    const cadenceUnit = frequencyUnitLabel(form.values.frequency, form.values.interval);
    const cadenceSummary = form.values.interval === 1
        ? t`Every ${cadenceUnit}`
        : t`Every ${form.values.interval} ${cadenceUnit}`;

    const ruleSentence = useMemo((): RuleSentence | null => {
        if (previewDates.length === 0) return null;
        const values = form.values;

        let onPart = '';
        if (values.frequency === 'weekly') {
            const dayList = DAYS_OF_WEEK
                .filter(day => values.days_of_week.includes(day.value))
                .map(day => day.label)
                .join(', ');
            if (dayList) onPart = t`on ${dayList}`;
        } else if (values.frequency === 'monthly') {
            if (values.monthly_pattern === 'by_day_of_month') {
                const dayList = values.days_of_month
                    .map(Number)
                    .filter(n => !isNaN(n))
                    .sort((a, b) => a - b)
                    .join(', ');
                if (dayList) onPart = t`on ${dayList}`;
            } else {
                const position = WEEK_POSITIONS.find(p => p.value === values.week_position)?.label ?? '';
                const day = DAYS_OF_WEEK.find(d => d.value === values.day_of_week)?.full ?? '';
                onPart = t`on the ${position} ${day}`;
            }
        } else if (values.frequency === 'yearly') {
            const month = MONTHS.find(m => m.value === values.yearly_month)?.label ?? '';
            const day = values.yearly_day;
            onPart = t`on ${month} ${day}`;
        }

        const times = validTimes
            .map(s => s.end_time ? `${s.time}–${s.end_time}` : s.time)
            .join(` ${t`and`} `);

        const startDate = parseLocalDate(values.range_start) ?? new Date();
        const start = formatShortDate(startDate);
        const range = values.range_type === 'until' && values.range_until
            ? (() => {
                const end = formatShortDate(parseLocalDate(values.range_until)!);
                return t`from ${start} until ${end}`;
            })()
            : (() => {
                const count = values.range_count;
                return t`from ${start}, for ${count} dates`;
            })();

        return {
            bold: onPart ? `${cadenceSummary} ${onPart}` : cadenceSummary,
            rest: `, ${times}, ${range}.`,
        };
    }, [form.values, previewDates.length, validTimes, cadenceSummary]);

    const dateCount = previewDates.length;
    const timesPerDay = validTimes.length;
    const max = MAX_PREVIEW;

    const countSubtitle = timesPerDay > 1
        ? t`sessions · ${dateCount} dates × ${timesPerDay} times`
        : t`dates`;

    const ruleBox = ruleSentence && (
        <div
            aria-live="polite"
            className={classNames(classes.ruleBox, exceedsLimit && classes.ruleBoxWarning)}
        >
            {exceedsLimit ? (
                <>
                    <IconAlertTriangle size={14}/>
                    <span>{t`That's ${totalOccurrences} sessions — the maximum is ${max}. Shorten the range or frequency.`}</span>
                </>
            ) : (
                <>
                    <span className={classes.ruleDot}/>
                    <span><strong>{ruleSentence.bold}</strong>{ruleSentence.rest}</span>
                </>
            )}
        </div>
    );

    const countLine = (
        <div className={classes.countCard}>
            <span className={classNames(classes.countNumber, exceedsLimit && classes.countNumberWarning)}>
                {totalOccurrences}
            </span>
            <span className={classes.countSubtitle}>{countSubtitle}</span>
        </div>
    );

    const emptyPreview = (
        <div className={classes.previewEmpty}>
            <IconSparkles size={18}/>
            {t`Pick days to see your dates.`}
        </div>
    );

    return (
        <Drawer
            opened
            onClose={handleClose}
            position="right"
            size={900}
            withCloseButton={false}
            closeOnClickOutside={false}
            overlayProps={{
                opacity: 0.55,
                blur: 3,
            }}
            aria-labelledby={headingId}
            classNames={{
                content: classes.content,
                body: classes.body,
            }}
        >
            <div className={classes.header}>
                <div className={classes.headerText} id={headingId}>
                    <h2 className={classes.headerTitle}>
                        {hasExistingRule ? t`Edit schedule` : t`Set up your schedule`}
                    </h2>
                </div>
                <div className={classes.headerActions}>
                    <span className={classes.escHint}>{t`Esc to close`}</span>
                    <CloseButton
                        aria-label={t`Close`}
                        onClick={handleClose}
                        size="lg"
                        radius="xl"
                    />
                </div>
            </div>

            <div className={classes.main} onKeyDown={handleKeyDown}>
                <div className={classes.formColumn} data-autofocus tabIndex={-1}>
                    <form id={formId} onSubmit={form.onSubmit(handleSubmit)}>
                        <div className={classes.block}>
                            <div className={classes.blockHeader}>
                                <span className={classes.blockLabel}>{t`Repeats`}</span>
                                <SegmentedControl
                                    size="xs"
                                    value={form.values.frequency}
                                    onChange={(value) => form.setFieldValue('frequency', value)}
                                    data={FREQUENCIES}
                                />
                            </div>

                            <div className={classes.sentenceRow}>
                                <span className={classes.sentenceWord}>{t`Every`}</span>
                                <IntervalStepper
                                    value={form.values.interval}
                                    onChange={(value) => form.setFieldValue('interval', value)}
                                />
                                <span className={classes.sentenceWord}>{cadenceUnit}</span>

                                {form.values.frequency === 'weekly' && (
                                    <>
                                        <span className={classes.sentenceWord}>{t`on`}</span>
                                        <DayDots
                                            selected={form.values.days_of_week}
                                            onToggle={toggleDayOfWeek}
                                        />
                                    </>
                                )}

                                {form.values.frequency === 'monthly' && (
                                    <>
                                        <span className={classes.sentenceWord}>{t`on`}</span>
                                        <SegmentedControl
                                            size="xs"
                                            value={form.values.monthly_pattern}
                                            onChange={(value) => form.setFieldValue('monthly_pattern', value)}
                                            data={[
                                                {label: t`Days of the month`, value: 'by_day_of_month'},
                                                {label: t`A weekday pattern`, value: 'by_day_of_week'},
                                            ]}
                                        />
                                    </>
                                )}

                                {form.values.frequency === 'yearly' && (
                                    <>
                                        <span className={classes.sentenceWord}>{t`on`}</span>
                                        <Select
                                            aria-label={t`Month`}
                                            data={MONTHS}
                                            className={classes.inlineSelect}
                                            {...form.getInputProps('yearly_month')}
                                        />
                                        <NumberInput
                                            aria-label={t`Day of Month`}
                                            min={1}
                                            max={31}
                                            hideControls
                                            className={classes.inlineDayInput}
                                            {...form.getInputProps('yearly_day')}
                                        />
                                    </>
                                )}
                            </div>

                            {form.values.frequency === 'weekly' && form.errors.days_of_week && (
                                <Text c="red" size="xs" mt={6}>{form.errors.days_of_week}</Text>
                            )}

                            {form.values.frequency === 'monthly' && form.values.monthly_pattern === 'by_day_of_month' && (
                                <div className={classes.patternContent}>
                                    <MonthDayGrid
                                        selected={form.values.days_of_month}
                                        onToggle={toggleDayOfMonth}
                                    />
                                    {form.errors.days_of_month
                                        ? <Text c="red" size="xs" mt={6}>{form.errors.days_of_month}</Text>
                                        : <p className={classes.fieldHint}>{t`Tap dates like a calendar.`}</p>}
                                </div>
                            )}

                            {form.values.frequency === 'monthly' && form.values.monthly_pattern === 'by_day_of_week' && (
                                <div className={classNames(classes.patternContent, classes.sentenceRow)}>
                                    <Select
                                        aria-label={t`Position`}
                                        data={WEEK_POSITIONS}
                                        className={classes.inlineSelect}
                                        {...form.getInputProps('week_position')}
                                    />
                                    <Select
                                        aria-label={t`Day`}
                                        data={DAYS_OF_WEEK.map(day => ({value: day.value, label: day.full}))}
                                        className={classes.inlineSelect}
                                        {...form.getInputProps('day_of_week')}
                                    />
                                    <span className={classes.sentenceWord}>{t`of every month`}</span>
                                </div>
                            )}
                        </div>

                        <div className={classes.block}>
                            <div className={classes.blockHeader}>
                                <span className={classes.blockLabel}>{t`Times`}</span>
                                <Button
                                    variant="subtle"
                                    size="compact-sm"
                                    leftSection={<IconPlus size={14}/>}
                                    onClick={handleAddTime}
                                >
                                    {t`Add another time`}
                                </Button>
                            </div>

                            <Stack gap="sm">
                                {form.values.time_slots.map((slot, index) => (
                                    <div key={index} className={classes.timeSlot}>
                                        <TextInput
                                            aria-label={t`Start time`}
                                            type="time"
                                            value={slot.time}
                                            onChange={(e) => handleSlotChange(index, 'time', e.currentTarget.value)}
                                            placeholder="09:00"
                                            className={classes.slotStart}
                                            classNames={{input: classes.timeInput}}
                                        />
                                        <span className={classNames(classes.timeSeparator, classes.slotSep)}>{t`to`}</span>
                                        <div className={classNames(classes.endTimeWrap, classes.slotEnd)}>
                                            <TextInput
                                                aria-label={t`End time`}
                                                type="time"
                                                value={slot.end_time}
                                                onChange={(e) => handleSlotChange(index, 'end_time', e.currentTarget.value)}
                                                placeholder="11:00"
                                                classNames={{input: classes.timeInput}}
                                            />
                                            {slotWrapsMidnight(slot) && (
                                                <span className={classes.plusDayPill}>{t`+1 day`}</span>
                                            )}
                                        </div>
                                        <TextInput
                                            aria-label={t`Label`}
                                            value={slot.label}
                                            onChange={(e) => handleSlotChange(index, 'label', e.currentTarget.value)}
                                            placeholder={t`Label — e.g. Morning session`}
                                            className={classes.slotLabel}
                                        />
                                        {form.values.time_slots.length > 1 ? (
                                            <ActionIcon
                                                aria-label={t`Remove time`}
                                                className={classes.slotRemove}
                                                variant="subtle"
                                                color="gray"
                                                onClick={() => handleRemoveTime(index)}
                                            >
                                                <IconX size={16}/>
                                            </ActionIcon>
                                        ) : <span className={classes.slotRemove}/>}
                                    </div>
                                ))}
                            </Stack>
                            {form.errors.time_slots && (
                                <Text c="red" size="xs" mt={6}>{form.errors.time_slots}</Text>
                            )}
                            <p className={classes.fieldHint}>
                                {t`Add multiple times if you run several sessions per day.`}
                            </p>
                        </div>

                        <div className={classes.block}>
                            <div className={classes.blockHeader}>
                                <span className={classes.blockLabel}>{t`Runs`}</span>
                                <SegmentedControl
                                    size="xs"
                                    value={form.values.range_type}
                                    onChange={(value) => form.setFieldValue('range_type', value)}
                                    data={[
                                        {label: t`Until a date`, value: 'until'},
                                        {label: t`For a number of dates`, value: 'count'},
                                    ]}
                                />
                            </div>

                            <div className={classes.sentenceRow}>
                                <span className={classes.sentenceWord}>{t`From`}</span>
                                <TextInput
                                    type="date"
                                    aria-label={t`Schedule starts on`}
                                    className={classes.inlineDateInput}
                                    {...form.getInputProps('range_start')}
                                />
                                {form.values.range_type === 'until' ? (
                                    <>
                                        <span className={classes.sentenceWord}>{t`until`}</span>
                                        <TextInput
                                            type="date"
                                            aria-label={t`Schedule ends on`}
                                            className={classes.inlineDateInput}
                                            {...form.getInputProps('range_until')}
                                        />
                                    </>
                                ) : (
                                    <>
                                        <span className={classes.sentenceWord}>{t`for`}</span>
                                        <NumberInput
                                            aria-label={t`Number of dates to create`}
                                            min={1}
                                            max={1200}
                                            className={classes.inlineCountInput}
                                            {...form.getInputProps('range_count')}
                                        />
                                        <span className={classes.sentenceWord}>{t`dates`}</span>
                                    </>
                                )}
                            </div>
                            <p className={classes.fieldHint}>
                                {t`Leave “from” as today to start right away.`}
                            </p>
                        </div>

                        <div className={classes.block}>
                            <div className={classes.blockHeader}>
                                <span className={classes.blockLabel}>{t`Capacity`}</span>
                                <NumberInput
                                    aria-label={t`Default capacity per date`}
                                    placeholder={t`Unlimited`}
                                    min={0}
                                    allowNegative={false}
                                    className={classes.capacityInput}
                                    {...form.getInputProps('default_capacity')}
                                />
                            </div>
                            <p className={classes.fieldHint}>
                                {t`You can override this for individual dates later.`}
                            </p>
                        </div>
                    </form>

                    <div className={classes.previewBelowForm}>
                        {previewDates.length === 0 ? emptyPreview : (
                            <>
                                {countLine}
                                {ruleBox}
                            </>
                        )}
                    </div>
                </div>

                <aside className={classes.previewColumn}>
                    <div className={classes.previewEyebrow}>{t`Schedule preview`}</div>
                    {previewDates.length === 0 ? emptyPreview : (
                        <>
                            {countLine}
                            <MiniCalendar previewDates={previewDates} rangeStart={form.values.range_start}/>
                            {ruleBox}
                            <p className={classes.previewFootnote}>
                                {t`Updates as you build the rule. Flip through months to sanity-check dates.`}
                            </p>
                        </>
                    )}
                </aside>
            </div>

            <div className={classes.footer}>
                <span className={classes.footerHint}>
                    {hasExistingRule
                        ? t`Any dates you've manually customized will be kept.`
                        : (totalOccurrences > 0
                            ? t`We'll create all ${totalOccurrences} sessions — you can edit or remove any date afterwards`
                            : t`Tell us how often your event repeats and we'll create all the dates for you.`)}
                </span>
                <div className={classes.footerActions}>
                    <Button variant="default" onClick={handleClose}>
                        {t`Cancel`}
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        loading={generateMutation.isPending}
                        disabled={exceedsLimit}
                    >
                        {hasExistingRule ? t`Save schedule` : t`Create schedule`}
                    </Button>
                </div>
            </div>
        </Drawer>
    );
};
