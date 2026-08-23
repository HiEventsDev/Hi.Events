import {useNavigate, useParams} from "react-router";
import {t} from "@lingui/macro";
import {Checkbox, Skeleton, Text} from "@mantine/core";
import {useCallback, useMemo, useRef, useState} from "react";
import {useDisclosure} from "@mantine/hooks";
import {modals} from "@mantine/modals";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {StatBoxes} from "../../../common/StatBoxes";
import {ProductSalesChartCard, RevenueChartCard} from "../../../common/StatsCharts";
import {OccurrenceAttendeesAndOrders} from "../../../common/OccurrenceAttendeesAndOrders";
import {OccurrenceEditModal} from "../OccurrencesTab/OccurrenceEditModal";
import {SendMessageModal} from "../../../modals/SendMessageModal";
import {ShareModal} from "../../../modals/ShareModal";
import {OccurrenceActionBar, OccurrenceMenuActions, statusLabel} from "../OccurrencesTab/OccurrenceMenu";
import {useOccurrenceCheckIn} from "../../../../hooks/useOccurrenceCheckIn.tsx";
import {useGetEventOccurrence} from "../../../../queries/useGetEventOccurrence.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventStats} from "../../../../queries/useGetEventStats.ts";
import {useCancelOccurrence} from "../../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../../mutations/useDeleteEventOccurrence.ts";
import {useReactivateOccurrence} from "../../../../mutations/useReactivateOccurrence.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {EventOccurrence, MessageType} from "../../../../types.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import classes from "./OccurrenceDetail.module.scss";

const OccurrenceDetail = () => {
    const {eventId, occurrenceId} = useParams();
    const navigate = useNavigate();
    const {data: event} = useGetEvent(eventId);
    const {data: occurrence, isLoading: occurrenceLoading} = useGetEventOccurrence(eventId, occurrenceId);
    const {data: eventStats} = useGetEventStats(eventId, {occurrenceId});

    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [showMessageModal, setShowMessageModal] = useState(false);
    const [showShareOccurrence, setShowShareOccurrence] = useState<EventOccurrence | undefined>();

    const {launchCheckIn, checkInModals} = useOccurrenceCheckIn(eventId);

    const cancelMutation = useCancelOccurrence();
    const deleteMutation = useDeleteEventOccurrence();
    const reactivateMutation = useReactivateOccurrence();
    const refundRef = useRef(false);

    const handleCancel = useCallback((occId: number) => {
        const orderCount = occurrence?.statistics?.orders_created ?? 0;
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
                    navigate(`/manage/event/${eventId}/occurrences`);
                },
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to delete date`),
            });
        });
    }, [eventId, navigate]);

    const handleReactivate = useCallback((occ: EventOccurrence) => {
        confirmationDialog(t`Reactivate this date? It will be reopened for future sales.`, () => {
            reactivateMutation.mutate({
                eventId,
                occurrenceId: occ.id,
            }, {
                onSuccess: () => showSuccess(t`Date reactivated`),
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to reactivate date`),
            });
        });
    }, [eventId]);

    const menuActions: OccurrenceMenuActions = useMemo(() => ({
        eventId: eventId!,
        onEdit: () => openEditModal(),
        onCancel: handleCancel,
        onDelete: handleDelete,
        onNavigate: navigate,
        onMessage: () => setShowMessageModal(true),
        onCheckIn: launchCheckIn,
        onReactivate: handleReactivate,
        onShare: (occ: EventOccurrence) => setShowShareOccurrence(occ),
    }), [eventId, launchCheckIn, handleCancel, handleDelete, handleReactivate, navigate, openEditModal]);

    if (occurrenceLoading || !event) {
        return (
            <PageBody>
                <Skeleton height={30} width={200} mb="md"/>
                <Skeleton height={120} mb="md"/>
                <Skeleton height={300}/>
            </PageBody>
        );
    }

    const startDate = occurrence
        ? formatDateWithLocale(occurrence.start_date, 'fullDateTime', event.timezone)
        : '';
    const dateRange = eventStats
        ? `${formatDateWithLocale(eventStats.start_date, 'chartDate', event.timezone)} - ${formatDateWithLocale(eventStats.end_date, 'chartDate', event.timezone)}`
        : '';

    return (
        <PageBody>
            <div className={classes.header}>
                <PageTitle style={{marginBottom: 0}}>
                    {startDate}
                    {occurrence?.label && ` — ${occurrence.label}`}
                </PageTitle>
                {occurrence?.status && (
                    <span className={classes.statusBadge} data-status={occurrence.status}>
                        {statusLabel(occurrence.status)}
                    </span>
                )}
            </div>

            {occurrence && (
                <div style={{marginBottom: 16}}>
                    <OccurrenceActionBar occurrence={occurrence} actions={menuActions} hiddenKeys={['dashboard']}/>
                </div>
            )}

            <div style={{marginBottom: 20}}>
                <StatBoxes occurrenceId={occurrenceId}/>
            </div>

            <OccurrenceAttendeesAndOrders occurrenceId={occurrenceId} perPage={25}/>

            {eventStats && (
                <>
                    <ProductSalesChartCard
                        dailyStats={eventStats.daily_stats}
                        timezone={event.timezone}
                        dateRangeLabel={dateRange}
                        syncId="occurrences"
                    />

                    <RevenueChartCard
                        dailyStats={eventStats.daily_stats}
                        timezone={event.timezone}
                        currency={event.currency}
                        dateRangeLabel={dateRange}
                        syncId="occurrences"
                    />
                </>
            )}

            {editModalOpen && (
                <OccurrenceEditModal
                    onClose={closeEditModal}
                    occurrenceId={occurrenceId}
                />
            )}

            {showMessageModal && (
                <SendMessageModal
                    onClose={() => setShowMessageModal(false)}
                    messageType={MessageType.AllAttendees}
                    eventOccurrenceId={occurrenceId}
                />
            )}

            {checkInModals}

            {showShareOccurrence && (
                <ShareModal
                    opened={!!showShareOccurrence}
                    onClose={() => setShowShareOccurrence(undefined)}
                    url={`${eventHomepageUrl(event)}?occurrence_id=${showShareOccurrence.id}`}
                    title={event.title}
                    shareText={`${event.title} — ${formatDateWithLocale(showShareOccurrence.start_date, 'shortDateTime', event.timezone)}`}
                />
            )}
        </PageBody>
    );
};

export default OccurrenceDetail;
