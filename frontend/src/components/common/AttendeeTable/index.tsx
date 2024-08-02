import {Anchor, Avatar, Badge, Button, Table as MantineTable,} from '@mantine/core';
import {Attendee, MessageType} from "../../../types.ts";
import {IconEye, IconMailForward, IconPencil, IconPlus, IconSend, IconTrash} from "@tabler/icons-react";
import {getInitials, getTicketFromEvent} from "../../../utilites/helpers.ts";
import {Table, TableHead} from "../Table";
import {useDisclosure} from "@mantine/hooks";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {useParams} from "react-router-dom";
import {EditAttendeeModal} from "../../modals/EditAttendeeModal";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import Truncate from "../Truncate";
import {notifications} from "@mantine/notifications";
import {useModifyAttendee} from "../../../mutations/useModifyAttendee.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useResendAttendeeTicket} from "../../../mutations/useResendAttendeeTicket.ts";
import {ViewAttendeeModal} from "../../modals/ViewAttendeeModal";
import {ActionMenu} from '../ActionMenu/index.tsx';

interface AttendeeTableProps {
    attendees: Attendee[];
    openCreateModal: () => void;
}

export const AttendeeTable = ({attendees, openCreateModal}: AttendeeTableProps) => {
    const {eventId} = useParams();
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [isEditModalOpen, editModal] = useDisclosure(false);
    const [isViewModalOpem, viewModalOpen] = useDisclosure(false);
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee>();
    const {data: event} = useGetEvent(eventId);
    const modifyMutation = useModifyAttendee();
    const resendTicketMutation = useResendAttendeeTicket();

    const handleModalClick = (attendee: Attendee, modal: {
        open: () => void
    }) => {
        setSelectedAttendee(attendee);
        modal.open();
    }

    const handleResendTicket = (attendee: Attendee) => {
        resendTicketMutation.mutate({
            attendeeId: attendee.id,
            eventId: eventId,
        }, {
            onSuccess: () => showSuccess(t`Ticket email has been resent to attendee`),
            onError: (error: any) => showError(error.response.data.message || t`Failed to resend ticket email`)
        });
    }

    if (attendees.length === 0) {
        return <NoResultsSplash
            heading={t`No Attendees to show`}
            imageHref={'/blank-slate/attendees.svg'}
            subHeading={(
                <>
                    <p>
                        {t`Your attendees will appear here once they have registered for your event. You can also manually add attendees.`}
                    </p>
                    <Button
                        size={'xs'}
                        leftSection={<IconPlus/>}
                        color={'green'}
                        onClick={() => openCreateModal()}>{t`Manually add an Attendee`}
                    </Button>
                </>
            )}
        />
    }

    const handleCancel = (attendee: Attendee) => {
        const message = attendee.status === 'CANCELLED'
            ? t`Are you sure you want to activate this attendee?`
            : t`Are you sure you want to cancel this attendee? This will void their ticket`

        confirmationDialog(message, () => {
            modifyMutation.mutate({
                attendeeId: attendee.id,
                eventId: eventId,
                attendeeData: {
                    status: attendee.status === 'CANCELLED' ? 'ACTIVE' : 'CANCELLED'
                }
            }, {
                onSuccess: () => {
                    notifications.show({
                        message: (
                            <Trans>
                                Successfully {attendee.status === 'CANCELLED' ? 'activated' : 'cancelled'} attendee
                            </Trans>
                        ),
                        color: 'green',
                    });
                },
                onError: () => showError(t`Failed to cancel attendee`),
            });
        })
    };

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th></MantineTable.Th>
                        <MantineTable.Th>{t`Name`}</MantineTable.Th>
                        <MantineTable.Th>{t`Email`}</MantineTable.Th>
                        <MantineTable.Th>{t`Order`}</MantineTable.Th>
                        <MantineTable.Th>{t`Ticket`}</MantineTable.Th>
                        <MantineTable.Th>{t`Status`}</MantineTable.Th>
                        <MantineTable.Th></MantineTable.Th>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {attendees.map((attendee) => {
                        return (
                            <MantineTable.Tr key={attendee.id}>
                                <MantineTable.Td>
                                    <Avatar
                                        size={40}>{getInitials(attendee.first_name + ' ' + attendee.last_name)}</Avatar>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <b>
                                        <Truncate length={20}
                                                  text={attendee.first_name + ' ' + attendee.last_name}/>
                                    </b>
                                    <div>
                                        {attendee.public_id}
                                    </div>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Anchor target={'_blank'} href={`mailto:${attendee.email}`}>
                                        <Truncate length={25} text={attendee.email}/>
                                    </Anchor>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Anchor
                                        href={`/manage/event/${eventId}/orders#order-${attendee.order?.id}`}>
                                        <Badge variant={'outline'}>
                                            {attendee.order?.public_id}
                                        </Badge>
                                    </Anchor>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Truncate
                                        text={getTicketFromEvent(attendee.ticket_id, event)?.title}
                                        length={25}
                                    />
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Badge
                                        variant={'light'}
                                        color={attendee.status === 'CANCELLED' ? 'red' : 'green'}>{attendee.status}</Badge>
                                </MantineTable.Td>
                                <MantineTable.Td style={{paddingRight: 0}}>
                                    <ActionMenu itemsGroups={[
                                        {
                                            label: t`Actions`,
                                            items: [
                                                {
                                                    label: t`View attendee`,
                                                    icon: <IconEye size={14}/>,
                                                    onClick: () => handleModalClick(attendee, viewModalOpen),
                                                },
                                                {
                                                    label: t`Message attendee`,
                                                    icon: <IconSend size={14}/>,
                                                    onClick: () => handleModalClick(attendee, messageModal),
                                                },
                                                {
                                                    label: t`Edit attendee`,
                                                    icon: <IconPencil size={14}/>,
                                                    onClick: () => handleModalClick(attendee, editModal),
                                                },
                                                {
                                                    label: t`Resend ticket email`,
                                                    icon: <IconMailForward size={14}/>,
                                                    onClick: () => handleResendTicket(attendee),
                                                    visible: attendee.status === 'ACTIVE',
                                                },
                                            ],
                                        },
                                        {
                                            label: t`Danger Zone`,
                                            items: [
                                                {
                                                    label: attendee.status === 'CANCELLED' ? t`Activate` : t`Cancel` + ` ` + t`ticket`,
                                                    icon: <IconTrash size={14}/>,
                                                    onClick: () => handleCancel(attendee),
                                                    color: attendee.status === 'CANCELLED' ? 'green' : 'red',
                                                },
                                            ],
                                        },
                                    ]}/>
                                </MantineTable.Td>
                            </MantineTable.Tr>
                        );
                    })}
                </MantineTable.Tbody>
            </Table>
            {(selectedAttendee && isMessageModalOpen) && <SendMessageModal
                onClose={messageModal.close}
                orderId={selectedAttendee.order_id}
                attendeeId={selectedAttendee.id}
                messageType={MessageType.Attendee}
            />}
            {(selectedAttendee?.id && isEditModalOpen) && <EditAttendeeModal
                attendeeId={selectedAttendee.id}
                onClose={editModal.close}
            />}

            {(selectedAttendee?.id && isViewModalOpem) && <ViewAttendeeModal
                attendeeId={selectedAttendee.id}
                onClose={viewModalOpen.close}
            />}
        </>

    );
};
