import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useNavigate} from "react-router";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {EventType, GenericModalProps, IdParam, Organizer} from "../../../types.ts";
import {useEffect, useMemo, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {Button, Kbd, Modal, Text, TextInput, UnstyledButton} from "@mantine/core";
import {useForm} from "@mantine/form";
import {getHotkeyHandler, useMediaQuery, useWindowEvent} from "@mantine/hooks";
import {useCreateEvent} from "../../../mutations/useCreateEvent.ts";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {IconArrowLeft, IconCalendarEvent, IconRepeat, IconWorld, IconX} from "@tabler/icons-react";
import classes from "./CreateEventModal.module.scss";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";
import dayjs from "dayjs";
import {getEventCategories} from "../../../constants/eventCategories.ts";
import {currencies} from "../../../../data/currencies.ts";
import {timezones} from "../../../../data/timezones.ts";
import {htmlToText} from "../../../utilites/helpers.ts";
import {Editor} from "../../common/Editor";
import {ChipSelect, ChipSelectOption} from "./ChipSelect.tsx";
import {DateTimeChip} from "./DateTimeChip.tsx";
import {PropertyChip} from "./PropertyChip.tsx";
import {
    currentDateTimeIn,
    defaultStartDate,
    formatChipDateTime,
    formatChipEnd,
    NAIVE_DATE_TIME_FORMAT
} from "./dateTimeFormat.ts";

interface CreateEventModalProps extends GenericModalProps {
    organizerId?: IdParam;
}

interface CreateEventFormValues {
    title: string;
    type: EventType;
    start_date: string | null;
    end_date: string | null;
    organizer_id: string | null;
    timezone: string | null;
    currency: string | null;
    category: string | null;
    description: string;
}

const DESCRIPTION_MAX_LENGTH = 50000;

const timezoneOptions: ChipSelectOption[] = timezones.map((timezone) => ({
    value: timezone,
    label: timezone.replace(/_/g, ' '),
}));

const currencyOptions: ChipSelectOption[] = Object.entries(currencies).map(([name, code]) => ({
    value: code,
    label: code,
    description: name,
}));

const isMac = () => typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

export const CreateEventModal = ({onClose, organizerId}: CreateEventModalProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const navigate = useNavigate();
    const isMobile = useMediaQuery('(max-width: 600px)');
    const {data: account, isFetched: isAccountFetched} = useGetAccount();
    const organizersQuery = useGetOrganizers();
    const eventMutation = useCreateEvent();
    const [createdOrganizers, setCreatedOrganizers] = useState<Organizer[]>([]);
    const [showCreateOrganizer, setShowCreateOrganizer] = useState(false);
    const [showDescription, setShowDescription] = useState(false);

    const containerRef = useRef<HTMLDivElement>(null);
    const hasPickedStartDateRef = useRef(false);

    useWindowEvent('keydown', (event) => {
        if (event.key !== 'Escape' || event.isComposing) {
            return;
        }
        const target = event.target as HTMLElement | null;
        const dialog = containerRef.current?.closest('[role="dialog"]');
        const isInsideModal = !!target && (target === document.body || !!dialog?.contains(target));
        const isOwnedByOpenDropdown = target?.getAttribute('data-mantine-stop-propagation') === 'true';
        if (isInsideModal && !isOwnedByOpenDropdown) {
            onClose();
        }
    }, {capture: true});

    const organizers = useMemo(() => {
        const fetched = organizersQuery.data?.data ?? [];
        const extra = createdOrganizers.filter((created) => !fetched.some((organizer) => organizer.id === created.id));
        return [...fetched, ...extra];
    }, [organizersQuery.data, createdOrganizers]);

    const form = useForm<CreateEventFormValues>({
        initialValues: {
            title: '',
            type: EventType.SINGLE,
            start_date: defaultStartDate(),
            end_date: null,
            organizer_id: organizerId ? String(organizerId) : null,
            timezone: null,
            currency: null,
            category: null,
            description: '',
        },
        validate: {
            title: (value) => {
                if (!value.trim()) {
                    return t`Give your event a name`;
                }
                if (value.length > 150) {
                    return t`Event name should be less than 150 characters`;
                }
            },
            start_date: (value, values) => {
                if (values.type === EventType.SINGLE && !value) {
                    return t`Choose when your event starts`;
                }
            },
            end_date: (value, values) => {
                if (values.type === EventType.RECURRING || !value || !values.start_date) {
                    return;
                }
                if (!dayjs(value).isAfter(dayjs(values.start_date))) {
                    return t`End date must be after start date`;
                }
            },
            organizer_id: (value) => {
                if (!value) {
                    return t`Choose an organizer`;
                }
            },
        },
    });

    const setStartDate = (value: string) => {
        const {start_date: previousStart, end_date: end} = form.values;
        form.setFieldValue('start_date', value);
        if (end && previousStart) {
            const duration = dayjs(end).diff(dayjs(previousStart));
            form.setFieldValue('end_date', dayjs(value).add(duration, 'ms').format(NAIVE_DATE_TIME_FORMAT));
        }
    };

    const applyOrganizer = (organizer?: Organizer) => {
        if (!organizer) {
            return;
        }
        const timezone = organizer.timezone ?? account?.timezone ?? null;
        form.setValues({
            organizer_id: String(organizer.id),
            currency: organizer.currency ?? account?.currency_code ?? null,
            timezone,
        });
        if (!hasPickedStartDateRef.current) {
            setStartDate(defaultStartDate(timezone));
        }
    };

    useEffect(() => {
        if (!isAccountFetched) {
            return;
        }
        if (!form.values.timezone) {
            form.setFieldValue('timezone', account?.timezone ?? null);
        }
        if (!form.values.currency) {
            form.setFieldValue('currency', account?.currency_code ?? null);
        }
    }, [isAccountFetched]);

    useEffect(() => {
        if (!organizersQuery.isFetched || !isAccountFetched) {
            return;
        }
        const fetched = organizersQuery.data?.data ?? [];
        if (fetched.length === 0) {
            setShowCreateOrganizer(true);
            return;
        }
        const preselected = organizerId
            ? fetched.find((organizer) => String(organizer.id) === String(organizerId))
            : fetched.length === 1 ? fetched[0] : undefined;
        applyOrganizer(preselected);
    }, [organizersQuery.isFetched, isAccountFetched]);

    const handleCreate = (values: CreateEventFormValues) => {
        const isRecurring = values.type === EventType.RECURRING;
        const hasDescription = htmlToText(values.description).trim().length > 0;

        eventMutation.mutateAsync({
            eventData: {
                title: values.title.trim(),
                type: values.type,
                organizer_id: values.organizer_id ?? undefined,
                timezone: values.timezone ?? undefined,
                currency: values.currency ?? undefined,
                category: values.category ?? undefined,
                start_date: isRecurring ? undefined : values.start_date ?? undefined,
                end_date: isRecurring ? undefined : values.end_date ?? undefined,
                description: hasDescription ? values.description : undefined,
            },
        }).then((data) => {
            navigate(`/manage/event/${data.data.id}/dashboard?new_event=true`);
        }).catch((error) => {
            errorHandler(form, error);
        });
    };

    const submit = form.onSubmit(handleCreate);
    const isRecurring = form.values.type === EventType.RECURRING;
    const selectedOrganizer = organizers.find((organizer) => String(organizer.id) === form.values.organizer_id);

    const categoryOptions: ChipSelectOption[] = getEventCategories().map((category) => ({
        value: category.id,
        label: `${category.emoji} ${category.name}`,
    }));

    const repeatOptions: ChipSelectOption[] = [
        {value: EventType.SINGLE, label: t`Doesn't repeat`, description: t`One date`},
        {value: EventType.RECURRING, label: t`Repeats`, description: t`Schedule set up next`},
    ];

    const propertyErrors = (['start_date', 'end_date', 'timezone', 'currency', 'category', 'description'] as const)
        .map((field) => form.errors[field])
        .filter(Boolean);

    const handleOrganizerCreated = (organizer: Organizer) => {
        setCreatedOrganizers((previous) => [...previous, organizer]);
        setShowCreateOrganizer(false);
        applyOrganizer(organizer);
    };

    const header = (
        <div className={classes.header}>
            {showCreateOrganizer ? (
                <>
                    {organizers.length > 0 && (
                        <UnstyledButton
                            type="button"
                            className={classes.crumb}
                            onClick={() => setShowCreateOrganizer(false)}
                            data-testid="create-event-organizer-back"
                        >
                            <IconArrowLeft size={14}/>
                            {t`Back`}
                        </UnstyledButton>
                    )}
                    <span className={classes.headerTitle}>{t`New organizer`}</span>
                </>
            ) : (
                <>
                    {organizerId ? (
                        <span className={classes.crumbStatic}>{selectedOrganizer?.name}</span>
                    ) : (
                        <ChipSelect
                            variant="crumb"
                            data={organizers.map((organizer) => ({
                                value: String(organizer.id),
                                label: organizer.name,
                            }))}
                            value={form.values.organizer_id}
                            onChange={(value) => applyOrganizer(organizers.find((organizer) => String(organizer.id) === value))}
                            placeholder={t`Choose organizer`}
                            ariaLabel={t`Organizer`}
                            dataTestId="create-event-organizer-select"
                            invalid={!!form.errors.organizer_id}
                            action={{
                                label: t`New organizer`,
                                onSelect: () => setShowCreateOrganizer(true),
                                dataTestId: 'create-event-new-organizer',
                            }}
                        />
                    )}
                    <span className={classes.headerSeparator}>/</span>
                    <span className={classes.headerTitle}>{t`New event`}</span>
                </>
            )}
            <UnstyledButton
                type="button"
                className={classes.closeButton}
                onClick={onClose}
                aria-label={t`Close`}
            >
                <IconX size={16}/>
            </UnstyledButton>
        </div>
    );

    return (
        <Modal
            opened
            onClose={onClose}
            withCloseButton={false}
            closeOnEscape={false}
            size={580}
            padding={0}
            radius="md"
            yOffset="12vh"
            fullScreen={isMobile}
            transitionProps={{transition: 'fade', duration: 150}}
            classNames={{content: classes.content, body: classes.modalBody}}
        >
            <div ref={containerRef} className={classes.container}>
                {header}

                {showCreateOrganizer ? (
                    <div className={classes.organizerForm}>
                        <OrganizerCreateForm
                            onCancel={() => setShowCreateOrganizer(false)}
                            onSuccess={handleOrganizerCreated}
                        />
                    </div>
                ) : (
                    <form
                        className={classes.form}
                        onSubmit={submit}
                        onKeyDown={getHotkeyHandler([['mod+Enter', () => submit()]])}
                        noValidate
                    >
                        <div className={classes.body}>
                            <TextInput
                                {...form.getInputProps('title')}
                                variant="unstyled"
                                placeholder={t`Event name`}
                                aria-label={t`Event name`}
                                maxLength={150}
                                autoComplete="off"
                                data-autofocus
                                data-testid="create-event-title-input"
                                classNames={{input: classes.titleInput}}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' && !event.metaKey && !event.ctrlKey) {
                                        event.preventDefault();
                                    }
                                }}
                            />

                            {showDescription && (
                                <div className={classes.description}>
                                    <Editor
                                        editorType="inline"
                                        value={form.values.description}
                                        onChange={(value) => form.setFieldValue('description', value)}
                                        maxLength={DESCRIPTION_MAX_LENGTH}
                                        placeholder={t`What should people expect?`}
                                        ariaLabel={t`Description`}
                                        dataTestId="create-event-description-input"
                                        className={classes.descriptionEditor}
                                        autoFocus
                                    />
                                    <div className={classes.descriptionMeta}>
                                        <UnstyledButton
                                            type="button"
                                            className={classes.textButton}
                                            onClick={() => {
                                                form.setFieldValue('description', '');
                                                setShowDescription(false);
                                            }}
                                        >
                                            {t`Remove description`}
                                        </UnstyledButton>
                                    </div>
                                </div>
                            )}

                            {isRecurring && (
                                <div className={classes.recurringNote} data-testid="create-event-recurring-note">
                                    <IconRepeat size={15}/>
                                    {t`You'll set the dates and schedule after the event is created.`}
                                </div>
                            )}

                            <div className={classes.chips}>
                                {!isRecurring && (
                                    <>
                                        <DateTimeChip
                                            value={form.values.start_date}
                                            onChange={(value) => {
                                                hasPickedStartDateRef.current = true;
                                                setStartDate(value);
                                            }}
                                            label={form.values.start_date ? formatChipDateTime(form.values.start_date) : t`Start date`}
                                            empty={!form.values.start_date}
                                            invalid={!!form.errors.start_date}
                                            minDate={currentDateTimeIn(form.values.timezone)}
                                            icon={<IconCalendarEvent size={15}/>}
                                            ariaLabel={t`Start date and time`}
                                            dataTestId="create-event-start-chip"
                                        />
                                        <DateTimeChip
                                            value={form.values.end_date}
                                            onChange={(value) => form.setFieldValue('end_date', value)}
                                            label={form.values.end_date
                                                ? t`Ends ${formatChipEnd(form.values.end_date, form.values.start_date)}`
                                                : t`+ End time`}
                                            empty={!form.values.end_date}
                                            invalid={!!form.errors.end_date}
                                            minDate={form.values.start_date}
                                            seedValue={() => dayjs(form.values.start_date ?? undefined)
                                                .add(2, 'hours')
                                                .format(NAIVE_DATE_TIME_FORMAT)}
                                            onClear={form.values.end_date ? () => form.setFieldValue('end_date', null) : undefined}
                                            ariaLabel={t`End date and time`}
                                            dataTestId="create-event-end-chip"
                                        />
                                    </>
                                )}
                                <ChipSelect
                                    data={repeatOptions}
                                    value={form.values.type}
                                    onChange={(value) => form.setFieldValue('type', value as EventType)}
                                    icon={<IconRepeat size={15}/>}
                                    placeholder={t`Doesn't repeat`}
                                    ariaLabel={t`Repeat`}
                                    dataTestId="create-event-repeat-chip"
                                />
                                <ChipSelect
                                    data={timezoneOptions}
                                    value={form.values.timezone}
                                    onChange={(value) => form.setFieldValue('timezone', value)}
                                    icon={<IconWorld size={15}/>}
                                    placeholder={t`Timezone`}
                                    ariaLabel={t`Timezone`}
                                    dataTestId="create-event-timezone-chip"
                                    invalid={!!form.errors.timezone}
                                    searchable
                                />
                                <ChipSelect
                                    data={currencyOptions}
                                    value={form.values.currency}
                                    onChange={(value) => form.setFieldValue('currency', value)}
                                    placeholder={t`Currency`}
                                    ariaLabel={t`Currency`}
                                    dataTestId="create-event-currency-chip"
                                    invalid={!!form.errors.currency}
                                    searchable
                                />
                                <ChipSelect
                                    data={categoryOptions}
                                    value={form.values.category}
                                    onChange={(value) => form.setFieldValue('category', value)}
                                    onClear={() => form.setFieldValue('category', null)}
                                    placeholder={t`+ Category`}
                                    ariaLabel={t`Category`}
                                    dataTestId="create-event-category-chip"
                                    searchable
                                />
                                {!showDescription && (
                                    <PropertyChip
                                        empty
                                        onClick={() => setShowDescription(true)}
                                        data-testid="create-event-description-button"
                                    >
                                        {t`+ Description`}
                                    </PropertyChip>
                                )}
                            </div>

                            {(propertyErrors.length > 0 || form.errors.organizer_id) && (
                                <div className={classes.errors} role="alert">
                                    {[form.errors.organizer_id, ...propertyErrors].filter(Boolean).map((error) => (
                                        <Text key={String(error)} size="xs" c="red">{error}</Text>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className={classes.footer}>
                            <Text size="xs" c="dimmed" className={classes.footerHint}>
                                {t`Next, you'll add tickets and set up your event page.`}
                            </Text>
                            <Button variant="default" onClick={onClose} type="button">
                                {t`Cancel`}
                            </Button>
                            <Button
                                type="submit"
                                loading={eventMutation.isPending}
                                className={classes.submitButton}
                                data-testid="create-event-submit-button"
                                rightSection={(
                                    <span className={classes.shortcut}>
                                        <Kbd size="xs">{isMac() ? '⌘' : 'Ctrl'}</Kbd>
                                        <Kbd size="xs">↵</Kbd>
                                    </span>
                                )}
                            >
                                {t`Create event`}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </Modal>
    );
};
