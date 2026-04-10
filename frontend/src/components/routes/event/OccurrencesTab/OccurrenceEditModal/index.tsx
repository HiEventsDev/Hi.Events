import {t} from "@lingui/macro";
import {Button, Checkbox, NumberInput, Tabs, Text, TextInput, UnstyledButton} from "@mantine/core";
import {useForm} from "@mantine/form";
import {modals} from "@mantine/modals";
import {useParams} from "react-router";
import {useEffect, useMemo, useRef} from "react";
import dayjs from "dayjs";
import {
    IconAlertTriangle,
    IconCalendar,
    IconEdit,
    IconInfoCircle,
    IconShoppingCart,
    IconTag,
    IconTrash,
    IconUsers,
    IconX,
} from "@tabler/icons-react";
import {Modal} from "../../../../common/Modal";
import {InputGroup} from "../../../../common/InputGroup";
import {
    EventOccurrence,
    EventOccurrenceStatus,
    GenericModalProps,
    IdParam,
    UpsertEventOccurrenceRequest,
} from "../../../../../types.ts";
import {useGetEventOccurrence} from "../../../../../queries/useGetEventOccurrence.ts";
import {useCreateEventOccurrence} from "../../../../../mutations/useCreateEventOccurrence.ts";
import {useUpdateEventOccurrence} from "../../../../../mutations/useUpdateEventOccurrence.ts";
import {useCancelOccurrence} from "../../../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../../../mutations/useDeleteEventOccurrence.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {utcToTz} from "../../../../../utilites/dates.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {OccurrenceProductSettings} from "../PriceOverrideForm";
import classes from './OccurrenceEditModal.module.scss';

interface OccurrenceEditModalProps extends GenericModalProps {
    occurrenceId?: IdParam;
    duplicateFrom?: EventOccurrence;
    defaultDate?: string;
}

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

    const form = useForm<UpsertEventOccurrenceRequest>({
        initialValues: {
            start_date: '',
            end_date: '',
            capacity: null,
            label: '',
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
        },
    });

    useEffect(() => {
        if (occurrence && event) {
            form.setValues({
                start_date: utcToTz(occurrence.start_date, event.timezone) || '',
                end_date: utcToTz(occurrence.end_date, event.timezone) || '',
                capacity: occurrence.capacity ?? null,
                label: occurrence.label || '',
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
            });
        }
    }, [duplicateFrom, event]);

    useEffect(() => {
        if (defaultDate && !isEditing && !duplicateFrom) {
            form.setFieldValue('start_date', defaultDate + 'T09:00');
        }
    }, [defaultDate]);

    const handleSubmit = (values: UpsertEventOccurrenceRequest) => {
        const onSuccess = () => {
            showSuccess(isEditing
                ? t`Date updated successfully`
                : t`Date created successfully`
            );
            onClose();
        };
        const onError = (error: any) => errorHandler(form, error);

        if (isEditing) {
            updateMutation.mutate({eventId, occurrenceId, data: values}, {onSuccess, onError});
        } else {
            createMutation.mutate({eventId, data: values}, {onSuccess, onError});
        }
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

    return (
        <Modal
            opened
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
                                    <div className={classes.sectionIcon}><IconUsers size={16}/></div>
                                    <span className={classes.sectionTitle}>{t`Capacity`}</span>
                                </div>
                                <NumberInput
                                    {...form.getInputProps('capacity')}
                                    placeholder={t`Leave empty for unlimited`}
                                    min={0}
                                    allowNegative={false}
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
    );
};
