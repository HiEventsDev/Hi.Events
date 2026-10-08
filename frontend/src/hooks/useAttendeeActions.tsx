import {useState} from "react";
import {t, Trans} from "@lingui/macro";
import {useDisclosure} from "@mantine/hooks";
import {notifications} from "@mantine/notifications";
import {IconMailForward, IconPencil, IconSend, IconTrash, IconUserCog} from "@tabler/icons-react";
import {Attendee, IdParam, MessageType} from "../types.ts";
import {EntityAction} from "../components/common/EntityActions";
import {SendMessageModal} from "../components/modals/SendMessageModal";
import {useModifyAttendee} from "../mutations/useModifyAttendee.ts";
import {useResendAttendeeTicket} from "../mutations/useResendAttendeeTicket.ts";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {confirmationDialog} from "../utilites/confirmationDialog.tsx";

interface UseAttendeeActionsOptions {
    eventId: IdParam;
    onManage?: (attendee: Attendee) => void;
    onEdit?: (attendee: Attendee) => void;
}

export const useAttendeeActions = ({eventId, onManage, onEdit}: UseAttendeeActionsOptions) => {
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee>();
    const modifyMutation = useModifyAttendee();
    const resendTicketMutation = useResendAttendeeTicket();

    const handleMessage = (attendee: Attendee) => {
        setSelectedAttendee(attendee);
        messageModal.open();
    };

    const handleResendTicket = (attendee: Attendee) => {
        resendTicketMutation.mutate({
            attendeeId: attendee.id,
            eventId: eventId,
        }, {
            onSuccess: () => showSuccess(t`Ticket email has been resent to attendee`),
            onError: (error: any) => showError(error.response.data.message || t`Failed to resend ticket email`)
        });
    };

    const handleCancel = (attendee: Attendee) => {
        const isReactivating = attendee.status === 'CANCELLED';
        const seatLabel = attendee.seat_label;
        const cancelMessage = seatLabel
            ? t`Are you sure you want to cancel this attendee? This will void their ticket and release seat ${seatLabel}, which may then be taken by someone else.`
            : t`Are you sure you want to cancel this attendee? This will void their ticket`;
        const message = isReactivating
            ? t`Are you sure you want to activate this attendee?`
            : cancelMessage;

        confirmationDialog(message, () => {
            modifyMutation.mutate({
                attendeeId: attendee.id,
                eventId: eventId,
                attendeeData: {
                    status: isReactivating ? 'ACTIVE' : 'CANCELLED'
                }
            }, {
                onSuccess: () => {
                    notifications.show({
                        message: (
                            <Trans>
                                Successfully {isReactivating ? 'activated' : 'cancelled'} attendee
                            </Trans>
                        ),
                        color: 'green',
                    });
                },
                onError: (error: any) => showError(
                    error?.response?.data?.errors?.status?.[0]
                    ?? error?.response?.data?.message
                    ?? (isReactivating ? t`Failed to activate attendee` : t`Failed to cancel attendee`),
                ),
            });
        });
    };

    const getAttendeeActions = (attendee: Attendee): EntityAction[] => {
        const isCancelled = attendee.status === 'CANCELLED';

        const actions: (EntityAction | false)[] = [
            !!onManage && {
                key: 'manage',
                label: t`Manage attendee`,
                icon: <IconUserCog size={14}/>,
                onClick: () => onManage(attendee),
                group: 'primary',
            },
            !!onEdit && {
                key: 'edit',
                label: t`Edit`,
                icon: <IconPencil size={14}/>,
                onClick: () => onEdit(attendee),
                group: 'primary',
                dataTestId: 'attendee-edit-button',
            },
            !!attendee.email && {
                key: 'message',
                label: t`Message attendee`,
                icon: <IconSend size={14}/>,
                onClick: () => handleMessage(attendee),
                group: 'primary',
            },
            attendee.status === 'ACTIVE' && !!attendee.email && {
                key: 'resend',
                label: t`Resend ticket email`,
                icon: <IconMailForward size={14}/>,
                onClick: () => handleResendTicket(attendee),
                group: 'primary',
            },
            {
                key: 'cancel',
                label: isCancelled ? t`Activate` : t`Cancel` + ` ` + t`ticket`,
                icon: <IconTrash size={14}/>,
                onClick: () => handleCancel(attendee),
                group: 'danger',
                color: isCancelled ? 'green' : 'red',
            },
        ];

        return actions.filter(Boolean) as EntityAction[];
    };

    const attendeeActionModals = selectedAttendee && isMessageModalOpen && (
        <SendMessageModal
            onClose={messageModal.close}
            orderId={selectedAttendee.order_id}
            attendeeId={selectedAttendee.id}
            messageType={MessageType.IndividualAttendees}
            eventOccurrenceId={selectedAttendee.event_occurrence_id}
        />
    );

    return {getAttendeeActions, attendeeActionModals, openMessageModal: handleMessage};
};
