import {ActionIcon, Avatar, Button, Group, Popover, Tooltip, VisuallyHidden} from '@mantine/core';
import {CreateButton} from "../CreateButton";
import {Attendee, EventType, IdParam} from "../../../types.ts";
import {IconCheck, IconCopy, IconNote, IconPlus, IconSend} from "@tabler/icons-react";
import {getInitials, getProductFromEvent} from "../../../utilites/helpers.ts";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import {useParams} from "react-router";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventCheckInLists} from "../../../queries/useGetCheckInLists.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {ManageAttendeeModal} from "../../modals/ManageAttendeeModal";
import {ManageOrderModal} from "../../modals/ManageOrderModal";
import {ActionMenu, RowActionsTarget} from '../ActionMenu';
import {toActionMenuGroups} from "../EntityActions";
import {useAttendeeActions} from "../../../hooks/useAttendeeActions.tsx";
import {CheckInStatusModal} from "../CheckInStatusModal";
import {formatDateWithLocale, prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellCopy, CellLink, CellText, EmptyCell, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {StatusBadge, StatusTone} from "../StatusBadge";
import {CellContext} from "@tanstack/react-table";
import classes from './AttendeeTable.module.scss';
import {WriteAccess} from "../WriteAccess";

interface AttendeeTableProps {
    attendees: Attendee[];
    openCreateModal?: () => void;
    compact?: boolean;
    embedded?: boolean;
    isRefreshing?: boolean;
    sort?: TableSort;
    columnToggleSlot?: HTMLElement | null;
    occurrenceId?: IdParam;
}

const ATTENDEE_STATUS_TONES: Record<string, StatusTone> = {
    ACTIVE: 'positive',
    AWAITING_PAYMENT: 'waiting',
    CANCELLED: 'negative',
};

export const AttendeeTable = ({attendees, openCreateModal, compact, embedded, isRefreshing, sort, columnToggleSlot, occurrenceId}: AttendeeTableProps) => {
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

    const handleCopyEmail = (email: string | null) => {
        if (!email) {
            return;
        }
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

    const openAttendee = (attendee: Attendee) => handleModalClick(attendee, viewModalOpen);
    const hasSeats = attendees.some(attendee => attendee.seat_label);
    const hasOccurrences = event?.type === EventType.RECURRING && attendees.some(attendee => attendee.event_occurrence);

    const columns = useMemo<TanStackTableColumn<Attendee>[]>(
        () => {
            const renderEmail = (attendee: Attendee) => {
                if (!attendee.email) {
                    return <CellText muted>{t`No email provided`}</CellText>;
                }

                return (
                    <Popover
                        opened={emailPopoverId === attendee.id}
                        onChange={(opened) => {
                            if (!opened) setEmailPopoverId(null);
                        }}
                        width={200}
                        position="bottom-start"
                        shadow="md"
                    >
                        <Popover.Target>
                            <span className={classes.emailTarget}>
                                <CellLink onClick={() => setEmailPopoverId(attendee.id || null)} title={attendee.email}>
                                    {attendee.email}
                                </CellLink>
                            </span>
                        </Popover.Target>
                        <Popover.Dropdown>
                            <Group gap="xs" style={{flexDirection: 'column', width: '100%'}}>
                                <WriteAccess>
                                    <Button
                                        fullWidth
                                        variant="light"
                                        leftSection={<IconSend size={16}/>}
                                        onClick={() => handleMessageFromEmail(attendee)}
                                    >
                                        {t`Message`}
                                    </Button>
                                </WriteAccess>
                                <Button
                                    fullWidth
                                    variant="light"
                                    color="gray"
                                    leftSection={<IconCopy size={16}/>}
                                    onClick={() => handleCopyEmail(attendee.email)}
                                >
                                    {t`Copy Email`}
                                </Button>
                            </Group>
                        </Popover.Dropdown>
                    </Popover>
                );
            };

            const allColumns: (TanStackTableColumn<Attendee> | false)[] = [
                {
                    id: 'attendee',
                    enableHiding: false,
                    ...sortableHeader(t`Attendee`, 'first_name', sort, 'asc'),
                    meta: {label: t`Attendee`, sticky: 'left', interactive: true},
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => {
                        const name = [attendee.first_name, attendee.last_name].filter(Boolean).join(' ');
                        const notes = attendee.notes;

                        return (
                            <IdentityCell
                                stacked
                                avatar={!compact && (
                                    <Avatar size={30} radius="xl" color="primary" variant="light" className={classes.avatar}>
                                        {getInitials(name)}
                                    </Avatar>
                                )}
                                primary={(
                                    <CellLink strong onClick={() => openAttendee(attendee)} title={name || undefined}>
                                        {name || attendee.public_id}
                                    </CellLink>
                                )}
                                secondary={renderEmail(attendee)}
                                trailing={notes && (
                                    <Tooltip
                                        label={notes.length > 100 ? t`Click to view notes` : notes}
                                        multiline
                                        w={notes.length > 100 ? 'auto' : 300}
                                        withArrow
                                    >
                                        <ActionIcon
                                            size="sm"
                                            variant="subtle"
                                            color="teal"
                                            aria-label={t`Notes`}
                                            onClick={() => openAttendee(attendee)}
                                        >
                                            <IconNote size={15} stroke={1.6}/>
                                        </ActionIcon>
                                    </Tooltip>
                                )}
                            />
                        );
                    },
                },
                {
                    id: 'reference',
                    header: t`Reference`,
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => (
                        <CellCopy value={attendee.public_id}>
                            <CellText mono>{attendee.public_id}</CellText>
                        </CellCopy>
                    ),
                },
                {
                    id: 'ticket',
                    ...sortableHeader(t`Ticket`, 'ticket_name', sort, 'asc'),
                    meta: {label: t`Ticket`},
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => {
                        const ticketTitle = getProductFromEvent(attendee.product_id, event)?.title;

                        return ticketTitle ? <CellText maxWidth={220} title={ticketTitle}>{ticketTitle}</CellText> : <EmptyCell/>;
                    },
                },
                hasSeats && {
                    id: 'seat',
                    header: t`Seat`,
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => attendee.seat_label ?? <EmptyCell/>,
                },
                hasOccurrences && {
                    id: 'occurrence',
                    header: t`Date`,
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => {
                        const occurrence = attendee.event_occurrence;
                        if (!occurrence || !event?.timezone) {
                            return <EmptyCell/>;
                        }

                        return (
                            <CellText maxWidth={240}>
                                {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                                {' '}
                                {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                                {occurrence.label && ` · ${occurrence.label}`}
                            </CellText>
                        );
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => {
                        const resale = attendee.resale;

                        if (resale?.status === 'SOLD') {
                            return (
                                <span className={classes.resold}>
                                    <StatusBadge tone="neutral">{t`Resold`}</StatusBadge>
                                    {resale.sold_order_id && (
                                        <CellLink
                                            mono
                                            title={t`Replacement order`}
                                            onClick={() => handleOrderClick(resale.sold_order_id ?? undefined)}
                                        >
                                            {resale.sold_order_public_id}
                                        </CellLink>
                                    )}
                                </span>
                            );
                        }

                        if (resale?.status === 'LISTED') {
                            return <StatusBadge tone="waiting">{t`Listed for resale`}</StatusBadge>;
                        }

                        return (
                            <StatusBadge tone={ATTENDEE_STATUS_TONES[attendee.status] ?? 'neutral'}>
                                {attendee.status === 'ACTIVE' && t`Active`}
                                {attendee.status === 'AWAITING_PAYMENT' && t`Awaiting Payment`}
                                {attendee.status === 'CANCELLED' && t`Cancelled`}
                            </StatusBadge>
                        );
                    },
                },
                hasCheckInLists && {
                    id: 'checkIn',
                    header: t`Check-In Status`,
                    meta: {interactive: true},
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => {
                        const checkInCount = getCheckInCount(attendee);
                        const totalLists = relevantCheckInLists.length;

                        return (
                            <button
                                className={classes.checkInButton}
                                data-checked-in={checkInCount > 0 || undefined}
                                onClick={() => handleModalClick(attendee, checkInModal)}
                            >
                                {checkInCount > 0 ? (
                                    <>
                                        <IconCheck size={14}/>
                                        {t`Checked In`} ({checkInCount}/{totalLists})
                                    </>
                                ) : t`Not Checked In`}
                            </button>
                        );
                    },
                },
                {
                    id: 'order',
                    header: t`Order`,
                    meta: {interactive: true},
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => attendee.order?.public_id ? (
                        <CellCopy value={attendee.order.public_id}>
                            <CellLink mono onClick={() => handleOrderClick(attendee.order_id)}>
                                {attendee.order.public_id}
                            </CellLink>
                        </CellCopy>
                    ) : <EmptyCell/>,
                },
                {
                    id: 'created',
                    ...sortableHeader(t`Created`, 'created_at', sort),
                    meta: {label: t`Created`},
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => attendee.order?.created_at && event?.timezone ? (
                        <Tooltip label={prettyDate(attendee.order.created_at, event.timezone)} withArrow openDelay={300}>
                            <span>{relativeDate(attendee.order.created_at)}</span>
                        </Tooltip>
                    ) : <EmptyCell/>,
                },
                {
                    id: 'actions',
                    header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
                    enableHiding: false,
                    meta: {sticky: 'right'},
                    cell: ({row: {original: attendee}}: CellContext<Attendee, unknown>) => (
                        <ActionMenu
                            dataTestId="attendee-actions-trigger"
                            itemsGroups={toActionMenuGroups(getAttendeeActions(attendee))}
                            target={<RowActionsTarget/>}
                        />
                    ),
                },
            ];

            return allColumns.filter(Boolean) as TanStackTableColumn<Attendee>[];
        },
        [emailPopoverId, event, hasCheckInLists, relevantCheckInLists, occurrenceId, hasSeats, hasOccurrences, compact, sort?.sortBy, sort?.sortDirection]
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
                        <CreateButton
                            size={'xs'}
                            leftSection={<IconPlus/>}
                            color={'green'}
                            onClick={() => openCreateModal()}>{t`Manually add an Attendee`}
                        </CreateButton>
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
                storageKey="attendees-list"
                enableColumnVisibility={!compact}
                columnToggleSlot={columnToggleSlot}
                hideHeader={compact}
                noCard={compact || embedded}
                isRefreshing={isRefreshing}
                rowLines={2}
                onRowClick={openAttendee}
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
