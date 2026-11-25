import {useMemo, useState} from "react";
import {t} from "@lingui/macro";
import {Tooltip, VisuallyHidden} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {IconAlertTriangle, IconRefresh, IconX} from "@tabler/icons-react";
import {CellContext} from "@tanstack/react-table";
import {
    Event,
    IdParam,
    TicketResaleCloseReason,
    TicketResaleListing,
    TicketResaleListingState,
} from "../../../../types.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useCloseTicketResaleListing} from "../../../../mutations/useCloseTicketResaleListing.ts";
import {useRetryTicketResaleRefund} from "../../../../mutations/useRetryTicketResaleRefund.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {formatDateWithLocale, prettyDate, relativeDate} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {ActionMenu, RowActionsTarget} from "../../../common/ActionMenu";
import {StatusBadge, StatusTone} from "../../../common/StatusBadge";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellCopy, CellLink, CellText, EmptyCell, IdentityCell} from "../../../common/TanStackTable/cells.tsx";
import {ManageAttendeeModal} from "../../../modals/ManageAttendeeModal";
import {ManageOrderModal} from "../../../modals/ManageOrderModal";
import classes from "./Resale.module.scss";

interface ResaleListingsTableProps {
    eventId: IdParam;
    event: Event;
    listings: TicketResaleListing[];
    isRefreshing?: boolean;
}

type ListingCell = CellContext<TicketResaleListing, unknown>;

const closeReasonLabel = (reason: TicketResaleCloseReason | null): string | null => {
    switch (reason) {
        case 'WITHDRAWN':
            return t`Withdrawn by the seller`;
        case 'CUTOFF_PASSED':
            return t`Not sold before resale closed`;
        case 'RESALE_TURNED_OFF':
            return t`Resale was turned off`;
        case 'TICKET_CANCELLED':
            return t`Ticket was cancelled`;
        case 'TICKET_USED':
            return t`Ticket was used`;
        case 'TICKET_TYPE_CHANGED':
            return t`Ticket type was changed`;
        case 'DATE_CANCELLED':
            return t`Date was cancelled`;
        case 'CLOSED_BY_ORGANIZER':
            return t`Closed by an organizer`;
        default:
            return null;
    }
};

const refundStatusLabel = (status: TicketResaleListing['refund_status']): string | null => {
    switch (status) {
        case 'PENDING':
            return t`Pending`;
        case 'SUCCEEDED':
            return t({message: 'Refunded', context: 'Resale refund status'});
        case 'FAILED':
            return t`Failed`;
        default:
            return null;
    }
};

const STATE_TONES: Record<TicketResaleListingState, StatusTone> = {
    OPEN: 'active',
    HELD: 'waiting',
    WITHDRAWAL_REQUESTED: 'waiting',
    NOT_OFFERED: 'neutral',
    SOLD: 'positive',
    CLOSED: 'neutral',
};

const stateLabel = (state: TicketResaleListingState): string => {
    switch (state) {
        case 'OPEN':
            return t({message: 'Open', context: 'Resale listing status'});
        case 'HELD':
            return t`Buyer checking out`;
        case 'WITHDRAWAL_REQUESTED':
            return t`Withdrawal requested`;
        case 'NOT_OFFERED':
            return t`Not offered`;
        case 'SOLD':
            return t({message: 'Sold', context: 'Resale listing status'});
        case 'CLOSED':
            return t`Closed`;
    }
};

const stateDetail = (listing: TicketResaleListing, timezone: string): string | null => {
    if (listing.state === 'CLOSED') {
        return closeReasonLabel(listing.closed_reason);
    }

    if (listing.held_until && (listing.state === 'HELD' || listing.state === 'WITHDRAWAL_REQUESTED')) {
        const heldUntil = formatDateWithLocale(listing.held_until, 'timeOnly', timezone);

        return t`until ${heldUntil}`;
    }

    return null;
};

