import {t} from "@lingui/macro";
import {Button, Alert, TextInput, Stack, Text} from "@mantine/core";
import {useNavigate, useParams} from "react-router";
import {useState} from "react";
import {DangerZone, DangerZoneSection} from "../../../../../common/DangerZone";
import {useGetEventDeletionStatus} from "../../../../../../queries/useGetEventDeletionStatus.ts";
import {useDeleteEvent} from "../../../../../../mutations/useDeleteEvent.ts";
import {useUpdateEventStatus} from "../../../../../../mutations/useUpdateEventStatus.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {showSuccess, showError} from "../../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {EventStatus} from "../../../../../../types.ts";
import {IconInfoCircle, IconTrash, IconArchive, IconArrowBackUp} from "@tabler/icons-react";
import {useIsCurrentUserAdmin} from "../../../../../../hooks/useIsCurrentUserAdmin.ts";
import {BouncingEmoji} from "../../../../../common/BouncingEmoji";

export const DangerZoneSettings = () => {
    const {eventId} = useParams();
    const navigate = useNavigate();
    const isAdmin = useIsCurrentUserAdmin();
    const {data: deletionStatus, isLoading: isDeletionStatusLoading} = useGetEventDeletionStatus(eventId!);
    const {data: event} = useGetEvent(eventId!);
    const deleteMutation = useDeleteEvent();
    const statusMutation = useUpdateEventStatus();
    const [deleteConfirmation, setDeleteConfirmation] = useState('');

    const isArchived = event?.status === EventStatus.ARCHIVED;
    const isDeleteConfirmed = deleteConfirmation.toLowerCase() === 'delete';

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
            <DangerZone>
                <div style={{textAlign: 'center', padding: '20px 0'}}>
                    <BouncingEmoji emoji="✋"/>
                    <h3>{t`Admin Access Required`}</h3>
                    <Text size="sm" c="dimmed">
                        {t`Only account administrators can delete or archive events. Contact your account admin for assistance.`}
                    </Text>
                </div>
            </DangerZone>
        );
    }

    return (
        <DangerZone>
            <DangerZoneSection
                title={t`Delete Event`}
                description={
                    deletionStatus?.can_delete
                        ? t`Permanently delete this event and all its associated data.`
                        : deletionStatus?.reason || t`Loading...`
                }
                action={
                    <>
                        {!isDeletionStatusLoading && !deletionStatus?.can_delete && (
                            <Alert icon={<IconInfoCircle size={16}/>} variant="light" color="gray" mb="sm">
                                {deletionStatus?.reason}
                            </Alert>
                        )}
                        {deletionStatus?.can_delete && (
                            <Stack gap="xs" maw={400}>
                                <Text size="sm" c="dimmed">
                                    {t`Type "delete" to confirm`}
                                </Text>
                                <TextInput
                                    placeholder={t`delete`}
                                    value={deleteConfirmation}
                                    onChange={(e) => setDeleteConfirmation(e.currentTarget.value)}
                                />
                            </Stack>
                        )}
                        <Button
                            mt="sm"
                            color="red"
                            variant="outline"
                            onClick={handleDelete}
                            loading={deleteMutation.isPending}
                            disabled={!deletionStatus?.can_delete || isDeletionStatusLoading || !isDeleteConfirmed}
                            leftSection={<IconTrash size={16}/>}
                        >
                            {t`Delete Event`}
                        </Button>
                    </>
                }
            />
            <DangerZoneSection
                title={isArchived ? t`Restore Event` : t`Archive Event`}
                description={
                    isArchived
                        ? t`Restore this event to make it visible again.`
                        : t`Archive this event to hide it from the public. You can restore it later.`
                }
                action={
                    <Button
                        color={isArchived ? "blue" : "orange"}
                        variant="outline"
                        onClick={handleArchiveToggle}
                        loading={statusMutation.isPending}
                        leftSection={isArchived ? <IconArrowBackUp size={16}/> : <IconArchive size={16}/>}
                    >
                        {isArchived ? t`Restore Event` : t`Archive Event`}
                    </Button>
                }
            />
        </DangerZone>
    );
};
