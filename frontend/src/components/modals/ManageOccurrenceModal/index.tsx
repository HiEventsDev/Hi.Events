import {EventOccurrence, EventOccurrenceStatus, GenericModalProps, IdParam, MessageType} from "../../../types.ts";
import {useNavigate, useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventOccurrence} from "../../../queries/useGetEventOccurrence.ts";
import {useGetEventCheckInLists} from "../../../queries/useGetCheckInLists.ts";
import {t} from "@lingui/macro";
import {useCallback, useMemo, useRef, useState} from "react";
import {Checkbox, Progress, Skeleton, Stack, Text} from "@mantine/core";
import {modals} from "@mantine/modals";
import {OccurrenceAttendeesAndOrders} from "../../common/OccurrenceAttendeesAndOrders";
import {SideDrawer} from "../../common/SideDrawer";
import {SendMessageModal} from "../SendMessageModal";
import {ShareModal} from "../ShareModal";
import {OccurrenceEditModal} from "../../routes/event/OccurrencesTab/OccurrenceEditModal";
import {CreateCheckInListModal} from "../CreateCheckInListModal";
import {OccurrenceActionBar, OccurrenceMenuActions} from "../../routes/event/OccurrencesTab/OccurrenceMenu";
import {statusLabel} from "../../routes/event/OccurrencesTab/OccurrenceMenu";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useCancelOccurrence} from "../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../mutations/useDeleteEventOccurrence.ts";
import {useUpdateEventOccurrence} from "../../../mutations/useUpdateEventOccurrence.ts";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import classes from './ManageOccurrenceModal.module.scss';

interface ManageOccurrenceModalProps {
    occurrenceId: IdParam;
}

