import {useNavigate, useParams} from "react-router";
import {t} from "@lingui/macro";
import {Checkbox, Skeleton, Text} from "@mantine/core";
import {IconChevronLeft} from "@tabler/icons-react";
import {useCallback, useMemo, useRef, useState} from "react";
import {AreaChart} from "@mantine/charts";
import {useDisclosure} from "@mantine/hooks";
import {modals} from "@mantine/modals";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {StatBoxes} from "../../../common/StatBoxes";
import {Card} from "../../../common/Card";
import {OccurrenceAttendeesAndOrders} from "../../../common/OccurrenceAttendeesAndOrders";
import {OccurrenceEditModal} from "../OccurrencesTab/OccurrenceEditModal";
import {SendMessageModal} from "../../../modals/SendMessageModal";
import {ShareModal} from "../../../modals/ShareModal";
import {CreateCheckInListModal} from "../../../modals/CreateCheckInListModal";
import {OccurrenceActionBar, OccurrenceMenuActions, statusLabel} from "../OccurrencesTab/OccurrenceMenu";
import {useGetEventOccurrence} from "../../../../queries/useGetEventOccurrence.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventStats} from "../../../../queries/useGetEventStats.ts";
import {useGetEventCheckInLists} from "../../../../queries/useGetCheckInLists.ts";
import {useCancelOccurrence} from "../../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../../mutations/useDeleteEventOccurrence.ts";
import {useUpdateEventOccurrence} from "../../../../mutations/useUpdateEventOccurrence.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
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
    const {data: eventStats} = useGetEventStats(eventId, occurrenceId);

    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const [showMessageModal, setShowMessageModal] = useState(false);
    const [showShareOccurrence, setShowShareOccurrence] = useState<EventOccurrence | undefined>();
    const [createCheckInForOccurrenceId, setCreateCheckInForOccurrenceId] = useState<number | undefined>();

    const checkInListsQuery = useGetEventCheckInLists(eventId);
    const checkInLists = checkInListsQuery?.data?.data;

    const cancelMutation = useCancelOccurrence();
    const deleteMutation = useDeleteEventOccurrence();
    const updateMutation = useUpdateEventOccurrence();
    const refundRef = useRef(false);

    const handleCheckIn = useCallback((occId: number) => {
        const list = checkInLists?.find(l => l.event_occurrence_id === occId)
            || checkInLists?.find(l => !l.event_occurrence_id);

        if (list) {
            window.open(`/check-in/${list.short_id}`, '_blank');
        } else {
            setCreateCheckInForOccurrenceId(occId);
        }
    }, [checkInLists]);

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
                    navigate(`/manage/event/${eventId}/occurrences`);
                },
                onError: (error: any) => showError(error?.response?.data?.message || t`Failed to delete date`),
            });
        });
    }, [eventId, navigate]);

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
        onEdit: () => openEditModal(),
        onCancel: handleCancel,
        onDelete: handleDelete,
        onNavigate: navigate,
        onMessage: () => setShowMessageModal(true),
        onCheckIn: handleCheckIn,
        onReactivate: handleReactivate,
        onShare: (occ: EventOccurrence) => setShowShareOccurrence(occ),
    }), [eventId, handleCheckIn, handleCancel, handleDelete, handleReactivate, navigate, openEditModal]);

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
            <div
                className={classes.backLink}
                onClick={() => navigate(`/manage/event/${eventId}/occurrences`)}
            >
                <IconChevronLeft size={14}/>
                {t`Back to Occurrence Schedule`}
            </div>

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
                    <OccurrenceActionBar occurrence={occurrence} actions={menuActions}/>
                </div>
            )}

            <div style={{marginBottom: 20}}>
                <StatBoxes occurrenceId={occurrenceId}/>
            </div>

            <OccurrenceAttendeesAndOrders occurrenceId={occurrenceId} perPage={25}/>

            {eventStats && (
                <>
                    <Card className={classes.chartCard}>
                        <div className={classes.chartCardTitle}>
                            <h2>{t`Product Sales`}</h2>
                            <div className={classes.dateRange}><span>{dateRange}</span></div>
                        </div>
                        <AreaChart
                            h={300}
                            data={eventStats.daily_stats?.map(stat => ({
                                date: formatDateWithLocale(stat.date, 'chartDate', event.timezone),
                                orders_created: stat.orders_created,
                                products_sold: stat.products_sold,
                                attendees_registered: stat.attendees_registered,
                            })) || []}
                            dataKey="date"
                            withLegend
                            legendProps={{verticalAlign: 'bottom', height: 50}}
                            series={[
                                {name: 'orders_created', color: 'blue.6', label: t`Completed Orders`},
                                {name: 'products_sold', color: 'blue.2', label: t`Products Sold`},
                                {name: 'attendees_registered', color: 'blue.4', label: t`Attendees Registered`},
                            ]}
                            curveType="bump"
                            tickLine="none"
                            areaChartProps={{syncId: 'occurrences'}}
                        />
                    </Card>

                    <Card className={classes.chartCard}>
                        <div className={classes.chartCardTitle}>
                            <h2>{t`Revenue`}</h2>
                            <div className={classes.dateRange}><span>{dateRange}</span></div>
                        </div>
                        <AreaChart
                            h={300}
                            pl={40}
                            pr={40}
                            data={eventStats.daily_stats?.map(stat => ({
                                date: formatDateWithLocale(stat.date, 'chartDate', event.timezone),
                                total_fees: stat.total_fees,
                                total_sales_gross: stat.total_sales_gross,
                                total_tax: stat.total_tax,
                                total_refunded: stat.total_refunded,
                            })) || []}
                            dataKey="date"
                            valueFormatter={(value) => formatCurrency(value, event.currency)}
                            withLegend
                            legendProps={{verticalAlign: 'bottom', height: 50}}
                            series={[
                                {name: 'total_fees', label: t`Total Fees`, color: 'primary.3'},
                                {name: 'total_sales_gross', label: t`Gross Sales`, color: 'grape.5'},
                                {name: 'total_tax', label: t`Total Tax`, color: 'grape.7'},
                                {name: 'total_refunded', label: t`Total Refunded`, color: 'red.6'},
                            ]}
                            curveType="natural"
                            tickLine="none"
                            areaChartProps={{syncId: 'occurrences'}}
                        />
                    </Card>
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
        </PageBody>
    );
};

export default OccurrenceDetail;
