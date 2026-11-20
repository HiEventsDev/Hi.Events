import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {useNavigate, useParams} from "react-router";
import {useState} from "react";
import {SettingsPage, SettingsRow, SettingsSection} from "../../../../../common/SettingsPage";
import {useGetEventDeletionStatus} from "../../../../../../queries/useGetEventDeletionStatus.ts";
import {useDeleteEvent} from "../../../../../../mutations/useDeleteEvent.ts";
import {useUpdateEventStatus} from "../../../../../../mutations/useUpdateEventStatus.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {showSuccess, showError} from "../../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {EventStatus} from "../../../../../../types.ts";
import {IconTrash, IconArchive, IconArrowBackUp} from "@tabler/icons-react";
import {useAccess} from "../../../../../../hooks/useAccess.ts";

export const DangerZoneSettings = () => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const isAdmin = useAccess().can('DELETE_EVENTS');
    const {data: deletionStatus, isLoading: isDeletionStatusLoading} = useGetEventDeletionStatus(eventId!);
    const {data: event} = useGetEvent(eventId!);
    const deleteMutation = useDeleteEvent();
    const statusMutation = useUpdateEventStatus();
    const [deleteConfirmation, setDeleteConfirmation] = useState('');

    const isArchived = event?.status === EventStatus.ARCHIVED;
    const isPendingReview = event?.status === EventStatus.PENDING_MANUAL_REVIEW;
    const deleteConfirmationPhrase = t`delete`;
    const isDeleteConfirmed = deleteConfirmation.trim().toLocaleLowerCase() === deleteConfirmationPhrase.toLocaleLowerCase();

    const handleDelete = () => {
        const organizerId = event?.organizer?.id;
        deleteMutation.mutate({eventId: eventId!}, {
            onSuccess: () => {
                showSuccess(t`Event deleted successfully`);
                navigate(`/manage/organizer/${organizerId}/events`);
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Failed to delete event`);
            }
        });
    };

    const handleArchiveToggle = () => {
        const newStatus = isArchived ? EventStatus.LIVE : EventStatus.ARCHIVED;
        const message = isArchived
            ? t`Are you sure you want to restore this event?`
            : t`Are you sure you want to archive this event? It will no longer be visible to the public.`;

        confirmationDialog(
            message,
            () => {
                statusMutation.mutate({eventId: eventId!, status: newStatus}, {
                    onSuccess: () => {
                        showSuccess(isArchived ? t`Event restored successfully` : t`Event archived successfully`);
                    },
                    onError: (error: any) => {
                        showError(error?.response?.data?.message || t`Failed to update event status`);
                    }
                });
            },
            {confirm: isArchived ? t`Restore` : t`Archive`, cancel: t`Cancel`}
        );
    };

    if (!isAdmin) {
        return (
            <SettingsPage title={t`Danger Zone`}>
                <SettingsSection tone="danger">
                    <SettingsRow
                        label={t`Admin Access Required`}
                        description={t`Only account administrators can delete or archive events. Contact your account admin for assistance.`}
                    />
                </SettingsSection>
            </SettingsPage>
        );
    }

    const canDelete = !!deletionStatus?.can_delete;

    return (
        <SettingsPage title={t`Danger Zone`}>
            <SettingsSection tone="danger">
                <SettingsRow
                    label={t`Delete Event`}
                    description={canDelete
                        ? (
                            <>
                                {t`Permanently delete this event and all its associated data.`}
                                <div>{t`Type "delete" to confirm`}</div>
                            </>
                        )
                        : deletionStatus?.reason || t`Loading...`}
                >
                    {canDelete && (
                        <TextInput
                            aria-label={t`Type "delete" to confirm`}
                            placeholder={deleteConfirmationPhrase}
                            value={deleteConfirmation}
                            onChange={(e) => setDeleteConfirmation(e.currentTarget.value)}
                        />
                    )}
                    <Button
                        color="red"
                        variant="outline"
                        data-testid="event-delete-button"
                        onClick={handleDelete}
                        loading={deleteMutation.isPending}
                        disabled={!canDelete || isDeletionStatusLoading || !isDeleteConfirmed}
                        leftSection={<IconTrash size={16}/>}
                    >
                        {t`Delete Event`}
                    </Button>
                </SettingsRow>
                <SettingsRow
                    label={isArchived ? t`Restore Event` : t`Archive Event`}
                    description={isPendingReview
                        ? t`This event is pending manual review. Its status cannot be changed until the review is complete.`
                        : isArchived
                            ? t`Restore this event to make it visible again.`
                            : t`Archive this event to hide it from the public. You can restore it later.`}
                >
                    <Button
                        color={isArchived ? "blue" : "orange"}
                        variant="outline"
                        onClick={handleArchiveToggle}
                        loading={statusMutation.isPending}
                        disabled={isPendingReview}
                        leftSection={isArchived ? <IconArrowBackUp size={16}/> : <IconArchive size={16}/>}
                    >
                        {isArchived ? t`Restore Event` : t`Archive Event`}
                    </Button>
                </SettingsRow>
            </SettingsSection>
        </SettingsPage>
    );
};