const ListingStatus = ({listing, timezone}: { listing: TicketResaleListing, timezone: string }) => {
    if (!listing.state) {
        return <EmptyCell/>;
    }

    const badge = <StatusBadge tone={STATE_TONES[listing.state]}>{stateLabel(listing.state)}</StatusBadge>;
    const detail = stateDetail(listing, timezone);

    return (
        <span className={classes.status}>
            {listing.state === 'NOT_OFFERED' ? (
                <Tooltip
                    label={t`Resale is off or has closed for this date; this listing will be closed shortly`}
                    withArrow
                    multiline
                    w={260}
                >
                    <span className={classes.hint} tabIndex={0}>{badge}</span>
                </Tooltip>
            ) : badge}
            {detail && <CellText muted>{detail}</CellText>}
            {listing.needs_attention && (
                <span className={classes.attention}>
                    <IconAlertTriangle size={14} stroke={2} aria-hidden/>
                    {t`Refund failed`}
                </span>
            )}
        </span>
    );
};

const ListingRefund = ({listing}: { listing: TicketResaleListing }) => {
    const currency = listing.currency ?? undefined;

    if (listing.status === 'LISTED' && listing.estimated_refund != null) {
        const amount = formatCurrency(listing.estimated_refund, currency);

        return <CellText muted>{t`${amount} estimated`}</CellText>;
    }

    const statusLabel = refundStatusLabel(listing.refund_status);
    const amount = listing.refund_amount ?? listing.estimated_refund;

    if (listing.status !== 'SOLD' || !statusLabel || amount == null) {
        return <EmptyCell/>;
    }

    return (
        <span className={classes.status}>
            <CellText strong>{formatCurrency(amount, currency)}</CellText>
            <span className={listing.refund_status === 'FAILED' ? classes.failed : classes.refundStatus}>
                {statusLabel}
            </span>
        </span>
    );
};

