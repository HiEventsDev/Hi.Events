import {t, plural} from "@lingui/macro";
import {
    ActionIcon,
    Button,
    Checkbox,
    Chip,
    Group,
    NumberInput,
    Radio,
    Select,
    Stack,
    Text,
    TextInput,
} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {
    IconAlertTriangle,
    IconBulb,
    IconCalendarEvent,
    IconCalendarStats,
    IconCheck,
    IconClock,
    IconHash,
    IconPlus,
    IconRepeat,
    IconSparkles,
    IconTag,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import {Modal} from "../../../../common/Modal";
import {InputGroup} from "../../../../common/InputGroup";
import {ModalIntro} from "../../../../common/ModalIntro";

import {GenericModalProps, RecurrenceRule, RecurrenceTimeSlot} from "../../../../../types.ts";
import {useGenerateOccurrences} from "../../../../../mutations/useGenerateOccurrences.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect, useMemo} from "react";
import classes from './RecurrenceScheduleModal.module.scss';

const MAX_PREVIEW = 1200;

const DAYS_OF_WEEK = [
    {value: 'monday', label: t`Mon`},
    {value: 'tuesday', label: t`Tue`},
    {value: 'wednesday', label: t`Wed`},
    {value: 'thursday', label: t`Thu`},
    {value: 'friday', label: t`Fri`},
    {value: 'saturday', label: t`Sat`},
    {value: 'sunday', label: t`Sun`},
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

const computePreviewDates = (values: RecurrenceFormValues): Date[] => {
    const dates: Date[] = [];
    const today = new Date();
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

const formatPreviewDate = (date: Date): string => {
    return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
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

export const RecurrenceScheduleModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const generateMutation = useGenerateOccurrences();
    const errorHandler = useFormErrorResponseHandler();

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
    });

    useEffect(() => {
        if (event?.recurrence_rule) {
            const rule = event.recurrence_rule;
            form.setValues({
                frequency: rule.frequency || 'weekly',
                interval: rule.interval || 1,
                days_of_week: rule.days_of_week || [],
                time_slots: parseTimeSlotsFromRule(rule),
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
        }
    }, [event]);

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

        const rule: RecurrenceRule = {
            frequency: values.frequency as RecurrenceRule['frequency'],
            interval: values.interval,
            times_of_day: timesOfDay,
            range: values.range_type === 'until'
                ? {type: 'until', until: values.range_until}
                : {type: 'count', count: values.range_count},
            default_capacity: values.default_capacity || null,
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
            onSuccess: () => {
                showSuccess(t`Schedule created successfully`);
                onClose();
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

    const daysOfMonthOptions = Array.from({length: 31}, (_, i) => String(i + 1));

    const previewSummary = useMemo(() => {
        if (previewDates.length === 0) return null;

        const maxShow = 8;
        const shown = previewDates.slice(0, maxShow).map(formatPreviewDate);
        const remaining = previewDates.length - maxShow;

        if (validTimes.length > 1) {
            return {
                label: t`${totalOccurrences} dates across ${previewDates.length} days (${plural(validTimes.length, {one: "# session", other: "# sessions"})} per day)`,
                dates: shown,
                remaining: remaining > 0 ? remaining : 0,
            };
        }

        return {
            label: t`${totalOccurrences} dates`,
            dates: shown,
            remaining: remaining > 0 ? remaining : 0,
        };
    }, [previewDates, validTimes.length, totalOccurrences]);

    return (
        <Modal opened onClose={onClose} heading={null} size="lg">
            {hasExistingRule ? (
                <div className={classes.returningBanner}>
                    <div className={classes.returningIcon}>
                        <IconCheck size={16}/>
                    </div>
                    <span className={classes.returningText}>
                        {t`Any dates you've manually customized will be kept.`}
                    </span>
                </div>
            ) : (
                <ModalIntro
                    icon={<IconCalendarEvent size={26}/>}
                    title={t`Set Up Your Schedule`}
                    subtitle={t`Tell us how often your event repeats and we'll create all the dates for you.`}
                />
            )}

            <form onSubmit={form.onSubmit(handleSubmit)}>
                <div className={classes.section}>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionIcon}><IconRepeat size={16}/></div>
                        <span className={classes.sectionTitle}>{t`How often?`}</span>
                    </div>

                    <InputGroup>
                        <Select
                            label={t`Frequency`}
                            data={FREQUENCIES}
                            {...form.getInputProps('frequency')}
                        />
                        <NumberInput
                            label={t`Repeat every`}
                            min={1}
                            rightSection={
                                <Text size="xs" c="dimmed" pr={4}>
                                    {frequencyUnitLabel(form.values.frequency, form.values.interval)}
                                </Text>
                            }
                            rightSectionWidth={60}
                            {...form.getInputProps('interval')}
                        />
                    </InputGroup>

                    {form.values.frequency === 'weekly' && (
                        <Checkbox.Group
                            label={t`Days of Week`}
                            {...form.getInputProps('days_of_week')}
                            mt="sm"
                        >
                            <Group mt="xs">
                                {DAYS_OF_WEEK.map(day => (
                                    <Checkbox
                                        key={day.value}
                                        value={day.value}
                                        label={day.label}
                                    />
                                ))}
                            </Group>
                        </Checkbox.Group>
                    )}

                    {form.values.frequency === 'monthly' && (
                        <Stack gap="sm" mt="sm">
                            <Radio.Group
                                label={t`Monthly Pattern`}
                                {...form.getInputProps('monthly_pattern')}
                            >
                                <Stack mt="xs" gap="xs">
                                    <Radio value="by_day_of_month" label={t`By day of month`}/>
                                    <Radio value="by_day_of_week" label={t`By day of week`}/>
                                </Stack>
                            </Radio.Group>

                            {form.values.monthly_pattern === 'by_day_of_month' && (
                                <div>
                                    <Text size="sm" fw={500} mb="xs">{t`Days of Month`}</Text>
                                    <Chip.Group
                                        multiple
                                        {...form.getInputProps('days_of_month')}
                                    >
                                        <Group gap={4}>
                                            {daysOfMonthOptions.map(day => (
                                                <Chip key={day} value={day} size="xs" variant="outline">
                                                    {day}
                                                </Chip>
                                            ))}
                                        </Group>
                                    </Chip.Group>
                                </div>
                            )}

                            {form.values.monthly_pattern === 'by_day_of_week' && (
                                <InputGroup>
                                    <Select
                                        label={t`Position`}
                                        data={WEEK_POSITIONS}
                                        {...form.getInputProps('week_position')}
                                    />
                                    <Select
                                        label={t`Day`}
                                        data={DAYS_OF_WEEK}
                                        {...form.getInputProps('day_of_week')}
                                    />
                                </InputGroup>
                            )}
                        </Stack>
                    )}

                    {form.values.frequency === 'yearly' && (
                        <InputGroup>
                            <Select
                                label={t`Month`}
                                data={MONTHS}
                                {...form.getInputProps('yearly_month')}
                            />
                            <NumberInput
                                label={t`Day of Month`}
                                min={1}
                                max={31}
                                {...form.getInputProps('yearly_day')}
                            />
                        </InputGroup>
                    )}
                </div>

                <div className={classes.section}>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionIcon}><IconClock size={16}/></div>
                        <span className={classes.sectionTitle}>{t`What time?`}</span>
                    </div>

                    <Stack gap="sm">
                        {form.values.time_slots.map((slot, index) => (
                            <div key={index} className={classes.timeSlot}>
                                <div className={classes.timeSlotTimes}>
                                    <TextInput
                                        label={index === 0 ? t`Start` : undefined}
                                        type="time"
                                        value={slot.time}
                                        onChange={(e) => handleSlotChange(index, 'time', e.currentTarget.value)}
                                        placeholder="09:00"
                                    />
                                    <span className={`${classes.timeSeparator} ${index === 0 ? classes.timeSeparatorLabeled : ''}`}>{t`to`}</span>
                                    <TextInput
                                        label={index === 0 ? t`End` : undefined}
                                        type="time"
                                        value={slot.end_time}
                                        onChange={(e) => handleSlotChange(index, 'end_time', e.currentTarget.value)}
                                        placeholder="11:00"
                                    />
                                    <TextInput
                                        label={index === 0 ? t`Label` : undefined}
                                        value={slot.label}
                                        onChange={(e) => handleSlotChange(index, 'label', e.currentTarget.value)}
                                        placeholder={t`e.g. Morning Session`}
                                        leftSection={<IconTag size={14}/>}
                                    />
                                    {form.values.time_slots.length > 1 && (
                                        <ActionIcon
                                            className={`${classes.removeButton} ${index === 0 ? classes.removeButtonLabeled : ''}`}
                                            variant="subtle"
                                            color="gray"
                                            onClick={() => handleRemoveTime(index)}
                                            size="lg"
                                        >
                                            <IconX size={16}/>
                                        </ActionIcon>
                                    )}
                                </div>
                            </div>
                        ))}
                    </Stack>
                    <Button
                        variant="outline"
                        size="xs"
                        leftSection={<IconPlus size={14}/>}
                        onClick={handleAddTime}
                        mt="xs"
                    >
                        {t`Add another time`}
                    </Button>
                    <div className={classes.sectionTip}>
                        <IconBulb size={14} className={classes.tipIcon}/>
                        {t`Add multiple times if you run several sessions per day.`}
                    </div>
                </div>

                <div className={classes.section}>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionIcon}><IconCalendarStats size={16}/></div>
                        <span className={classes.sectionTitle}>{t`How long does the schedule run?`}</span>
                    </div>

                    <div className={classes.rangeToggle}>
                        <div
                            className={classes.rangeOption}
                            data-active={form.values.range_type === 'until'}
                            onClick={() => form.setFieldValue('range_type', 'until')}
                        >
                            <div className={classes.rangeOptionIcon}>
                                <IconCalendarEvent size={18}/>
                            </div>
                            <div>
                                <div className={classes.rangeOptionLabel}>{t`End on a date`}</div>
                                <div className={classes.rangeOptionDesc}>{t`Run until a specific date`}</div>
                            </div>
                        </div>
                        <div
                            className={classes.rangeOption}
                            data-active={form.values.range_type === 'count'}
                            onClick={() => form.setFieldValue('range_type', 'count')}
                        >
                            <div className={classes.rangeOptionIcon}>
                                <IconHash size={18}/>
                            </div>
                            <div>
                                <div className={classes.rangeOptionLabel}>{t`Set number of dates`}</div>
                                <div className={classes.rangeOptionDesc}>{t`Create a fixed number`}</div>
                            </div>
                        </div>
                    </div>

                    {form.values.range_type === 'until' && (
                        <TextInput
                            type="date"
                            label={t`Schedule ends on`}
                            {...form.getInputProps('range_until')}
                        />
                    )}

                    {form.values.range_type === 'count' && (
                        <NumberInput
                            label={t`Number of dates to create`}
                            min={1}
                            max={1200}
                            {...form.getInputProps('range_count')}
                        />
                    )}
                </div>

                <div className={classes.section}>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionIcon}><IconUsers size={16}/></div>
                        <span className={classes.sectionTitle}>{t`Capacity`}</span>
                    </div>

                    <NumberInput
                        label={t`Default capacity per date`}
                        placeholder={t`Leave empty for unlimited`}
                        min={0}
                        allowNegative={false}
                        {...form.getInputProps('default_capacity')}
                    />
                    <div className={classes.sectionTip}>
                        <IconBulb size={14} className={classes.tipIcon}/>
                        {t`You can override this for individual dates later.`}
                    </div>
                </div>

                {previewSummary && previewSummary.dates.length > 0 && (
                    <div className={exceedsLimit ? classes.previewCardWarning : classes.previewCard}>
                        <div className={classes.previewHeader}>
                            {exceedsLimit
                                ? <IconAlertTriangle size={16} className={classes.previewIconWarning}/>
                                : <IconSparkles size={16} className={classes.previewIcon}/>
                            }
                            <span className={exceedsLimit ? classes.previewLabelWarning : classes.previewLabel}>
                                {previewSummary.label}
                            </span>
                        </div>
                        {exceedsLimit && (
                            <div className={classes.previewWarning}>
                                {t`The maximum is ${MAX_PREVIEW} dates. Please reduce the date range, frequency, or number of sessions per day.`}
                            </div>
                        )}
                        <div className={classes.previewDates}>
                            {previewSummary.dates.map((date, i) => (
                                <span key={i} className={exceedsLimit ? classes.previewChipWarning : classes.previewChip}>{date}</span>
                            ))}
                            {previewSummary.remaining > 0 && (
                                <span className={classes.previewMore}>
                                    {t`and ${previewSummary.remaining} more...`}
                                </span>
                            )}
                        </div>
                    </div>
                )}

                <Button
                    type="submit"
                    fullWidth
                    size="md"
                    mt="lg"
                    loading={generateMutation.isPending}
                    disabled={exceedsLimit}
                >
                    {hasExistingRule ? t`Save Schedule` : t`Create Schedule`}
                </Button>
            </form>
        </Modal>
    );
};
