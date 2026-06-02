import {EventOccurrence, GenericModalProps, IdParam, MessageType} from "../../../types.ts";
import {useNavigate, useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventOccurrence} from "../../../queries/useGetEventOccurrence.ts";
import {t} from "@lingui/macro";
import {useCallback, useMemo, useState} from "react";
import {Anchor, Progress, Skeleton, Stack, Text} from "@mantine/core";
import {IconMapPin, IconWorld} from "@tabler/icons-react";
import {getEventLocationDisplay} from "../../../utilites/effectiveLocation.ts";
import {OccurrenceAttendeesAndOrders} from "../../common/OccurrenceAttendeesAndOrders";
import {SideDrawer} from "../../common/SideDrawer";
import {SendMessageModal} from "../SendMessageModal";
import {ShareModal} from "../ShareModal";
import {OccurrenceEditModal} from "../../routes/event/OccurrencesTab/OccurrenceEditModal";
import {OccurrenceActionBar, OccurrenceMenuActions} from "../../routes/event/OccurrencesTab/OccurrenceMenu";
import {statusLabel} from "../../routes/event/OccurrencesTab/OccurrenceMenu";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useCancelOccurrence} from "../../../mutations/useCancelOccurrence.ts";
import {useDeleteEventOccurrence} from "../../../mutations/useDeleteEventOccurrence.ts";
import {useReactivateOccurrence} from "../../../mutations/useReactivateOccurrence.ts";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {openCancelOccurrenceDialog} from "../../routes/event/OccurrencesTab/cancelOccurrenceDialog";
import {useOccurrenceCheckIn} from "../../../hooks/useOccurrenceCheckIn.tsx";
import classes from './ManageOccurrenceModal.module.scss';

interface ManageOccurrenceModalProps {
    occurrenceId: IdParam;
}

export const ManageOccurrenceModal = ({onClose, occurrenceId}: GenericModalProps & ManageOccurrenceModalProps) => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const {data: occurrence} = useGetEventOccurrence(eventId, occurrenceId);
    const {data: event} = useGetEvent(eventId);
    const {launchCheckIn, checkInModals} = useOccurrenceCheckIn(eventId);

    const [showMessageModal, setShowMessageModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showShareOccurrence, setShowShareOccurrence] = useState<EventOccurrence | undefined>();

    const cancelMutation = useCancelOccurrence();
    const deleteMutation = useDeleteEventOccurrence();
    const reactivateMutation = useReactivateOccurrence();

    const handleCancel = useCallback((occId: number) => {
        openCancelOccurrenceDialog({
            eventId,
            occurrenceId: occId,
            orderCount: occurrence?.statistics?.orders_created ?? 0,
            mutation: cancelMutation,
        });
    }, [occurrence, eventId, cancelMutation]);

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
        onEdit: () => setShowEditModal(true),
        onCancel: handleCancel,
        onDelete: handleDelete,
        onNavigate: (path: string) => {
            onClose();
            navigate(path);
        },
        onMessage: () => setShowMessageModal(true),
        onCheckIn: launchCheckIn,
        onReactivate: handleReactivate,
        onShare: (occ: EventOccurrence) => setShowShareOccurrence(occ),
    }), [eventId, launchCheckIn, handleCancel, handleDelete, handleReactivate, onClose, navigate]);

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
    const locationDisplay = getEventLocationDisplay(event, occurrence);

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
                        {locationDisplay && (
                            <div className={classes.location}>
                                {locationDisplay.isOnline
                                    ? <IconWorld size={13} className={classes.locationIcon}/>
                                    : <IconMapPin size={13} className={classes.locationIcon}/>}
                                {locationDisplay.isOnline ? (
                                    <span className={classes.locationText}>{t`Online event`}</span>
                                ) : locationDisplay.mapsUrl ? (
                                    <Anchor
                                        href={locationDisplay.mapsUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={classes.locationText}
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        {locationDisplay.venueName && (
                                            <span className={classes.locationVenue}>{locationDisplay.venueName}</span>
                                        )}
                                        <span>{locationDisplay.full}</span>
                                    </Anchor>
                                ) : (
                                    <span className={classes.locationText}>{locationDisplay.short}</span>
                                )}
                            </div>
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
        </SideDrawer>
    );
};