export const ResaleListingsTable = ({eventId, event, listings, isRefreshing}: ResaleListingsTableProps) => {
    const canIssueRefunds = useAccess().can('ISSUE_REFUNDS');
    const closeMutation = useCloseTicketResaleListing();
    const retryMutation = useRetryTicketResaleRefund();
    const [isAttendeeModalOpen, attendeeModal] = useDisclosure(false);
    const [isOrderModalOpen, orderModal] = useDisclosure(false);
    const [selectedAttendeeId, setSelectedAttendeeId] = useState<IdParam>();
    const [selectedOrderId, setSelectedOrderId] = useState<IdParam>();
    const timezone = event.timezone;
    const showRefunds = listings.some(listing => 'refund_status' in listing);

    const openAttendee = (listing: TicketResaleListing) => {
        setSelectedAttendeeId(listing.attendee.id);
        attendeeModal.open();
    };

    const openOrder = (orderId: IdParam) => {
        setSelectedOrderId(orderId);
        orderModal.open();
    };

    const serverMessage = (error: any, fallback: string): string => error?.response?.data?.message || fallback;

    const handleClose = (listing: TicketResaleListing) => {
        confirmationDialog(
            t`Close this resale listing?`,
            () => closeMutation.mutate({eventId, listingId: listing.id}, {
                onSuccess: () => showSuccess(t`Listing closed. The seller has been emailed.`),
                onError: (error) => showError(serverMessage(error, t`We could not close this listing. Please try again.`)),
            }),
            {
                confirm: t`Close listing`,
                description: t`The ticket goes back to the seller and works as normal. They will be emailed to say the listing was closed.`,
            },
        );
    };

    const handleRetryRefund = (listing: TicketResaleListing) => {
        retryMutation.mutate({eventId, listingId: listing.id}, {
            onSuccess: () => showSuccess(t`Refund retried. It can take a few minutes to go through.`),
            onError: (error) => showError(serverMessage(error, t`We could not retry this refund. Please try again.`)),
        });
    };

    const columns = useMemo<TanStackTableColumn<TicketResaleListing>[]>(
        () => {
            const allColumns: (TanStackTableColumn<TicketResaleListing> | false)[] = [
                {
                    id: 'ticket',
                    header: t`Ticket`,
                    meta: {sticky: 'left', interactive: true},
                    cell: ({row: {original: listing}}: ListingCell) => {
                        const ticket = [listing.product_title, listing.price_label].filter(Boolean).join(' · ');
                        const seller = [
                            [listing.attendee.first_name, listing.attendee.last_name].filter(Boolean).join(' '),
                            listing.attendee.email,
                        ].filter(Boolean).join(' · ');

                        return (
                            <IdentityCell
                                stacked
                                primary={(
                                    <CellLink strong onClick={() => openAttendee(listing)} title={ticket || undefined}>
                                        {ticket || listing.attendee.public_id}
                                    </CellLink>
                                )}
                                secondary={<span title={seller}>{seller}</span>}
                            />
                        );
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    cell: ({row: {original: listing}}: ListingCell) => (
                        <span data-state={listing.state ?? undefined} data-testid="resale-listing-status">
                            <ListingStatus listing={listing} timezone={timezone}/>
                        </span>
                    ),
                },
                {
                    id: 'replacementOrder',
                    header: t`Replacement order`,
                    meta: {interactive: true},
                    cell: ({row: {original: listing}}: ListingCell) => listing.sold_order ? (
                        <CellCopy value={listing.sold_order.public_id}>
                            <CellLink mono onClick={() => openOrder(listing.sold_order?.id)}>
                                {listing.sold_order.public_id}
                            </CellLink>
                        </CellCopy>
                    ) : <EmptyCell/>,
                },
                showRefunds && {
                    id: 'refund',
                    header: t`Refund`,
                    cell: ({row: {original: listing}}: ListingCell) => (
                        <span data-testid="resale-listing-refund">
                            <ListingRefund listing={listing}/>
                        </span>
                    ),
                },
                {
                    id: 'listed',
                    header: t`Listed`,
                    cell: ({row: {original: listing}}: ListingCell) => (
                        <Tooltip label={prettyDate(listing.created_at, timezone)} withArrow openDelay={300}>
                            <span>{relativeDate(listing.created_at)}</span>
                        </Tooltip>
                    ),
                },
                {
                    id: 'actions',
                    header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
                    meta: {sticky: 'right'},
                    cell: ({row: {original: listing}}: ListingCell) => (
                        <ActionMenu
                            dataTestId="resale-listing-actions-trigger"
                            target={<RowActionsTarget/>}
                            itemsGroups={[{
                                label: t`Actions`,
                                items: [
                                    {
                                        label: t`Retry refund`,
                                        icon: <IconRefresh size={14}/>,
                                        onClick: () => handleRetryRefund(listing),
                                        visible: listing.needs_attention && canIssueRefunds,
                                        requiresEdit: true,
                                        dataTestId: 'resale-retry-refund-button',
                                    },
                                    {
                                        label: t`Close listing`,
                                        icon: <IconX size={14}/>,
                                        onClick: () => handleClose(listing),
                                        visible: listing.status === 'LISTED',
                                        requiresEdit: true,
                                        dataTestId: 'resale-close-listing-button',
                                    },
                                ],
                            }]}
                        />
                    ),
                },
            ];

            return allColumns.filter(Boolean) as TanStackTableColumn<TicketResaleListing>[];
        },
        [eventId, timezone, showRefunds, canIssueRefunds],
    );

    return (
        <div className={classes.listings}>
            <TanStackTable
                data={listings}
                columns={columns}
                noCard
                rowLines={2}
                isRefreshing={isRefreshing}
                dataTestId="resale-listings-table"
                onRowClick={openAttendee}
                rowProps={listing => ({'data-attention': listing.needs_attention || undefined})}
            />
            {(selectedAttendeeId && isAttendeeModalOpen) && (
                <ManageAttendeeModal attendeeId={selectedAttendeeId} onClose={attendeeModal.close}/>
            )}
            {(selectedOrderId && isOrderModalOpen) && (
                <ManageOrderModal orderId={selectedOrderId} onClose={orderModal.close}/>
            )}
        </div>
    );
};
