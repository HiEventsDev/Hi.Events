import {Button, Select, Switch, TextInput} from "@mantine/core";
import {DateTimePicker} from "@mantine/dates";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import dayjs from "dayjs";
import {useEffect, useRef, useState} from "react";
import {useNavigate} from "react-router";
import classes from "./Welcome.module.scss";
import {EventType, IdParam, Organizer, User} from "../../../types.ts";
import {useCreateEvent} from "../../../mutations/useCreateEvent.ts";
import {useCreateOrganizer} from "../../../mutations/useCreateOrganizer.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {getEventCategories} from "../../../constants/eventCategories.ts";
import {organizerCurrencyOptions} from "../../forms/OrganizerForm";
import {timezones} from "../../../../data/timezones.ts";
import {getDateTimePickerFormat, getTimePickerFormat} from "../../../utilites/dates.ts";
import {getClientLocale, toIntlLocale} from "../../../locales.ts";
import {AnalyticsEvents, trackEvent} from "../../../utilites/analytics.ts";
import {Callout} from "../../common/Callout";
import {TicketPreview} from "../../layouts/AuthLayout/AuthShell.tsx";

interface FirstEventFormValues {
    title: string;
    is_recurring: boolean;
    start_date: string | Date | null;
    category: string | null;
    organizer_name: string;
    currency: string;
    timezone: string;
}

interface FirstEventStepProps {
    user: User;
    organizer: Organizer | undefined;
    stepLabel: string;
    onPreviewChange: (preview: Partial<TicketPreview>) => void;
}

const ORGANIZER_FIELD_BY_API_FIELD: Record<string, keyof FirstEventFormValues> = {
    name: 'organizer_name',
    currency: 'currency',
    timezone: 'timezone',
};

