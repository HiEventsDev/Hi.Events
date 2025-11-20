import {Anchor, Avatar, Badge, Button, Table as MantineTable, Tooltip, ActionIcon, Popover, Group} from '@mantine/core';
import {Attendee, MessageType} from "../../../types.ts";
import {IconMailForward, IconPlus, IconSend, IconTrash, IconUserCog, IconQrcode, IconNote, IconCopy} from "@tabler/icons-react";
import {getInitials, getProductFromEvent} from "../../../utilites/helpers.ts";
import {Table, TableHead} from "../Table";
import {useDisclosure, useClipboard} from "@mantine/hooks";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import Truncate from "../Truncate";
import {notifications} from "@mantine/notifications";
import {useModifyAttendee} from "../../../mutations/useModifyAttendee.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useResendAttendeeTicket} from "../../../mutations/useResendAttendeeTicket.ts";
import {ManageAttendeeModal} from "../../modals/ManageAttendeeModal";
import {ManageOrderModal} from "../../modals/ManageOrderModal";
import {ActionMenu} from '../ActionMenu';
import {AttendeeStatusBadge} from "../AttendeeStatusBadge";
import {CheckInStatusModal} from "../CheckInStatusModal";
import {prettyDate} from "../../../utilites/dates.ts";
import {IdParam} from "../../../types.ts";

interface AttendeeTableProps {
    attendees: Attendee[];
    openCreateModal: () => void;
}

