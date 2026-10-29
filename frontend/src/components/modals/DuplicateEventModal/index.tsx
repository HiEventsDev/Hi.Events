import {t} from "@lingui/macro";
import {EventDuplicatePayload, EventType, GenericModalProps, IdParam} from "../../../types.ts";
import {Button, Checkbox, SimpleGrid, TextInput, UnstyledButton} from "@mantine/core";
import {Modal, ModalFooter} from "../../common/Modal";
import {useForm} from "@mantine/form";
import {useDuplicateEvent} from "../../../mutations/useDuplicateEvent.ts";
import {Editor} from "../../common/Editor";
import {InputGroup} from "../../common/InputGroup";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useNavigate} from "react-router";
import {useEffect, useRef, useState} from "react";
import dayjs from "dayjs";
import {utcToTz} from "../../../utilites/dates.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {DateTimeField} from "../../common/DateTimeField";
import {OrganizerDatesProvider} from "../../common/OrganizerDatesProvider";
import {NAIVE_DATE_TIME_FORMAT} from "../CreateEventModal/dateTimeFormat.ts";
import classes from "./DuplicateEventModal.module.scss";

interface DuplicateEventModalProps extends GenericModalProps {
    eventId: IdParam;
}

type CopyOptionKey =
    | 'duplicate_products'
    | 'duplicate_promo_codes'
    | 'duplicate_capacity_assignments'
    | 'duplicate_affiliates'
    | 'duplicate_questions'
    | 'duplicate_check_in_lists'
    | 'duplicate_occurrences'
    | 'duplicate_lineup'
    | 'duplicate_settings'
    | 'duplicate_event_cover_image'
    | 'duplicate_ticket_logo'
    | 'duplicate_email_templates'
    | 'duplicate_webhooks';

type DuplicateEventFormValues = Record<CopyOptionKey, boolean> & {
    title: string;
    start_date: string;
    end_date: string;
    description: string;
};

interface CopyOption {
    key: CopyOptionKey;
    label: string;
    description?: string;
    requiresProducts?: boolean;
}

interface CopyOptionGroup {
    title: string;
    options: CopyOption[];
}

const DEPENDS_ON_PRODUCTS: CopyOptionKey[] = ['duplicate_capacity_assignments', 'duplicate_check_in_lists'];