const formatPreviewDate = (value: string | Date | null): string => {
    if (!value || !dayjs(value).isValid()) {
        return '';
    }

    return new Intl.DateTimeFormat(toIntlLocale(getClientLocale()), {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(dayjs(value).toDate());
};

export const FirstEventStep = ({user, organizer, stepLabel, onPreviewChange}: FirstEventStepProps) => {
    const navigate = useNavigate();
    const organizerMutation = useCreateOrganizer();
    const eventMutation = useCreateEvent();
    const handleFormError = useFormErrorResponseHandler();
    const {data: account, isFetched: isAccountFetched} = useGetAccount();
    const [isRegionalOpen, setIsRegionalOpen] = useState(false);
    const [isSkipping, setIsSkipping] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const titleRef = useRef<HTMLInputElement>(null);
    const needsOrganizer = !organizer;

    const form = useForm<FirstEventFormValues>({
        initialValues: {
            title: '',
            is_recurring: false,
            start_date: dayjs().add(1, 'day').hour(19).minute(0).second(0).toDate(),
            category: null,
            organizer_name: '',
            currency: '',
            timezone: user.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
        },
        validate: {
            title: (value) => value.trim() ? null : t`Event name is required`,
            start_date: (value, values) => values.is_recurring || value ? null : t`Start date is required`,
            organizer_name: (value) => !needsOrganizer || value.trim() ? null : t`Organizer name is required`,
            currency: (value) => !needsOrganizer || value ? null : t`Currency is required`,
            timezone: (value) => !needsOrganizer || value ? null : t`Timezone is required`,
        },
    });

    useEffect(() => {
        titleRef.current?.focus({preventScroll: true});
    }, []);

    useEffect(() => {
        if (!isAccountFetched) {
            return;
        }
        form.setFieldValue('organizer_name', account?.name ?? '');
        form.setFieldValue('currency', account?.currency_code ?? '');
    }, [isAccountFetched]);

    const {title, start_date: startDate, is_recurring: isRecurring, organizer_name: organizerName} = form.values;
    const previewOrganizer = organizer?.name ?? organizerName;

    useEffect(() => {
        onPreviewChange({
            title: title.trim(),
            meta: [isRecurring ? '' : formatPreviewDate(startDate), previewOrganizer.trim()]
                .filter(Boolean)
                .join(' · '),
        });
    }, [title, startDate, isRecurring, previewOrganizer]);

    const isPending = organizerMutation.isPending || eventMutation.isPending;

    const createOrganizer = async (values: FirstEventFormValues): Promise<IdParam | undefined> => {
        try {
            const {data} = await organizerMutation.mutateAsync({
                organizerData: {
                    name: values.organizer_name.trim(),
                    email: user.email,
                    currency: values.currency,
                    timezone: values.timezone,
                },
            });
            trackEvent(AnalyticsEvents.ORGANIZER_CREATED);

            return data.id;
        } catch (error: any) {
            const apiErrors: Record<string, string> = error?.response?.data?.errors ?? {};
            const fieldErrors = Object.entries(apiErrors)
                .filter(([field]) => field in ORGANIZER_FIELD_BY_API_FIELD)
                .map(([field, message]) => [ORGANIZER_FIELD_BY_API_FIELD[field], message]);

            if (fieldErrors.length > 0) {
                form.setErrors(Object.fromEntries(fieldErrors));
                if (fieldErrors.some(([field]) => field !== 'organizer_name')) {
                    setIsRegionalOpen(true);
                }
            } else {
                setFormError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`);
            }

            return undefined;
        }
    };

    const createEvent = async (values: FirstEventFormValues) => {
        setFormError(null);

        const organizerId = organizer?.id ?? await createOrganizer(values);
        if (!organizerId) {
            return;
        }

        try {
            const result = await eventMutation.mutateAsync({
                eventData: {
                    title: values.title.trim(),
                    type: values.is_recurring ? EventType.RECURRING : EventType.SINGLE,
                    category: values.category ?? undefined,
                    organizer_id: organizerId,
                    start_date: !values.is_recurring && values.start_date
                        ? dayjs(values.start_date).toISOString()
                        : undefined,
                },
            });
            trackEvent(AnalyticsEvents.FIRST_EVENT_CREATED);
            navigate(`/manage/event/${result.data.id}/dashboard?new_event=true`);
        } catch (error) {
            handleFormError(form, error);
        }
    };

    const skip = async () => {
        if (!needsOrganizer) {
            navigate('/manage/events');
            return;
        }

        const organizerFields = ['organizer_name', 'currency', 'timezone'] as const;
        if (organizerFields.map((field) => form.validateField(field).hasError).some(Boolean)) {
            return;
        }

        setFormError(null);
        setIsSkipping(true);
        const organizerId = await createOrganizer(form.values);
        setIsSkipping(false);
        if (organizerId) {
            navigate(`/manage/organizer/${organizerId}`);
        }
    };

    return (
        <>
            <header className={classes.header}>
                <p className={classes.step}>{stepLabel}</p>
                <h2>{t`Create your first event`}</h2>
                <p>{t`You can change all of this later.`}</p>
            </header>

            {formError && (
                <Callout variant="danger" className={classes.error} onDismiss={() => setFormError(null)}>
                    {formError}
                </Callout>
            )}

            <form className={classes.form} onSubmit={form.onSubmit(createEvent)}>
                <TextInput
                    {...form.getInputProps('title')}
                    ref={titleRef}
                    label={t`Event name`}
                    placeholder={t`Summer Music Festival 2025`}
                    maxLength={150}
                    required
                    disabled={isPending}
                />

                {!isRecurring && (
                    <DateTimePicker
                        {...form.getInputProps('start_date')}
                        label={t`Start date & time`}
                        valueFormat={getDateTimePickerFormat()}
                        dropdownType="modal"
                        timePickerProps={{format: getTimePickerFormat(), withDropdown: true}}
                        minDate={new Date()}
                        required
                        disabled={isPending}
                    />
                )}

                <Switch
                    {...form.getInputProps('is_recurring', {type: 'checkbox'})}
                    label={t`This is a recurring event`}
                    description={isRecurring
                        ? t`After your event is created, you can choose how often it repeats from the dashboard.`
                        : t`It happens on more than one date`}
                    disabled={isPending}
                />

                <Select
                    {...form.getInputProps('category')}
                    label={t`Category`}
                    placeholder={t`Optional`}
                    data={getEventCategories().map((category) => ({value: category.id, label: category.name}))}
                    searchable
                    clearable
                    disabled={isPending}
                />

                {needsOrganizer && (
                    <>
                        <TextInput
                            {...form.getInputProps('organizer_name')}
                            label={t`Organizer name`}
                            description={t`Shown on your event page`}
                            autoComplete="organization"
                            required
                            disabled={isPending}
                        />

                        {isRegionalOpen ? (
                            <div className={classes.regionalFields}>
                                <Select
                                    {...form.getInputProps('currency')}
                                    label={t`Currency`}
                                    data={organizerCurrencyOptions}
                                    searchable
                                    required
                                    disabled={isPending}
                                />
                                <Select
                                    {...form.getInputProps('timezone')}
                                    label={t`Timezone`}
                                    data={timezones}
                                    searchable
                                    required
                                    disabled={isPending}
                                />
                            </div>
                        ) : (
                            <div className={classes.regional}>
                                <span>{[form.values.currency, form.values.timezone].filter(Boolean).join(' · ')}</span>
                                <button
                                    type="button"
                                    className={classes.linkButton}
                                    onClick={() => setIsRegionalOpen(true)}
                                    data-testid="welcome-change-regional-button"
                                >
                                    {t`Change currency or timezone`}
                                </button>
                            </div>
                        )}
                    </>
                )}

                <Button type="submit" color="secondary.5" fullWidth disabled={isPending || isSkipping}>
                    {isPending && !isSkipping ? t`Creating Event...` : t`Create event`}
                </Button>
            </form>

            <div className={classes.links}>
                <button
                    type="button"
                    className={classes.linkButton}
                    onClick={skip}
                    disabled={isPending || isSkipping}
                    data-testid="welcome-skip-button"
                >
                    {t`Skip for now`}
                </button>
            </div>
        </>
    );
};