export const AttendeeTable = ({attendees, openCreateModal}: AttendeeTableProps) => {
    const {eventId} = useParams();
    const [isMessageModalOpen, messageModal] = useDisclosure(false);
    const [isViewModalOpen, viewModalOpen] = useDisclosure(false);
    const [isCheckInModalOpen, checkInModal] = useDisclosure(false);
    const [isOrderModalOpen, orderModal] = useDisclosure(false);
    const [emailPopoverId, setEmailPopoverId] = useState<number | null>(null);
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee>();
    const [selectedOrderId, setSelectedOrderId] = useState<IdParam>();
    const {data: event} = useGetEvent(eventId);
    const modifyMutation = useModifyAttendee();
    const resendTicketMutation = useResendAttendeeTicket();
    const clipboard = useClipboard({timeout: 2000});

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

    const getCheckInCount = (attendee: Attendee) => {
        return attendee.check_ins?.length || 0;
    };

    const hasCheckIns = (attendee: Attendee) => {
        return getCheckInCount(attendee) > 0;
    };

    const handleCopyEmail = (email: string, attendeeId: number | undefined) => {
        clipboard.copy(email);
        showSuccess(t`Email address copied to clipboard`);
        setEmailPopoverId(null);
    };

    const handleMessageFromEmail = (attendee: Attendee) => {
        setEmailPopoverId(null);
        handleModalClick(attendee, messageModal);
    };

    const handleOrderClick = (orderId: IdParam) => {
        setSelectedOrderId(orderId);
        orderModal.open();
    };

    return (
        <>
            <Table>
                <TableHead>
                    <MantineTable.Tr>
                        <MantineTable.Th></MantineTable.Th>
                        <MantineTable.Th>{t`Name`}</MantineTable.Th>
                        <MantineTable.Th>{t`Email`}</MantineTable.Th>
                        <MantineTable.Th miw={140}>{t`Order`}</MantineTable.Th>
                        <MantineTable.Th>{t`Ticket`}</MantineTable.Th>
                        <MantineTable.Th miw={120}>{t`Status`}</MantineTable.Th>
                        <MantineTable.Th w={60} style={{textAlign: 'center'}}>{t`Check-In Status`}</MantineTable.Th>
                        <MantineTable.Th w={60}></MantineTable.Th>
                        <MantineTable.Th></MantineTable.Th>
                    </MantineTable.Tr>
                </TableHead>
                <MantineTable.Tbody>
                    {attendees.map((attendee) => {
                        const checkInCount = getCheckInCount(attendee);
                        const hasChecked = hasCheckIns(attendee);

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
                                    <Popover
                                        opened={emailPopoverId === attendee.id}
                                        onChange={(opened) => {
                                            if (!opened) setEmailPopoverId(null);
                                        }}
                                        width={200}
                                        position="bottom"
                                        withArrow
                                        shadow="md"
                                    >
                                        <Popover.Target>
                                            <Anchor
                                                onClick={() => setEmailPopoverId(attendee.id || null)}
                                                style={{cursor: 'pointer'}}
                                            >
                                                <Truncate length={25} text={attendee.email}/>
                                            </Anchor>
                                        </Popover.Target>
                                        <Popover.Dropdown>
                                            <Group gap="xs" style={{flexDirection: 'column', width: '100%'}}>
                                                <Button
                                                    fullWidth
                                                    variant="light"
                                                    leftSection={<IconSend size={16}/>}
                                                    onClick={() => handleMessageFromEmail(attendee)}
                                                >
                                                    {t`Message`}
                                                </Button>
                                                <Button
                                                    fullWidth
                                                    variant="light"
                                                    color="gray"
                                                    leftSection={<IconCopy size={16}/>}
                                                    onClick={() => handleCopyEmail(attendee.email, attendee.id)}
                                                >
                                                    {t`Copy Email`}
                                                </Button>
                                            </Group>
                                        </Popover.Dropdown>
                                    </Popover>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Tooltip
                                        label={
                                            attendee.order?.created_at && event?.timezone
                                                ? t`Registered: ${prettyDate(attendee.order.created_at, event.timezone)}`
                                                : t`Order details`
                                        }
                                        withArrow
                                    >
                                        <Anchor
                                            onClick={() => handleOrderClick(attendee.order_id)}
                                            style={{cursor: 'pointer'}}
                                        >
                                            <Badge variant={'outline'} style={{cursor: 'pointer'}}>
                                                {attendee.order?.public_id}
                                            </Badge>
                                        </Anchor>
                                    </Tooltip>
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <Truncate
                                        text={getProductFromEvent(attendee.product_id, event)?.title}
                                        length={25}
                                    />
                                </MantineTable.Td>
                                <MantineTable.Td>
                                    <AttendeeStatusBadge attendee={attendee}/>
                                </MantineTable.Td>
                                <MantineTable.Td style={{textAlign: 'center'}}>
                                    <Tooltip
                                        label={
                                            hasChecked
                                                ? t`Checked into ${checkInCount} list(s)`
                                                : t`Not checked in`
                                        }
                                        withArrow
                                    >
                                        <ActionIcon
                                            variant="subtle"
                                            color={hasChecked ? 'green' : 'gray'}
                                            onClick={() => handleModalClick(attendee, checkInModal)}
                                            aria-label={t`View check-in status`}
                                        >
                                            <IconQrcode size={18}/>
                                            {hasChecked && (
                                                <Badge
                                                    size="xs"
                                                    circle
                                                    variant="filled"
                                                    color="green"
                                                    style={{
                                                        position: 'absolute',
                                                        top: -2,
                                                        right: -2,
                                                        minWidth: 16,
                                                        height: 16,
                                                        padding: '0 4px'
                                                    }}
                                                >
                                                    {checkInCount}
                                                </Badge>
                                            )}
                                        </ActionIcon>
                                    </Tooltip>
                                </MantineTable.Td>
                                <MantineTable.Td style={{textAlign: 'center'}}>
                                    {attendee.notes && (
                                        <Tooltip
                                            label={
                                                attendee.notes.length > 100
                                                    ? t`Click to view notes`
                                                    : attendee.notes
                                            }
                                            multiline
                                            w={attendee.notes.length > 100 ? 'auto' : 300}
                                            withArrow
                                        >
                                            <ActionIcon
                                                variant="subtle"
                                                onClick={() => {
                                                    if (attendee.notes && attendee.notes.length > 100) {
                                                        handleModalClick(attendee, viewModalOpen);
                                                    }
                                                }}
                                                aria-label={t`View notes`}
                                            >
                                                <IconNote size={18}/>
                                            </ActionIcon>
                                        </Tooltip>
                                    )}
                                </MantineTable.Td>
                                <MantineTable.Td style={{paddingRight: 0}}>
                                    <ActionMenu itemsGroups={[
                                        {
                                            label: t`Actions`,
                                            items: [
                                                {
                                                    label: t`Manage attendee`,
                                                    icon: <IconUserCog size={14}/>,
                                                    onClick: () => handleModalClick(attendee, viewModalOpen),
                                                },
                                                {
                                                    label: t`Message attendee`,
                                                    icon: <IconSend size={14}/>,
                                                    onClick: () => handleModalClick(attendee, messageModal),
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
                messageType={MessageType.IndividualAttendees}
            />}
            {(selectedAttendee?.id && isViewModalOpen) && <ManageAttendeeModal
                attendeeId={selectedAttendee.id}
                onClose={viewModalOpen.close}
            />}
            {(selectedAttendee && isCheckInModalOpen && event?.timezone) && <CheckInStatusModal
                attendee={selectedAttendee}
                eventTimezone={event.timezone}
                eventId={eventId}
                isOpen={isCheckInModalOpen}
                onClose={checkInModal.close}
            />}
            {(selectedOrderId && isOrderModalOpen) && <ManageOrderModal
                orderId={selectedOrderId}
                onClose={orderModal.close}
            />}
        </>

    );
};
