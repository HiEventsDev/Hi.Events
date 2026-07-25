import {useParams} from "react-router";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {PageTitle} from "../../../common/PageTitle";
import {PageBody} from "../../../common/PageBody";
import {WaitlistTable} from "../../../common/WaitlistTable";
import {WaitlistStatsCards} from "../../../common/WaitlistStats";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Pagination} from "../../../common/Pagination";
import {Button, Group, Select} from "@mantine/core";
import {ToolBar} from "../../../common/ToolBar";
import {useGetEventWaitlistEntries} from "../../../../queries/useGetEventWaitlistEntries.ts";
import {useGetWaitlistStats} from "../../../../queries/useGetWaitlistStats.ts";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {EventType, QueryFilterOperator, QueryFilters, WaitlistEntryStatus} from "../../../../types.ts";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {t} from "@lingui/macro";
import {useDisclosure} from "@mantine/hooks";
import {OfferWaitlistModal} from "../../../modals/OfferWaitlistModal";
import {IconSend} from "@tabler/icons-react";
import {useGetEventOccurrences} from "../../../../queries/useGetEventOccurrences.ts";
import {OccurrenceSelect} from "../../../common/OccurrenceSelect";
import {SortSelector} from "../../../common/SortSelector";

export const SoldOutWaitlist = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const entriesQuery = useGetEventWaitlistEntries(eventId, searchParams as QueryFilters);
    const entries = entriesQuery?.data?.data;
    const pagination = entriesQuery?.data?.meta;
    const selectedOccurrenceId = (searchParams.filterFields?.event_occurrence_id as {value?: string})?.value || null;
    const {data: stats} = useGetWaitlistStats(eventId, selectedOccurrenceId);
    const {data: eventSettings} = useGetEventSettings(eventId);
    const [offerModalOpen, {open: openOfferModal, close: closeOfferModal}] = useDisclosure(false);
    const isRecurring = event?.type === EventType.RECURRING;
    const {data: occurrencesData} = useGetEventOccurrences(
        eventId,
        {pageNumber: 1, perPage: 1200} as QueryFilters,
        true,
        {includeStats: false},
    );
    const occurrences = occurrencesData?.data || [];

    const handleOccurrenceFilter = (value: string | null) => {
        setSearchParams({
            pageNumber: 1,
            filterFields: {
                ...(searchParams.filterFields || {}),
                event_occurrence_id: value
                    ? {operator: QueryFilterOperator.Equals, value}
                    : undefined,
            },
        }, true);
    };

    const handleStatusFilter = (value: string | null) => {
        setSearchParams({
            pageNumber: 1,
            filterFields: {
                ...(searchParams.filterFields || {}),
                status: value
                    ? {operator: QueryFilterOperator.Equals, value}
                    : undefined,
            },
        }, true);
    };

    return (
        <PageBody>
            <PageTitle
            subheading={t`Manage your event's waitlist, view stats, and offer tickets to attendees.`}
            >{t`Waitlist`}</PageTitle>

            {stats && <WaitlistStatsCards stats={stats}/>}

            <ToolBar
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by name or email...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                    />
                )}
                filterComponent={(
                    <Group gap="sm" wrap="wrap">
                        {pagination?.allowed_sorts && (
                            <SortSelector
                                selected={searchParams.sortBy && searchParams.sortDirection
                                    ? searchParams.sortBy + ':' + searchParams.sortDirection
                                    : pagination.default_sort + ':' + pagination.default_sort_direction}
                                options={pagination.allowed_sorts}
                                onSortSelect={(key, sortDirection) => {
                                    setSearchParams({sortBy: key, sortDirection});
                                }}
                            />
                        )}
                        {isRecurring && occurrences.length > 0 && (
                            <OccurrenceSelect
                                occurrences={occurrences}
                                timezone={event?.timezone || 'UTC'}
                                value={selectedOccurrenceId}
                                onChange={handleOccurrenceFilter}
                                placeholder={t`All Dates`}
                                clearable
                            />
                        )}
                        <Select
                            placeholder={t`All Statuses`}
                            clearable
                            size="sm"
                            mb={0}
                            value={(searchParams.filterFields?.status as {value?: string})?.value || null}
                            onChange={handleStatusFilter}
                            data={[
                                {value: WaitlistEntryStatus.Waiting, label: t`Waiting`},
                                {value: WaitlistEntryStatus.Offered, label: t`Offered`},
                                {value: WaitlistEntryStatus.Purchased, label: t`Purchased`},
                                {value: WaitlistEntryStatus.OfferExpired, label: t`Expired`},
                                {value: WaitlistEntryStatus.Cancelled, label: t`Cancelled`},
                            ]}
                        />
                    </Group>
                )}
                resultCount={pagination?.total}
                resultLabel={t`entries`}
            >
                <Button
                    size="sm"
                    color="green"
                    leftSection={<IconSend size={16}/>}
                    data-testid="waitlist-offer-next-button"
                    onClick={openOfferModal}
                >
                    {t`Offer Tickets`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!entries || !event}/>

            {(entries && event) && (
                <WaitlistTable
                    eventId={eventId}
                    entries={entries}
                    event={event}
                />
            )}

            {!!entries?.length && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={(value) => setSearchParams({pageNumber: value})}
                    total={Number(pagination?.last_page)}
                />
            )}

            {(offerModalOpen && eventId) && (
                <OfferWaitlistModal
                    onClose={closeOfferModal}
                    eventId={eventId}
                    eventSettings={eventSettings}
                    stats={stats}
                    eventOccurrenceId={selectedOccurrenceId}
                    isRecurring={isRecurring}
                />
            )}
        </PageBody>
    );
};

export default SoldOutWaitlist;
