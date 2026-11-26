import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {useNavigate} from "react-router";
import {useDisclosure} from "@mantine/hooks";
import {IconArchive, IconCopy, IconEye, IconSettings} from "@tabler/icons-react";
import {Event} from "../types.ts";
import {useAccess} from "./useAccess.ts";
import {useUpdateEventStatus} from "../mutations/useUpdateEventStatus.ts";
import {ActionMenuItemsGroup} from "../components/common/ActionMenu";
import {DuplicateEventModal} from "../components/modals/DuplicateEventModal";
import {confirmationDialog} from "../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {eventHomepagePath} from "../utilites/urlHelper.ts";

interface EventActions {
    menuItems: ActionMenuItemsGroup[];
    duplicateModal: ReactNode;
}

export const useEventActions = (event: Event): EventActions => {
    const navigate = useNavigate();
    const access = useAccess();
    const [isDuplicateModalOpen, duplicateModal] = useDisclosure(false);
    const statusToggleMutation = useUpdateEventStatus();

    const handleStatusToggle = () => {
        if (event.status === 'PENDING_MANUAL_REVIEW') {
            showError(t`This event is pending manual review and its status cannot be changed until the review is complete.`);
            return;
        }

        const message = event.status !== 'ARCHIVED'
            ? t`Are you sure you want to archive this event?`
            : t`Are you sure you want to restore this event? It will be restored as a draft event.`;

        confirmationDialog(message, () => {
            statusToggleMutation.mutate({
                eventId: event.id,
                status: event.status === 'ARCHIVED' ? 'DRAFT' : 'ARCHIVED'
            }, {
                onSuccess: () => {
                    showSuccess(t`Event status updated`);
                },
                onError: (error: any) => {
                    showError(error?.response?.data?.message || t`Event status update failed. Please try again later`);
                }
            });
        });
    };

    const menuItems: ActionMenuItemsGroup[] = [
        {
            label: '',
            items: [
                {
                    label: t`View event page`,
                    icon: <IconEye size={14}/>,
                    onClick: () => window.location.href = eventHomepagePath(event),
                },
                {
                    label: t`Manage event`,
                    icon: <IconSettings size={14}/>,
                    onClick: () => navigate(`/manage/event/${event.id}`),
                },
                {
                    label: t`Duplicate event`,
                    icon: <IconCopy size={14}/>,
                    onClick: duplicateModal.open,
                    dataTestId: 'event-duplicate-menu-item',
                    visible: !access.isReadOnly && access.canManageOrganizer(event.organizer_id),
                },
                {
                    label: event.status === 'ARCHIVED' ? t`Restore event` : t`Archive event`,
                    icon: <IconArchive size={14}/>,
                    onClick: handleStatusToggle,
                    visible: !access.isReadOnly,
                },
            ],
        },
    ];

    return {
        menuItems,
        duplicateModal: isDuplicateModalOpen
            ? <DuplicateEventModal eventId={event.id} onClose={duplicateModal.close}/>
            : null,
    };
};
