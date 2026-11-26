import {useMemo} from "react";
import {t} from "@lingui/macro";
import {Avatar, Tooltip, VisuallyHidden} from "@mantine/core";
import {IconArmchair, IconCalendarEvent, IconRepeat} from "@tabler/icons-react";
import {Link, useNavigate} from "react-router";
import {Event, EventType} from "../../../types.ts";
import {useAccess} from "../../../hooks/useAccess.ts";
import {useEventActions} from "../../../hooks/useEventActions.tsx";
import {ActionMenu, RowActionsTarget} from "../ActionMenu";
import {StatusBadge, StatusTone} from "../StatusBadge";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellLink, CellText, EmptyCell, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatDateWithLocale, isValidDate, relativeDate} from "../../../utilites/dates.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {
    EventStatusTone,
    getEventDisplayDate,
    getEventLocationText,
    getEventStatusSummary,
    getEventTicketAvailability,
} from "../../../utilites/eventSummary.ts";
import classes from "./EventsTable.module.scss";

interface EventsTableProps {
    events: Event[];
    showOrganizer?: boolean;
    isRefreshing?: boolean;
    sort?: TableSort;
}

const STATUS_TONES: Record<EventStatusTone, StatusTone> = {
    success: 'positive',
    warning: 'waiting',
    muted: 'neutral',
};

const eventDashboardPath = (event: Event) => `/manage/event/${event.id}/dashboard`;

const EventRowActions = ({event}: { event: Event }) => {
    const {menuItems, duplicateModal} = useEventActions(event);

    return (
        <>
            <ActionMenu itemsGroups={menuItems} target={<RowActionsTarget/>}/>
            {duplicateModal}
        </>
    );
};

export const EventsTable = ({events, showOrganizer = false, isRefreshing, sort}: EventsTableProps) => {
    const navigate = useNavigate();
    const access = useAccess();
    const canViewFinancials = access.can('VIEW_FINANCIALS');

    const columns = useMemo<TanStackTableColumn<Event>[]>(() => [
        {
            id: 'event',
            header: t`Event`,
            meta: {sticky: 'left'},
            cell: ({row: {original: event}}) => {
                const coverImage = event.images?.find(image => image.type === 'EVENT_COVER');
                const organizerName = showOrganizer ? event.organizer?.name : undefined;

                return (
                    <IdentityCell
                        stacked={showOrganizer}
                        avatar={(
                            <Avatar
                                className={classes.thumbnail}
                                src={coverImage?.url}
                                alt=""
                                size={36}
                                radius={6}
                                color="gray"
                                variant="light"
                            >
                                <IconCalendarEvent size={18} stroke={1.6}/>
                            </Avatar>
                        )}
                        primary={(
                            <>
                                <CellLink strong to={eventDashboardPath(event)} title={event.title}>
                                    {event.title}
                                </CellLink>
                                {event.type === EventType.RECURRING && (
                                    <Tooltip label={t`Recurring`} withArrow>
                                        <span className={classes.marker} role="img" aria-label={t`Recurring`}>
                                            <IconRepeat size={14}/>
                                        </span>
                                    </Tooltip>
                                )}
                                {event.has_seat_map === true && (
                                    <Tooltip label={t`Reserved seating`} withArrow>
                                        <span className={classes.marker} role="img" aria-label={t`Reserved seating`}>
                                            <IconArmchair size={14}/>
                                        </span>
                                    </Tooltip>
                                )}
                            </>
                        )}
                        secondary={organizerName && access.canManageOrganizer(event.organizer_id) ? (
                            <Link
                                className={classes.organizerLink}
                                to={`/manage/organizer/${event.organizer_id}`}
                                onClick={clickEvent => clickEvent.stopPropagation()}
                            >
                                {organizerName}
                            </Link>
                        ) : organizerName}
                    />
                );
            },
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: event}}) => {
                const status = getEventStatusSummary(event);

                return <StatusBadge tone={STATUS_TONES[status.tone]}>{status.label}</StatusBadge>;
            },
        },
        {
            id: 'date',
            ...sortableHeader(t`Date`, 'start_date', sort, 'asc'),
            cell: ({row: {original: event}}) => {
                const displayDate = getEventDisplayDate(event);
                if (!isValidDate(displayDate)) {
                    return <CellText muted>{t`No date added`}</CellText>;
                }

                return (
                    <IdentityCell
                        stacked
                        primary={formatDateWithLocale(displayDate, 'shortDateTime', event.timezone)}
                        secondary={relativeDate(displayDate)}
                    />
                );
            },
        },
        {
            id: 'location',
            header: t`Location`,
            cell: ({row: {original: event}}) => {
                const location = getEventLocationText(event);

                return location ? <CellText maxWidth={200} title={location}>{location}</CellText> : <EmptyCell/>;
            },
        },
        {
            id: 'tickets',
            header: t`Tickets`,
            cell: ({row: {original: event}}) => {
                const availability = getEventTicketAvailability(event);
                if (!availability) {
                    return <EmptyCell/>;
                }

                return <CellText strong={availability.status === 'sold-out'}>{availability.text}</CellText>;
            },
        },
        {
            id: 'attendees',
            header: t`Attendees`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => {
                const attendees = event.statistics?.attendees_registered || 0;

                return <CellText muted={attendees === 0}>{formatNumber(attendees)}</CellText>;
            },
        },
        ...(canViewFinancials ? [{
            id: 'revenue',
            header: t`Revenue`,
            meta: {align: 'right' as const},
            cell: ({row: {original: event}}: { row: { original: Event } }) => {
                const revenue = event.statistics?.sales_total_gross || 0;

                return (
                    <CellText strong={revenue !== 0} muted={revenue === 0}>
                        {formatCurrency(revenue, event.currency)}
                    </CellText>
                );
            },
        }] : []),
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            meta: {sticky: 'right'},
            cell: ({row: {original: event}}) => <EventRowActions event={event}/>,
        },
    ], [events, showOrganizer, access, canViewFinancials, sort?.sortBy, sort?.sortDirection]);

    return (
        <TanStackTable
            data={events}
            columns={columns}
            noCard
            rowLines={2}
            isRefreshing={isRefreshing}
            onRowClick={event => navigate(eventDashboardPath(event))}
        />
    );
};
