import {ActionIcon, Anchor, Avatar, Button, Group, Popover, Tooltip} from '@mantine/core';
import {Attendee, IdParam, MessageType} from "../../../types.ts";
import {
    IconCheck,
    IconClock,
    IconClipboardList,
    IconCopy,
    IconMailForward,
    IconNote,
    IconPlus,
    IconSend,
    IconTrash,
    IconUserCog,
    IconX
} from "@tabler/icons-react";
import {getInitials, getProductFromEvent} from "../../../utilites/helpers.ts";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {SendMessageModal} from "../../modals/SendMessageModal";
import {useMemo, useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventCheckInLists} from "../../../queries/useGetCheckInLists.ts";
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
import {CheckInStatusModal} from "../CheckInStatusModal";
import {prettyDate} from "../../../utilites/dates.ts";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {ColumnVisibilityToggle} from "../ColumnVisibilityToggle";
import {CellContext} from "@tanstack/react-table";
import classes from './AttendeeTable.module.scss';

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
    const {data: checkInLists} = useGetEventCheckInLists(eventId);
    const modifyMutation = useModifyAttendee();
    const resendTicketMutation = useResendAttendeeTicket();
    const clipboard = useClipboard({timeout: 2000});

    const hasCheckInLists = checkInLists?.data && checkInLists.data.length > 0;

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

    const handleCopyEmail = (email: string) => {
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

    const columns = useMemo<TanStackTableColumn<Attendee>[]>(
        () => {
            const allColumns: TanStackTableColumn<Attendee>[] = [
                {
                    id: 'attendeeDetails',
                    header: t`Attendee Details`,
                    enableHiding: false,
                    cell: (info: CellContext<Attendee, unknown>) => (
                        <Group gap="sm" wrap="nowrap">
                            <Avatar size={44} radius={10} color="primary" variant="light">
                                {getInitials(info.row.original.first_name + ' ' + info.row.original.last_name)}
                            </Avatar>
                            <div className={classes.attendeeDetails}>
                                <div className={classes.nameRow}>
                                    <Anchor
                                        className={classes.attendeeName}
                                        onClick={() => handleModalClick(info.row.original, viewModalOpen)}
                                        style={{cursor: 'pointer'}}
                                    >
                                        <Truncate
                                            length={30}
                                            text={info.row.original.first_name + ' ' + info.row.original.last_name}
                                        />
                                    </Anchor>
                                    <div className={classes.attendeeId}>
                                        {info.row.original.public_id}
                                    </div>
                                </div>
                                <div className={classes.emailRow}>
                                    <Popover
                                        opened={emailPopoverId === info.row.original.id}
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
                                                onClick={() => setEmailPopoverId(info.row.original.id || null)}
                                                className={classes.attendeeEmail}
                                                style={{cursor: 'pointer'}}
                                            >
                                                {info.row.original.email}
                                            </Anchor>
                                        </Popover.Target>
                                        <Popover.Dropdown>
                                            <Group gap="xs" style={{flexDirection: 'column', width: '100%'}}>
                                                <Button
                                                    fullWidth
                                                    variant="light"
                                                    leftSection={<IconSend size={16}/>}
                                                    onClick={() => handleMessageFromEmail(info.row.original)}
                                                >
                                                    {t`Message`}
                                                </Button>
                                                <Button
                                                    fullWidth
                                                    variant="light"
                                                    color="gray"
                                                    leftSection={<IconCopy size={16}/>}
                                                    onClick={() => handleCopyEmail(info.row.original.email)}
                                                >
                                                    {t`Copy Email`}
                                                </Button>
                                            </Group>
                                        </Popover.Dropdown>
                                    </Popover>
                                    <div className={classes.emailActions}>
                                        {info.row.original.notes && (
                                            <Tooltip
                                                label={
                                                    info.row.original.notes.length > 100
                                                        ? t`Click to view notes`
                                                        : info.row.original.notes
                                                }
                                                multiline
                                                w={info.row.original.notes.length > 100 ? 'auto' : 300}
                                                withArrow
                                            >
                                                <ActionIcon
                                                    className={classes.actionIcon}
                                                    size="xs"
                                                    variant="subtle"
                                                    color="green"
                                                    onClick={() => {
                                                        if (info.row.original.notes && info.row.original.notes.length > 100) {
                                                            handleModalClick(info.row.original, viewModalOpen);
                                                        }
                                                    }}
                                                >
                                                    <IconNote size={16}/>
                                                </ActionIcon>
                                            </Tooltip>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </Group>
                    ),
                    meta: {
                        headerStyle: {minWidth: 300},
                    },
                },
                {
                    id: 'orderAndTicket',
                    header: t`Order & Ticket`,
                    enableHiding: true,
                    cell: (info: CellContext<Attendee, unknown>) => {
                        const ticketTitle = getProductFromEvent(info.row.original.product_id, event)?.title;
                        return (
                            <div className={classes.orderTicketContainer}>
                                <div className={classes.ticketName}>
                                    <Truncate
                                        text={ticketTitle}
                                        length={25}
                                    />
                                </div>
                                <div className={classes.orderId}>
                                    <Anchor
                                        onClick={() => handleOrderClick(info.row.original.order_id)}
                                        style={{cursor: 'pointer', color: 'inherit', textDecoration: 'none'}}
                                    >
                                        {info.row.original.order?.public_id}
                                    </Anchor>
                                </div>
                                {info.row.original.order?.created_at && event?.timezone && (
                                    <div className={classes.registrationDate}>
                                        {prettyDate(info.row.original.order.created_at, event.timezone)}
                                    </div>
                                )}
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 160},
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    enableHiding: true,
                    cell: (info: CellContext<Attendee, unknown>) => {
                        const attendee = info.row.original;
                        return (
                            <div className={classes.statusBadge} data-status={attendee.status}>
                                {attendee.status === 'ACTIVE' && (
                                    <>
                                        <IconCheck size={14}/>
                                        {t`Active`}
                                    </>
                                )}
                                {attendee.status === 'AWAITING_PAYMENT' && (
                                    <>
                                        <IconClock size={14}/>
                                        {t`Awaiting Payment`}
                                    </>
                                )}
                                {attendee.status === 'CANCELLED' && (
                                    <>
                                        <IconX size={14}/>
                                        {t`Cancelled`}
                                    </>
                                )}
                            </div>
                        );
                    },
                    meta: {
                        headerStyle: {minWidth: 120},
                    },
                },
                {
                    id: 'checkIn',
                    header: t`Check-In Status`,
                    enableHiding: true,
                    cell: (info: CellContext<Attendee, unknown>) => {
                        const checkInCount = getCheckInCount(info.row.original);
                        const hasChecked = hasCheckIns(info.row.original);
                        const totalLists = checkInLists?.data?.length || 0;

                        return (
                            <button
                                className={`${classes.checkInButton} ${hasChecked ? classes.checkedIn : classes.notCheckedIn}`}
                                onClick={() => handleModalClick(info.row.original, checkInModal)}
                            >
                                {hasChecked ? (
                                    <>
                                        <IconCheck size={16}/>
                                        {t`Checked In`} ({checkInCount}/{totalLists})
                                    </>
                                ) : (
                                    <>
                                        <IconClipboardList size={16}/>
                                        {t`Not Checked In`}
                                    </>
                                )}
                            </button>
                        );
                    },
                    meta: {
                        headerStyle: {width: 80, textAlign: 'center'},
                        cellStyle: {textAlign: 'center'},
                    },
                },
                {
                    id: 'actions',
                    header: '',
                    enableHiding: false,
                    cell: (info: CellContext<Attendee, unknown>) => (
                        <div className={classes.actionsMenu}>
                            <ActionMenu itemsGroups={[
                                {
                                    label: t`Actions`,
                                    items: [
                                        {
                                            label: t`Manage attendee`,
                                            icon: <IconUserCog size={14}/>,
                                            onClick: () => handleModalClick(info.row.original, viewModalOpen),
                                        },
                                        {
                                            label: t`Message attendee`,
                                            icon: <IconSend size={14}/>,
                                            onClick: () => handleModalClick(info.row.original, messageModal),
                                        },
                                        {
                                            label: t`Resend ticket email`,
                                            icon: <IconMailForward size={14}/>,
                                            onClick: () => handleResendTicket(info.row.original),
                                            visible: info.row.original.status === 'ACTIVE',
                                        },
                                    ],
                                },
                                {
                                    label: t`Danger Zone`,
                                    items: [
                                        {
                                            label: info.row.original.status === 'CANCELLED' ? t`Activate` : t`Cancel` + ` ` + t`ticket`,
                                            icon: <IconTrash size={14}/>,
                                            onClick: () => handleCancel(info.row.original),
                                            color: info.row.original.status === 'CANCELLED' ? 'green' : 'red',
                                        },
                                    ],
                                },
                            ]}/>
                        </div>
                    ),
                    meta: {
                        sticky: 'right',
                        cellStyle: {paddingRight: 0},
                    },
                },
            ];

            return allColumns.filter(column => {
                if (column.id === 'checkIn' && !hasCheckInLists) {
                    return false;
                }
                return true;
            });
        },
        [emailPopoverId, event, hasCheckInLists]
    );

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

    return (
        <>
            <TanStackTable
                data={attendees}
                columns={columns}
                storageKey="attendee-table"
                enableColumnVisibility={true}
                renderColumnVisibilityToggle={(table) => <ColumnVisibilityToggle table={table}/>}
            />
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
