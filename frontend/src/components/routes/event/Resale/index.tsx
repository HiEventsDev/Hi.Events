import {useState} from "react";
import {useParams} from "react-router";
import {plural, t} from "@lingui/macro";
import {SegmentedControl, Skeleton, Switch, UnstyledButton} from "@mantine/core";
import {IconAlertTriangle, IconInfoCircle} from "@tabler/icons-react";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useGetTicketResaleListings} from "../../../../queries/useGetTicketResaleListings.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilterOperator, QueryFilters} from "../../../../types.ts";
import {NoResultsSplash} from "../../../common/NoResultsSplash";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {ToolBar} from "../../../common/ToolBar";
import {ResaleListingsTable} from "./ResaleListingsTable.tsx";
import {ResaleSettings} from "./ResaleSettings.tsx";
import classes from "./Resale.module.scss";

const ALL_STATUSES = 'ALL';
const SOLD_STATUS = 'SOLD';

const Resale = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: eventSettings} = useGetEventSettings(eventId);
    const {isReadOnly} = useAccess();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [onlyNeedsAttention, setOnlyNeedsAttention] = useState(false);
    const listingsQuery = useGetTicketResaleListings(eventId, searchParams as QueryFilters);
    const pagination = listingsQuery.data?.meta;
    const pageListings = listingsQuery.data?.data;
    const status = (searchParams.filterFields?.status as { value?: string } | undefined)?.value ?? ALL_STATUSES;
    const isAttentionFilterActive = status === SOLD_STATUS && onlyNeedsAttention;
    const listings = isAttentionFilterActive
        ? pageListings?.filter(listing => listing.needs_attention)
        : pageListings;
    const attentionCount = pageListings?.filter(listing => listing.needs_attention).length ?? 0;
    const isResaleOff = eventSettings !== undefined && !eventSettings.allow_ticket_resale;

    const showStatus = (value: string) => {
        setSearchParams({
            pageNumber: 1,
            filterFields: {
                ...(searchParams.filterFields || {}),
                status: value === ALL_STATUSES ? undefined : {operator: QueryFilterOperator.Equals, value},
            },
        }, true);
    };

    const showOnlyNeedsAttention = () => {
        setOnlyNeedsAttention(true);
        if (status !== SOLD_STATUS) {
            showStatus(SOLD_STATUS);
        }
    };

    const emptyMessage = () => {
        if (isAttentionFilterActive) {
            return t`No refunds on this page need attention.`;
        }

        return status === ALL_STATUSES
            ? t`No tickets have been listed for resale yet.`
            : t`No listings match this filter.`;
    };

    return (
        <PageBody>
            <PageTitle subheading={t`Tickets buyers have listed for resale, who bought them and what the seller was refunded.`}>
                {t`Ticket resale`}
            </PageTitle>

            {!isReadOnly && !eventSettings && <Skeleton height={62} radius="md" mb={16}/>}

            {!isReadOnly && eventSettings && <ResaleSettings eventId={eventId} eventSettings={eventSettings}/>}

            {isReadOnly && isResaleOff && (
                <p className={classes.notice} data-testid="resale-off-notice">
                    <IconInfoCircle size={16} stroke={1.8} aria-hidden/>
                    <span>{t`Ticket resale is off for this event.`}</span>
                </p>
            )}

            <TableCard>
                <ToolBar
                    embedded
                    className={classes.toolbar}
                    filterComponent={(
                        <SegmentedControl
                            size="sm"
                            aria-label={t`Filter by status`}
                            value={status}
                            onChange={showStatus}
                            data={[
                                {value: ALL_STATUSES, label: t`All`},
                                {value: 'LISTED', label: t({message: 'Open', context: 'Resale listing status'})},
                                {value: SOLD_STATUS, label: t({message: 'Sold', context: 'Resale listing status'})},
                                {value: 'CLOSED', label: t`Closed`},
                            ]}
                        />
                    )}
                >
                    {status === SOLD_STATUS && (
                        <Switch
                            size="sm"
                            label={t`Needs attention`}
                            checked={onlyNeedsAttention}
                            onChange={changeEvent => setOnlyNeedsAttention(changeEvent.currentTarget.checked)}
                        />
                    )}
                </ToolBar>

                {attentionCount > 0 && (
                    <div className={classes.attentionSummary} data-testid="resale-attention-summary">
                        <IconAlertTriangle size={16} stroke={2} aria-hidden/>
                        <span className={classes.attentionCount}>
                            {plural(attentionCount, {one: '# refund needs attention', other: '# refunds need attention'})}
                        </span>
                        <span>
                            {t`The seller has not been refunded yet. Retry the refund, or refund them another way.`}
                        </span>
                        {!isAttentionFilterActive && (
                            <UnstyledButton className={classes.attentionAction} onClick={showOnlyNeedsAttention}>
                                {t`Show only these`}
                            </UnstyledButton>
                        )}
                    </div>
                )}

                {(!listings || !event) && <TableCardSkeleton rows={5}/>}

                {(listings && event && listings.length > 0) && (
                    <ResaleListingsTable
                        eventId={eventId}
                        event={event}
                        listings={listings}
                        isRefreshing={listingsQuery.isFetching}
                    />
                )}

                {(listings && event && listings.length === 0) && (
                    status === ALL_STATUSES
                        ? <NoResultsSplash heading={emptyMessage()} imageHref="/blank-slate/tickets.svg"/>
                        : <p className={classes.empty}>{emptyMessage()}</p>
                )}

                {(!!pageListings?.length && (!isAttentionFilterActive || (pagination?.last_page ?? 1) > 1)) && (
                    <TableCardFooter
                        pagination={pagination}
                        page={searchParams.pageNumber}
                        onPageChange={(value) => setSearchParams({pageNumber: value})}
                    />
                )}
            </TableCard>
        </PageBody>
    );
};

export default Resale;
