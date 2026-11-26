import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {useNavigate, useParams} from "react-router";
import {useState} from "react";
import {SettingsPage, SettingsRow, SettingsSection} from "../../../../../common/SettingsPage";
import {useGetOrganizerDeletionStatus} from "../../../../../../queries/useGetOrganizerDeletionStatus.ts";
import {useDeleteOrganizer} from "../../../../../../mutations/useDeleteOrganizer.ts";
import {useUpdateOrganizerStatus} from "../../../../../../mutations/useUpdateOrganizerStatus.ts";
import {useGetOrganizer} from "../../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizers} from "../../../../../../queries/useGetOrganizers.ts";
import {showSuccess, showError} from "../../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {OrganizerStatus} from "../../../../../../types.ts";
import {IconTrash, IconArchive, IconArrowBackUp} from "@tabler/icons-react";
import {useAccess} from "../../../../../../hooks/useAccess.ts";

export const DangerZoneSettings = () => {
    const {organizerId} = useParams();
    const navigate = useNavigate();
    const isAdmin = useAccess().can('DELETE_EVENTS');
    const {data: deletionStatus, isLoading: isDeletionStatusLoading} = useGetOrganizerDeletionStatus(organizerId!);
    const {data: organizer} = useGetOrganizer(organizerId!);
    const {data: organizers} = useGetOrganizers();
    const deleteMutation = useDeleteOrganizer();
    const statusMutation = useUpdateOrganizerStatus();
    const [deleteConfirmation, setDeleteConfirmation] = useState('');

    const isArchived = organizer?.status === OrganizerStatus.ARCHIVED;
    const deleteConfirmationPhrase = t`delete`;
    const isDeleteConfirmed = deleteConfirmation.trim().toLocaleLowerCase() === deleteConfirmationPhrase.toLocaleLowerCase();

    const activeOrganizerCount = organizers?.data?.filter(
        org => org.status !== OrganizerStatus.ARCHIVED
    ).length ?? 0;
    const isLastActiveOrganizer = !isArchived && activeOrganizerCount <= 1;

    const handleDelete = () => {
        deleteMutation.mutate({organizerId: organizerId!}, {
            onSuccess: () => {
                showSuccess(t`Organizer deleted successfully`);
                navigate('/manage/events');
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Failed to delete organizer`);
            }
        });
    };

    const handleArchiveToggle = () => {
        const newStatus = isArchived ? OrganizerStatus.LIVE : OrganizerStatus.ARCHIVED;
        const message = isArchived
            ? t`Are you sure you want to restore this organizer?`
            : t`Are you sure you want to archive this organizer? This will also archive all events belonging to this organizer.`;

        confirmationDialog(
            message,
            () => {
                statusMutation.mutate({organizerId: organizerId!, status: newStatus}, {
                    onSuccess: () => {
                        showSuccess(isArchived ? t`Organizer restored successfully` : t`Organizer archived successfully`);
                    },
                    onError: (error: any) => {
                        showError(error?.response?.data?.message || t`Failed to update organizer status`);
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
                        description={t`Only account administrators can delete or archive organizers. Contact your account admin for assistance.`}
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
                    label={t`Delete Organizer`}
                    description={
                        canDelete
                            ? t`Permanently delete this organizer and all its events.`
                            : deletionStatus?.reason || t`Loading...`
                    }
                    below={canDelete && (
                        <TextInput
                            id="organizer-delete-confirmation"
                            label={t`Type "delete" to confirm`}
                            placeholder={deleteConfirmationPhrase}
                            value={deleteConfirmation}
                            onChange={(e) => setDeleteConfirmation(e.currentTarget.value)}
                            maw={400}
                        />
                    )}
                >
                    <Button
                        color="red"
                        variant="outline"
                        onClick={handleDelete}
                        loading={deleteMutation.isPending}
                        disabled={!canDelete || isDeletionStatusLoading || !isDeleteConfirmed}
                        leftSection={<IconTrash size={16}/>}
                    >
                        {t`Delete Organizer`}
                    </Button>
                </SettingsRow>
                <SettingsRow
                    label={isArchived ? t`Restore Organizer` : t`Archive Organizer`}
                    description={
                        isArchived
                            ? t`Restore this organizer and make it active again.`
                            : isLastActiveOrganizer
                                ? t`You cannot archive the last active organizer on your account.`
                                : t`Archive this organizer. This will also archive all events belonging to this organizer.`
                    }
                >
                    <Button
                        color={isArchived ? "blue" : "orange"}
                        variant="outline"
                        onClick={handleArchiveToggle}
                        loading={statusMutation.isPending}
                        disabled={!isArchived && isLastActiveOrganizer}
                        leftSection={isArchived ? <IconArrowBackUp size={16}/> : <IconArchive size={16}/>}
                    >
                        {isArchived ? t`Restore Organizer` : t`Archive Organizer`}
                    </Button>
                </SettingsRow>
            </SettingsSection>
        </SettingsPage>
    );
};