export const DuplicateEventModal = ({onClose, eventId}: DuplicateEventModalProps) => {
    const form = useForm<DuplicateEventFormValues>({
        initialValues: {
            title: '',
            start_date: '',
            end_date: '',
            description: '',
            duplicate_products: true,
            duplicate_promo_codes: true,
            duplicate_capacity_assignments: true,
            duplicate_affiliates: true,
            duplicate_questions: true,
            duplicate_check_in_lists: true,
            duplicate_occurrences: true,
            duplicate_lineup: true,
            duplicate_settings: true,
            duplicate_event_cover_image: true,
            duplicate_ticket_logo: true,
            duplicate_email_templates: true,
            duplicate_webhooks: true,
        },
        validate: {
            title: (value) => (value.trim() ? null : t`Give the new event a name`),
            end_date: (value, values) => {
                if (!value || !values.start_date) {
                    return null;
                }
                return dayjs(value).isBefore(dayjs(values.start_date)) ? t`End date must be after start date` : null;
            },
        },
    });
    const mutation = useDuplicateEvent();
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;
    const isRecurring = event?.type === EventType.RECURRING;
    const nav = useNavigate();
    const errorHandler = useFormErrorResponseHandler();
    const [showDescription, setShowDescription] = useState(false);
    const hasPrefilledRef = useRef(false);

    useEffect(() => {
        if (!event || hasPrefilledRef.current) {
            return;
        }
        hasPrefilledRef.current = true;
        const title = event.title;
        form.setValues({
            title: t`${title} (Copy)`,
            description: event.description ?? '',
            start_date: utcToTz(event.start_date, event.timezone) ?? '',
            end_date: utcToTz(event.end_date, event.timezone) ?? '',
        });
        form.resetDirty();
    }, [event]);

    const productsSelected = form.values.duplicate_products;
    const isUnavailable = (option: CopyOption) => !!option.requiresProducts && !productsSelected;

    const groups: CopyOptionGroup[] = [
        {
            title: t`Tickets & sales`,
            options: [
                {key: 'duplicate_products', label: t`Products`, description: t`Tickets, prices, taxes & fees, add-ons and seat map`},
                {key: 'duplicate_promo_codes', label: t`Promo codes`, description: t`Usage counts start at zero`},
                {key: 'duplicate_capacity_assignments', label: t`Capacity assignments`, requiresProducts: true},
                {key: 'duplicate_affiliates', label: t`Affiliates`, description: t`Sales totals start at zero`},
            ],
        },
        {
            title: t`Checkout & entry`,
            options: [
                {
                    key: 'duplicate_questions',
                    label: t`Questions`,
                    description: productsSelected ? t`Order and attendee questions` : t`Order questions only. Attendee questions need products.`,
                },
                {key: 'duplicate_check_in_lists', label: t`Check-in lists`, requiresProducts: true},
            ],
        },
        {
            title: t`Dates & lineup`,
            options: [
                ...(isRecurring ? [{
                    key: 'duplicate_occurrences' as const,
                    label: t`Upcoming dates`,
                    description: t`Past and cancelled dates are skipped. Sales start at zero.`,
                }] : []),
                {
                    key: 'duplicate_lineup',
                    label: t`Lineup & schedule`,
                    description: isRecurring ? t`Performers, spaces and time slots` : t`Time slots move with the new start date`,
                },
            ],
        },
        {
            title: t`Branding & emails`,
            options: [
                {key: 'duplicate_settings', label: t`Settings`, description: t`Checkout, payments, homepage design and SEO`},
                {key: 'duplicate_event_cover_image', label: t`Cover image`},
                {key: 'duplicate_ticket_logo', label: t`Ticket logo`},
                {key: 'duplicate_email_templates', label: t`Email templates`, description: t`Custom order and ticket emails for this event`},
            ],
        },
        {
            title: t`Integrations`,
            options: [
                {key: 'duplicate_webhooks', label: t`Webhooks`, description: t`Same endpoints and signing secrets`},
            ],
        },
    ];

    const allOptions = groups.flatMap(group => group.options);
    const selectableOptions = allOptions.filter(option => !isUnavailable(option));
    const selectedCount = selectableOptions.filter(option => form.values[option.key]).length;
    const totalCount = selectableOptions.length;
    const allSelected = selectedCount === totalCount;

    const setAllOptions = (value: boolean) => {
        form.setValues(Object.fromEntries(allOptions.map(option => [option.key, value])) as Partial<DuplicateEventFormValues>);
    };

    const setStartDate = (value: string) => {
        const {start_date: previousStart, end_date: end} = form.values;
        form.setFieldValue('start_date', value);
        if (end && previousStart && value) {
            const duration = dayjs(end).diff(dayjs(previousStart));
            form.setFieldValue('end_date', dayjs(value).add(duration, 'ms').format(NAIVE_DATE_TIME_FORMAT));
        }
    };

    const handleDuplicate = (values: DuplicateEventFormValues) => {
        const duplicateData: EventDuplicatePayload = {
            ...values,
            title: values.title.trim(),
            end_date: values.end_date || undefined,
            ...(values.duplicate_products ? {} : Object.fromEntries(DEPENDS_ON_PRODUCTS.map(key => [key, false]))),
        };

        mutation.mutate({eventId, duplicateData}, {
            onSuccess: ({data}) => {
                nav(`/manage/event/${data.id}`);
                showSuccess(t`Event duplicated`);
            },
            onError: (error) => {
                errorHandler(form, error);
            }
        });
    };

    return (
        <Modal
            onClose={onClose}
            heading={t`Duplicate Event`}
            opened
            size={'lg'}
            withCloseButton
        >
            <form onSubmit={form.onSubmit(handleDuplicate)}>
                <fieldset disabled={eventQuery.isLoading || mutation.isPending}>
                    <div className={classes.fields}>
                        <TextInput
                            {...form.getInputProps('title')}
                            label={t`Name`}
                            required
                            data-autofocus
                        />

                        {isRecurring ? (
                            <p className={classes.note}>
                                {t`The new event keeps this event's repeat schedule. You can change its dates after duplicating.`}
                            </p>
                        ) : (
                            <OrganizerDatesProvider firstDayOfWeek={event?.organizer?.settings?.first_day_of_week}>
                                <InputGroup>
                                    <DateTimeField
                                        {...form.getInputProps('start_date')}
                                        onChange={setStartDate}
                                        label={t`Start Date`}
                                        required
                                    />
                                    <DateTimeField
                                        {...form.getInputProps('end_date')}
                                        label={t`End Date`}
                                        defaultTime="23:59"
                                    />
                                </InputGroup>
                            </OrganizerDatesProvider>
                        )}

                        {showDescription ? (
                            <Editor
                                label={t`Description`}
                                value={form.values.description || ''}
                                onChange={(value) => form.setFieldValue('description', value)}
                                error={form.errors?.description as string}
                            />
                        ) : (
                            <UnstyledButton
                                type="button"
                                className={classes.textButton}
                                onClick={() => setShowDescription(true)}
                                data-testid="duplicate-event-edit-description"
                            >
                                {t`Edit description`}
                            </UnstyledButton>
                        )}
                    </div>

                    <div className={classes.optionsHeader}>
                        <h3 className={classes.optionsTitle}>{t`What to copy`}</h3>
                        <div className={classes.optionsMeta}>
                            <span>{t`${selectedCount} of ${totalCount}`}</span>
                            <UnstyledButton
                                type="button"
                                className={classes.textButton}
                                onClick={() => setAllOptions(!allSelected)}
                                data-testid="duplicate-event-toggle-all"
                            >
                                {allSelected ? t`Clear all` : t`Select all`}
                            </UnstyledButton>
                        </div>
                    </div>

                    <div className={classes.groups}>
                        {groups.map(group => (
                            <section key={group.title} className={classes.group}>
                                <h4 className={classes.groupTitle}>{group.title}</h4>
                                <SimpleGrid cols={{base: 1, sm: 2}} spacing="md" verticalSpacing="sm">
                                    {group.options.map(option => {
                                        const unavailable = isUnavailable(option);
                                        return (
                                            <Checkbox
                                                key={option.key}
                                                className={classes.option}
                                                checked={form.values[option.key] && !unavailable}
                                                disabled={unavailable}
                                                onChange={(e) => form.setFieldValue(option.key, e.currentTarget.checked)}
                                                label={option.label}
                                                description={unavailable ? t`Needs products` : option.description}
                                            />
                                        );
                                    })}
                                </SimpleGrid>
                            </section>
                        ))}
                    </div>
                </fieldset>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={mutation.isPending} disabled={eventQuery.isLoading}>
                        {t`Duplicate Event`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    )
}
