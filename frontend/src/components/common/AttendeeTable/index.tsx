import {ActionIcon, Anchor, Avatar, Button, Group, Popover, Tooltip} from '@mantine/core';
import {Attendee, IdParam} from "../../../types.ts";
import {
    IconCalendarEvent,
    IconCheck,
    IconClock,
    IconClipboardList,
    IconCopy,
    IconNote,
    IconPlus,
    IconSend,
    IconX
} from "@tabler/icons-react";
import {getInitials, getProductFromEvent} from "../../../utilites/helpers.ts";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventCheckInLists} from "../../../queries/useGetCheckInLists.ts";
import Truncate from "../Truncate";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {ManageAttendeeModal} from "../../modals/ManageAttendeeModal";
import {ManageOrderModal} from "../../modals/ManageOrderModal";
import {ActionMenu} from '../ActionMenu';
import {toActionMenuGroups} from "../EntityActions";
import {useAttendeeActions} from "../../../hooks/useAttendeeActions.tsx";
import {CheckInStatusModal} from "../CheckInStatusModal";
import {formatDateWithLocale, prettyDate} from "../../../utilites/dates.ts";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {ColumnVisibilityToggle} from "../ColumnVisibilityToggle";
import {CellContext} from "@tanstack/react-table";
import classes from './AttendeeTable.module.scss';

interface AttendeeTableProps {
    attendees: Attendee[];
    openCreateModal?: () => void;
    compact?: boolean;
    occurrenceId?: IdParam;
}

export const AttendeeTable = ({attendees, openCreateModal, compact, occurrenceId}: AttendeeTableProps) => {
    const {eventId} = useParams();
    const [isViewModalOpen, viewModalOpen] = useDisclosure(false);
    const [isCheckInModalOpen, checkInModal] = useDisclosure(false);
    const [isOrderModalOpen, orderModal] = useDisclosure(false);
    const [emailPopoverId, setEmailPopoverId] = useState<number | null>(null);
    const [selectedAttendee, setSelectedAttendee] = useState<Attendee>();
    const [selectedOrderId, setSelectedOrderId] = useState<IdParam>();
    const {data: event} = useGetEvent(eventId);
    const {data: checkInLists} = useGetEventCheckInLists(eventId);
    const clipboard = useClipboard({timeout: 2000});
    const {getAttendeeActions, attendeeActionModals, openMessageModal} = useAttendeeActions({
        eventId,
        onManage: (attendee: Attendee) => handleModalClick(attendee, viewModalOpen),
    });

    const relevantCheckInLists = checkInLists?.data?.filter(list =>
        !occurrenceId || !list.event_occurrence_id || list.event_occurrence_id === Number(occurrenceId)
    ) || [];

    const hasCheckInLists = relevantCheckInLists.length > 0;

    const handleModalClick = (attendee: Attendee, modal: {
        open: () => void
    }) => {
        setSelectedAttendee(attendee);
        modal.open();
    }

    const getCheckInCount = (attendee: Attendee) => {
        if (!attendee.check_ins) return 0;
        if (!occurrenceId) return attendee.check_ins.length;
        return attendee.check_ins.filter(ci => ci.event_occurrence_id === Number(occurrenceId)).length;
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
        openMessageModal(attendee);
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
                        const attendee = info.row.original;
                        const ticketTitle = getProductFromEvent(attendee.product_id, event)?.title;
                        const occurrence = attendee.event_occurrence;
                        return (
                            <div className={classes.orderTicketContainer}>
                                <div className={classes.ticketName}>
                                    <Truncate
                                        text={ticketTitle}
                                        length={25}
                                    />
                                </div>
                                {occurrence && event?.timezone && (
                                    <span className={classes.occurrenceChip}>
                                        <IconCalendarEvent size={12}/>
                                        {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                                        {' '}
                                        {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                                        {occurrence.label && ` · ${occurrence.label}`}
                                    </span>
                                )}
                                <div className={classes.orderId}>
                                    <Anchor
                                        onClick={() => handleOrderClick(attendee.order_id)}
                                        style={{cursor: 'pointer', color: 'inherit', textDecoration: 'none'}}
                                    >
                                        {attendee.order?.public_id}
                                    </Anchor>
                                </div>
                                {attendee.order?.created_at && event?.timezone && (
                                    <div className={classes.registrationDate}>
                                        {prettyDate(attendee.order.created_at, event.timezone)}
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
                        const totalLists = relevantCheckInLists.length;

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
                            <ActionMenu
                                dataTestId="attendee-actions-trigger"
                                itemsGroups={toActionMenuGroups(getAttendeeActions(info.row.original))}
                            />
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
        [emailPopoverId, event, hasCheckInLists, relevantCheckInLists, occurrenceId]
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
                    {openCreateModal && (
                        <Button
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Manually add an Attendee`}
                        </Button>
                    )}
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
                enableColumnVisibility={!compact}
                renderColumnVisibilityToggle={!compact ? (table) => <ColumnVisibilityToggle table={table}/> : undefined}
                hideHeader={compact}
                noCard={compact}
            />
            {attendeeActionModals}
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
