import {t, Trans} from "@lingui/macro";
import {Anchor, Button, Checkbox, NumberInput, Radio, SegmentedControl, Tabs, Text, TextInput, UnstyledButton} from "@mantine/core";
import {useForm} from "@mantine/form";
import {modals} from "@mantine/modals";
import {Link, useParams} from "react-router";
import {useEffect, useMemo, useRef, useState} from "react";
import dayjs from "dayjs";
import {
    IconAlertTriangle,
    IconCalendar,
    IconEdit,
    IconInfoCircle,
    IconMapPin,
    IconShoppingCart,
    IconTag,
    IconTrash,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import {Modal} from "../../../../common/Modal";
import {InputGroup} from "../../../../common/InputGroup";
import {Callout} from "../../../../common/Callout";
import {
    EventOccurrence,
    EventOccurrenceStatus,
    GenericModalProps,
    IdParam,
    LocationType,
    UpsertEventOccurrenceRequest,
} from "../../../../../types.ts";
import {Editor} from "../../../../common/Editor";
import {LocationPicker, LocationPickerValue} from "../../../../common/LocationPicker";
import {useCreateLocation} from "../../../../../mutations/useCreateLocation.ts";
import {useGetEventOccurrence} from "../../../../../queries/useGetEventOccurrence.ts";
import {isAddressSet, sameAddress} from "../../../../../utilites/addressUtilities.ts";
import {useCreateEventOccurrence} from "../../../../../mutations/useCreateEventOccurrence.ts";
import {useUpdateEventOccurrence} from "../../../../../mutations/useUpdateEventOccurrence.ts";
import {useCancelOccurrence} from "../../../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../../../mutations/useDeleteEventOccurrence.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {utcToTz} from "../../../../../utilites/dates.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {isEmptyHtml} from "../../../../../utilites/helpers.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {OccurrenceProductSettings} from "../PriceOverrideForm";
import {SendMessageModal} from "../../../../modals/SendMessageModal";
import {MessageType} from "../../../../../types.ts";
import {buildSingleRescheduleTemplate} from "../rescheduleMessageTemplate";
import classes from './OccurrenceEditModal.module.scss';

interface OccurrenceEditModalProps extends GenericModalProps {
    occurrenceId?: IdParam;
    duplicateFrom?: EventOccurrence;
    defaultDate?: string;
}

type CapacityVisibility = 'inherit' | 'show' | 'hide';

const capacityVisibilityToForm = (value?: boolean | null): CapacityVisibility =>
    value === true ? 'show' : value === false ? 'hide' : 'inherit';

const capacityVisibilityToPayload = (value: CapacityVisibility): boolean | null =>
    value === 'show' ? true : value === 'hide' ? false : null;

export const OccurrenceEditModal = ({onClose, occurrenceId, duplicateFrom, defaultDate}: OccurrenceEditModalProps) => {
    const {eventId} = useParams();
    const isEditing = !!occurrenceId;
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const {data: occurrence} = useGetEventOccurrence(eventId, occurrenceId);

    const createMutation = useCreateEventOccurrence();
    const updateMutation = useUpdateEventOccurrence();
    const cancelMutation = useCancelOccurrence();
    const deleteMutation = useDeleteEventOccurrence();
    const createLocationMutation = useCreateLocation();
    const organizerId = event?.organizer?.id ?? event?.organizer_id;

    type OccurrenceFormShape = {
        start_date: string;
        end_date: string;
        capacity: number | null;
        label: string;
        show_available_capacity: 'inherit' | 'show' | 'hide';
        online_event_connection_details: string;
        location_mode: 'inherit' | 'override' | 'online';
        location: LocationPickerValue | null;
    };

    const form = useForm<OccurrenceFormShape>({
        initialValues: {
            start_date: '',
            end_date: '',
            capacity: null,
            label: '',
            show_available_capacity: 'inherit',
            online_event_connection_details: '',
            location_mode: 'inherit',
            location: null,
        },
        validate: {
            start_date: (value) => !value ? t`Start date is required` : null,
            end_date: (value, values) => {
                if (value && values.start_date && value < values.start_date) {
                    return t`End date must be after start date`;
                }
                return null;
            },
            capacity: (value) => {
                if (value !== null && value !== undefined && value < 0) {
                    return t`Capacity must be 0 or greater`;
                }
                return null;
            },
            online_event_connection_details: (value, values) => {
                if (values.location_mode === 'online' && (!value || isEmptyHtml(value))) {
                    return t`Connection details are required for online dates`;
                }
                return null;
            },
            location: (value, values) => {
                if (values.location_mode !== 'override') {
                    return null;
                }
                if (!value || (value.kind === 'new' && !isAddressSet(value.address))) {
                    return t`Enter a venue name or address for in-person events`;
                }
                return null;
            },
        },
    });

    useEffect(() => {
        if (occurrence && event) {
            const eventLocation = occurrence.event_location;
            const occType = eventLocation?.type;
            const mode: 'inherit' | 'override' | 'online' = (() => {
                if (occType === LocationType.Online) return 'online';
                if (occType === LocationType.InPerson) return 'override';
                return 'inherit';
            })();
            const occLocation = eventLocation?.location;
            const occLocationId = eventLocation?.location_id ?? null;
            form.setValues({
                start_date: utcToTz(occurrence.start_date, event.timezone) || '',
                end_date: utcToTz(occurrence.end_date, event.timezone) || '',
                capacity: occurrence.capacity ?? null,
                label: occurrence.label || '',
                show_available_capacity: capacityVisibilityToForm(occurrence.show_available_capacity),
                online_event_connection_details: eventLocation?.online_event_connection_details ?? '',
                location_mode: mode,
                location: occLocationId && occLocation
                    ? {kind: 'saved', location: {...occLocation, id: occLocation.id ?? occLocationId}}
                    : null,
            });
        }
    }, [occurrence, event]);

    useEffect(() => {
        if (duplicateFrom && event && !isEditing) {
            form.setValues({
                start_date: utcToTz(duplicateFrom.start_date, event.timezone) || '',
                end_date: utcToTz(duplicateFrom.end_date, event.timezone) || '',
                capacity: duplicateFrom.capacity ?? null,
                label: duplicateFrom.label || '',
                show_available_capacity: capacityVisibilityToForm(duplicateFrom.show_available_capacity),
            });
        }
    }, [duplicateFrom, event]);

    useEffect(() => {
        if (defaultDate && !isEditing && !duplicateFrom) {
            form.setFieldValue('start_date', defaultDate + 'T09:00');
        }
    }, [defaultDate]);

    const [pendingNotification, setPendingNotification] = useState<{
        occurrence: EventOccurrence;
        newStartDate: string;
        newEndDate: string | null | undefined;
    } | null>(null);

    type OccurrenceFormValues = OccurrenceFormShape;

    const resolveLocationId = async (value: LocationPickerValue): Promise<number | null> => {
        if (value.kind === 'saved') {
            return value.location.id ? Number(value.location.id) : null;
        }

        if (!organizerId || !isAddressSet(value.address)) {
            return null;
        }

        const existingLocationId = occurrence?.event_location?.location_id ?? null;
        const existingAddress = occurrence?.event_location?.location?.structured_address ?? null;
        if (existingLocationId !== null && existingAddress !== null && sameAddress(existingAddress, value.address)) {
            return existingLocationId;
        }

        const created = await createLocationMutation.mutateAsync({
            organizerId,
            payload: {
                name: value.address.venue_name || null,
                structured_address: value.address,
                latitude: value.latitude,
                longitude: value.longitude,
                provider: value.provider,
                provider_place_id: value.providerPlaceId,
            },
        });

        return (created.data.id as number | undefined) ?? null;
    };

    const submit = async (values: OccurrenceFormValues, notifyAfterSave: boolean) => {
        try {
            let eventLocationPayload: UpsertEventOccurrenceRequest['event_location'] = undefined;
            let clearEventLocation = false;

            if (values.location_mode === 'inherit') {
                clearEventLocation = true;
            } else if (values.location_mode === 'online') {
                eventLocationPayload = {
                    type: LocationType.Online,
                    online_event_connection_details: values.online_event_connection_details || null,
                };
            } else if (values.location_mode === 'override') {
                const locationIdForOccurrence = values.location ? await resolveLocationId(values.location) : null;
                if (locationIdForOccurrence === null) {
                    throw new Error(t`Enter a venue name or address for in-person events`);
                }
                eventLocationPayload = {
                    type: LocationType.InPerson,
                    location_id: locationIdForOccurrence,
                };
            }

            const normalisedEnd = values.end_date && values.end_date > values.start_date
                ? values.end_date
                : undefined;

            const payload: UpsertEventOccurrenceRequest = {
                start_date: values.start_date,
                end_date: normalisedEnd,
                capacity: values.capacity,
                label: values.label,
                show_available_capacity: capacityVisibilityToPayload(values.show_available_capacity),
                ...(eventLocationPayload ? {event_location: eventLocationPayload} : {}),
                ...(clearEventLocation ? {clear_event_location: true} : {}),
            };

            const onSuccess = () => {
                showSuccess(isEditing
                    ? t`Date updated successfully`
                    : t`Date created successfully`
                );
                if (notifyAfterSave && isEditing && occurrence) {
                    setPendingNotification({
                        occurrence,
                        newStartDate: values.start_date,
                        newEndDate: values.end_date,
                    });
                    return;
                }
                onClose();
            };
            const onError = (error: any) => errorHandler(form, error);

            if (isEditing) {
                updateMutation.mutate({eventId, occurrenceId, data: payload}, {onSuccess, onError});
            } else {
                createMutation.mutate({eventId, data: payload}, {onSuccess, onError});
            }
        } catch (error: any) {
            showError(error?.message || t`Could not save date`);
            errorHandler(form, error);
        }
    };

    const handleSubmit = (values: OccurrenceFormValues) => {
        const attendeeCount = occurrence?.statistics?.attendees_registered ?? 0;
        const originalStart = occurrence && event ? utcToTz(occurrence.start_date, event.timezone) : '';
        const originalEnd = occurrence && event ? utcToTz(occurrence.end_date, event.timezone) : '';
        const dateChanged = isEditing
            && (values.start_date !== originalStart || (values.end_date || '') !== (originalEnd || ''));

        if (dateChanged && attendeeCount > 0) {
            const notifyRef = {current: true};
            modals.openConfirmModal({
                title: t`You've changed the session time`,
                children: (
                    <>
                        <Text size="sm" mb="md">
                            {attendeeCount === 1
                                ? t`1 attendee is registered for this session.`
                                : t`${attendeeCount} attendees are registered for this session.`}
                        </Text>
                        <Checkbox
                            defaultChecked={true}
                            label={t`Let them know about the change`}
                            description={t`We'll open a message composer with a pre-filled template after saving. You review and send it — nothing is sent automatically.`}
                            onChange={(e) => { notifyRef.current = e.currentTarget.checked; }}
                        />
                    </>
                ),
                labels: {confirm: t`Save`, cancel: t`Cancel`},
                onConfirm: () => submit(values, notifyRef.current),
            });
            return;
        }

        submit(values, false);
    };

    const refundRef = useRef(false);

    const handleCancel = () => {
        refundRef.current = false;
        const orderCount = occurrence?.statistics?.orders_created ?? 0;

        modals.openConfirmModal({
            title: t`Cancel Date`,
            children: (
                <>
                    <Text size="sm" mb="md">
                        {t`Are you sure you want to cancel this date? Affected attendees will be notified by email.`}
                    </Text>
                    {orderCount > 0 && (
                        <Text size="sm" fw={600} c="red" mb="md">
                            {t`This date has ${orderCount} order(s) that will be affected.`}
                        </Text>
                    )}
                    <Checkbox
                        label={t`Refund all orders for this date`}
                        description={t`Orders spanning multiple dates will be flagged for manual review.`}
                        onChange={(e) => { refundRef.current = e.currentTarget.checked; }}
                    />
                </>
            ),
            labels: {confirm: t`Cancel Date`, cancel: t`Go Back`},
            confirmProps: {color: 'red'},
            onConfirm: () => {
                cancelMutation.mutate({eventId, occurrenceId, refundOrders: refundRef.current}, {
                    onSuccess: () => {
                        showSuccess(t`Date cancelled successfully`);
                        onClose();
                    },
                    onError: (error: any) => {
                        showError(error?.response?.data?.message || t`Failed to cancel date`);
                    },
                });
            },
        });
    };

    const handleDelete = () => {
        modals.openConfirmModal({
            title: t`Delete Date`,
            children: (
                <Text size="sm">
                    {t`Are you sure you want to permanently delete this date? This cannot be undone.`}
                </Text>
            ),
            labels: {confirm: t`Delete Permanently`, cancel: t`Go Back`},
            confirmProps: {color: 'red'},
            onConfirm: () => {
                deleteMutation.mutate({eventId, occurrenceId}, {
                    onSuccess: () => {
                        showSuccess(t`Date deleted successfully`);
                        onClose();
                    },
                    onError: (error: any) => {
                        showError(error?.response?.data?.message || t`Failed to delete date. It may have existing orders.`);
                    },
                });
            },
        });
    };

    const isCancelled = occurrence?.status === EventOccurrenceStatus.CANCELLED;
    const isPending = createMutation.isPending || updateMutation.isPending;
    const isStartDateInPast = useMemo(() => {
        if (!form.values.start_date) return false;
        return dayjs(form.values.start_date).isBefore(dayjs());
    }, [form.values.start_date]);

    const notificationTemplate = useMemo(() => {
        if (!pendingNotification || !event) return null;
        return buildSingleRescheduleTemplate(
            event,
            pendingNotification.occurrence,
            pendingNotification.newStartDate,
            pendingNotification.newEndDate,
        );
    }, [pendingNotification, event]);

    return (
        <>
        <Modal
            opened={!pendingNotification}
            onClose={onClose}
            heading={isEditing ? t`Edit Date` : duplicateFrom ? t`Duplicate Date` : t`Add Date`}
            size="lg"
        >
            <Tabs defaultValue="details">
                <Tabs.List>
                    <Tabs.Tab value="details" leftSection={<IconEdit size={14}/>}>
                        {t`Details`}
                    </Tabs.Tab>
                    {isEditing && (
                        <Tabs.Tab value="products" leftSection={<IconShoppingCart size={14}/>}>
                            {t`Products`}
                        </Tabs.Tab>
                    )}
                </Tabs.List>

                <Tabs.Panel value="details" pt="md">
                    {isCancelled && (
                        <div className={classes.cancelledBanner}>
                            <div className={classes.cancelledIcon}>
                                <IconX size={14}/>
                            </div>
                            <span className={classes.cancelledText}>
                                {t`This date has been cancelled. You can still delete it to remove it permanently.`}
                            </span>
                        </div>
                    )}

                    <form onSubmit={form.onSubmit(handleSubmit)}>
                        <fieldset disabled={isCancelled || isPending} style={{border: 'none', padding: 0, margin: 0}}>
                            <div className={classes.section}>
                                <div className={classes.sectionHeader}>
                                    <div className={classes.sectionIcon}><IconCalendar size={16}/></div>
                                    <span className={classes.sectionTitle}>{t`Date & Time`}</span>
                                </div>
                                <InputGroup>
                                    <TextInput
                                        type="datetime-local"
                                        {...form.getInputProps('start_date')}
                                        label={t`Start Date`}
                                        required
                                    />
                                    <TextInput
                                        type="datetime-local"
                                        {...form.getInputProps('end_date')}
                                        label={t`End Date`}
                                    />
                                    <TextInput
                                        {...form.getInputProps('label')}
                                        label={t`Label`}
                                        placeholder={t`e.g. Morning Session`}
                                        leftSection={<IconTag size={14}/>}
                                    />
                                </InputGroup>
                                {isStartDateInPast && !isEditing && (
                                    <div className={classes.pastDateWarning}>
                                        <IconAlertTriangle size={14}/>
                                        <span>{t`This date is in the past. It will be created but won't be visible to attendees under upcoming dates.`}</span>
                                    </div>
                                )}
                            </div>

                            <div className={classes.section}>
                                <div className={classes.sectionHeader}>
                                    <div className={classes.sectionIcon}><IconMapPin size={16}/></div>
                                    <span className={classes.sectionTitle}>{t`Location`}</span>
                                </div>
                                <Radio.Group
                                    value={form.values.location_mode}
                                    onChange={(value) => form.setFieldValue('location_mode', value as 'inherit' | 'override' | 'online')}
                                >
                                    <Radio value="inherit" label={t`Same as event`} mb={6}/>
                                    <Radio value="override" label={t`Different location`} mb={6}/>
                                    <Radio value="online" label={t`Online`}/>
                                </Radio.Group>

                                {form.values.location_mode === 'override' && organizerId && (
                                    <div style={{marginTop: 12}}>
                                        <LocationPicker
                                            organizerId={organizerId}
                                            value={form.values.location}
                                            onChange={(value) => form.setFieldValue('location', value)}
                                            error={form.errors.location}
                                        />
                                    </div>
                                )}

                                {form.values.location_mode === 'online' && (
                                    <div style={{marginTop: 12}}>
                                        <Editor
                                            value={form.values.online_event_connection_details || ''}
                                            error={form.errors.online_event_connection_details as string}
                                            label={t`Connection Details`}
                                            description={t`These details are shown on the attendee's ticket and order summary for this date only.`}
                                            onChange={(value) => form.setFieldValue('online_event_connection_details', value)}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className={classes.section}>
                                <div className={classes.sectionHeader}>
                                    <div className={classes.sectionIcon}><IconUsers size={16}/></div>
                                    <span className={classes.sectionTitle}>{t`Capacity`}</span>
                                </div>
                                <NumberInput
                                    {...form.getInputProps('capacity')}
                                    placeholder={t`Leave empty for unlimited`}
                                    min={0}
                                    allowNegative={false}
                                />
                                <Callout
                                    variant="info"
                                    title={t`Only tickets count toward capacity`}
                                    style={{marginTop: 12}}
                                >
                                    <Trans>
                                        Selling a physical product? Cap its quantity on the{" "}
                                        <Anchor component={Link} to={`/manage/event/${eventId}/products`} target="_blank" inherit>
                                            products page
                                        </Anchor>{" "}
                                        instead.
                                    </Trans>
                                </Callout>
                                <Text size="sm" fw={500} mt="md" mb={6}>
                                    {t`Show remaining capacity to buyers`}
                                </Text>
                                <SegmentedControl
                                    fullWidth
                                    size="sm"
                                    data={[
                                        {label: t`Use event default`, value: 'inherit'},
                                        {label: t`Show`, value: 'show'},
                                        {label: t`Hide`, value: 'hide'},
                                    ]}
                                    value={form.values.show_available_capacity}
                                    onChange={(value) => form.setFieldValue('show_available_capacity', value as CapacityVisibility)}
                                />
                            </div>

                            <Button
                                type="submit"
                                fullWidth
                                size="md"
                                loading={isPending}
                            >
                                {isEditing ? t`Save Changes` : t`Create Date`}
                            </Button>
                        </fieldset>
                    </form>

                    {isEditing && (
                        <div className={classes.dangerZone}>
                            <div className={classes.dangerHeader}>
                                <div className={classes.dangerIcon}><IconAlertTriangle size={16}/></div>
                                <span className={classes.dangerTitle}>{t`Danger Zone`}</span>
                            </div>
                            <div className={classes.dangerActions}>
                                {!isCancelled && (
                                    <UnstyledButton
                                        className={classes.dangerAction}
                                        onClick={handleCancel}
                                        data-loading={cancelMutation.isPending}
                                    >
                                        <div className={classes.dangerActionIcon}>
                                            <IconInfoCircle size={16}/>
                                        </div>
                                        <div>
                                            <div className={classes.dangerActionLabel}>{t`Cancel Date`}</div>
                                            <div className={classes.dangerActionDesc}>{t`Notify attendees and stop sales`}</div>
                                        </div>
                                    </UnstyledButton>
                                )}
                                <UnstyledButton
                                    className={classes.dangerAction}
                                    onClick={handleDelete}
                                    data-loading={deleteMutation.isPending}
                                >
                                    <div className={classes.dangerActionIcon}>
                                        <IconTrash size={16}/>
                                    </div>
                                    <div>
                                        <div className={classes.dangerActionLabel}>{t`Delete Date`}</div>
                                        <div className={classes.dangerActionDesc}>{t`Permanently remove this date`}</div>
                                    </div>
                                </UnstyledButton>
                            </div>
                        </div>
                    )}
                </Tabs.Panel>

                {isEditing && (
                    <Tabs.Panel value="products" pt="md">
                        {isCancelled ? (
                            <div className={classes.cancelledBanner}>
                                <div className={classes.cancelledIcon}>
                                    <IconX size={14}/>
                                </div>
                                <span className={classes.cancelledText}>
                                    {t`Product settings cannot be edited for cancelled dates.`}
                                </span>
                            </div>
                        ) : (
                            <OccurrenceProductSettings occurrenceId={occurrenceId}/>
                        )}
                    </Tabs.Panel>
                )}
            </Tabs>
        </Modal>
        {pendingNotification && notificationTemplate && (
            <SendMessageModal
                onClose={() => {
                    setPendingNotification(null);
                    onClose();
                }}
                messageType={MessageType.AllAttendees}
                eventOccurrenceId={pendingNotification.occurrence.id}
                initialSubject={notificationTemplate.subject}
                initialMessage={notificationTemplate.message}
            />
        )}
        </>
    );
};