export const ManageOccurrenceModal = ({onClose, occurrenceId}: GenericModalProps & ManageOccurrenceModalProps) => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const {data: occurrence} = useGetEventOccurrence(eventId, occurrenceId);
    const {data: event} = useGetEvent(eventId);
    const checkInListsQuery = useGetEventCheckInLists(eventId);
    const checkInLists = checkInListsQuery?.data?.data;

    const [showMessageModal, setShowMessageModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showShareOccurrence, setShowShareOccurrence] = useState<EventOccurrence | undefined>();
    const [createCheckInForOccurrenceId, setCreateCheckInForOccurrenceId] = useState<number | undefined>();

    const cancelMutation = useCancelOccurrence();
    const deleteMutation = useDeleteEventOccurrence();
    const updateMutation = useUpdateEventOccurrence();
    const refundRef = useRef(false);

    const handleCheckIn = useCallback((occurrenceId: number) => {
        const list = checkInLists?.find(l => l.event_occurrence_id === occurrenceId)
            || checkInLists?.find(l => !l.event_occurrence_id);

        if (list) {
            window.open(`/check-in/${list.short_id}`, '_blank');
        } else {
            setCreateCheckInForOccurrenceId(occurrenceId);
        }
    }, [checkInLists]);

    const handleCancel = useCallback((occId: number) => {
        const occ = occurrence;
        const orderCount = occ?.statistics?.orders_created ?? 0;
        refundRef.current = false;

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
                cancelMutation.mutate({eventId, occurrenceId: occId, refundOrders: refundRef.current}, {
                    onSuccess: () => showSuccess(t`Date cancelled`),
                    onError: (error: any) => showError(error?.response?.data?.message || t`Failed to cancel date`),
                });
            },
        });
    }, [occurrence, eventId]);

    const handleDelete = useCallback((occId: number) => {
        confirmationDialog(t`Are you sure you want to delete this date? This action cannot be undone.`, () => {
            deleteMutation.mutate({eventId, occurrenceId: occId}, {
                onSuccess: () => {
                    showSuccess(t`Date deleted`);
                    onClose();
                },
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to delete date`),
            });
        });
    }, [eventId, onClose]);

    const handleReactivate = useCallback((occ: EventOccurrence) => {
        confirmationDialog(t`Reactivate this date? It will be reopened for future sales.`, () => {
            updateMutation.mutate({
                eventId,
                occurrenceId: occ.id,
                data: {start_date: occ.start_date, status: 'ACTIVE'},
            }, {
                onSuccess: () => showSuccess(t`Date reactivated`),
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to reactivate date`),
            });
        });
    }, [eventId]);

    const menuActions: OccurrenceMenuActions = useMemo(() => ({
        eventId: eventId!,
        onEdit: () => setShowEditModal(true),
        onCancel: handleCancel,
        onDelete: handleDelete,
        onNavigate: (path: string) => {
            onClose();
            navigate(path);
        },
        onMessage: () => setShowMessageModal(true),
        onCheckIn: handleCheckIn,
        onReactivate: handleReactivate,
        onShare: (occ: EventOccurrence) => setShowShareOccurrence(occ),
    }), [eventId, handleCheckIn, handleCancel, handleDelete, handleReactivate, onClose, navigate]);

    if (!occurrence || !event) {
        return (
            <SideDrawer opened={true} onClose={onClose} size="lg" padding="md">
                <Stack p="md" gap="md">
                    <Skeleton height={24} width="70%"/>
                    <Skeleton height={14} width="40%"/>
                    <div style={{display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10}}>
                        <Skeleton height={70} radius="md"/>
                        <Skeleton height={70} radius="md"/>
                        <Skeleton height={70} radius="md"/>
                        <Skeleton height={70} radius="md"/>
                    </div>
                    <Skeleton height={32} width="100%"/>
                </Stack>
            </SideDrawer>
        );
    }

    const startFormatted = formatDateWithLocale(occurrence.start_date, 'fullDateTime', event.timezone);
    const endFormatted = occurrence.end_date
        ? formatDateWithLocale(occurrence.end_date, 'timeOnly', event.timezone)
        : null;

    const usedCapacity = occurrence.used_capacity ?? 0;
    const hasCapacityLimit = occurrence.capacity != null;
    const soldLabel = hasCapacityLimit
        ? `${usedCapacity} / ${occurrence.capacity}`
        : `${usedCapacity}`;
    const capacityPercent = hasCapacityLimit && occurrence.capacity
        ? Math.min(100, Math.round((usedCapacity / occurrence.capacity) * 100))
        : 0;

    return (
        <SideDrawer opened={true} onClose={onClose} size="lg" padding="md">
            <Stack className={classes.container}>
                <div className={classes.header}>
                    <div className={classes.occurrenceInfo}>
                        <Text className={classes.dateTime}>
                            {startFormatted}{endFormatted && <> &mdash; {endFormatted}</>}
                        </Text>
                        {occurrence.label && (
                            <Text className={classes.titleSuffix}>{occurrence.label}</Text>
                        )}
                    </div>
                    <div className={classes.statusBadge} data-status={occurrence.status}>
                        {statusLabel(occurrence.status)}
                    </div>
                </div>

                <div className={classes.statsGrid}>
                    <div className={classes.statCard}>
                        <div className={classes.statValue}>{occurrence.statistics?.attendees_registered ?? 0}</div>
                        <div className={classes.statLabel}>{t`Attendees`}</div>
                    </div>
                    <div className={classes.statCard}>
                        <div className={classes.statValue}>{occurrence.statistics?.orders_created ?? 0}</div>
                        <div className={classes.statLabel}>{t`Orders`}</div>
                    </div>
                    <div className={classes.statCard}>
                        <div className={classes.statValue}>{formatCurrency(occurrence.statistics?.total_gross_sales ?? 0, event.currency)}</div>
                        <div className={classes.statLabel}>{t`Gross Sales`}</div>
                    </div>
                    <div className={classes.statCard}>
                        <div className={classes.statValue}>{soldLabel}</div>
                        <div className={classes.statLabel}>{t`Sold`}</div>
                        {hasCapacityLimit && (
                            <Progress
                                value={capacityPercent}
                                size="sm"
                                mt={6}
                                color={capacityPercent >= 90 ? 'red' : capacityPercent >= 70 ? 'orange' : 'blue'}
                            />
                        )}
                    </div>
                </div>

                <OccurrenceActionBar occurrence={occurrence} actions={menuActions}/>

                <OccurrenceAttendeesAndOrders occurrenceId={occurrenceId} onNavigateAway={onClose}/>
            </Stack>

            {showMessageModal && (
                <SendMessageModal
                    onClose={() => setShowMessageModal(false)}
                    messageType={MessageType.AllAttendees}
                    eventOccurrenceId={occurrenceId}
                />
            )}

            {showEditModal && (
                <OccurrenceEditModal
                    onClose={() => setShowEditModal(false)}
                    occurrenceId={occurrenceId}
                />
            )}

            {createCheckInForOccurrenceId && (
                <CreateCheckInListModal
                    onClose={() => setCreateCheckInForOccurrenceId(undefined)}
                    initialOccurrenceId={createCheckInForOccurrenceId}
                />
            )}

            {showShareOccurrence && (
                <ShareModal
                    opened={!!showShareOccurrence}
                    onClose={() => setShowShareOccurrence(undefined)}
                    url={`${eventHomepageUrl(event)}?occurrence_id=${showShareOccurrence.id}`}
                    title={event.title}
                    shareText={`${event.title} — ${formatDateWithLocale(showShareOccurrence.start_date, 'shortDateTime', event.timezone)}`}
                />
            )}
        </SideDrawer>
    );
};
