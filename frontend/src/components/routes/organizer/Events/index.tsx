import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {QueryFilters} from "../../../../types.ts";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {IconPlus} from "@tabler/icons-react";
import {CreateButton} from "../../../common/CreateButton";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventsStatusControl} from "../../../common/EventsStatusControl";
import {EventsTable} from "../../../common/EventsTable";
import {NoEventsBlankSlate} from "../../../common/NoEventsBlankSlate";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {tableSortFromQuery} from "../../../common/TanStackTable/sorting.tsx";
import {useAccess} from "../../../../hooks/useAccess.ts";

const Events = () => {
    const {organizerId, eventsState} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {isReadOnly} = useAccess();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const eventsQuery = useGetEvents(getEventQueryFilters(searchParams) as QueryFilters);
    const pagination = eventsQuery.data?.meta;
    const events = eventsQuery.data?.data;

    return (
        <PageBody>
            <PageTitle>
                {t`Events`}
            </PageTitle>
            <TableCard>
                <ToolBar
                    embedded
                    searchComponent={() => (
                        <SearchBarWrapper
                            placeholder={t`Search by event name...`}
                            setSearchParams={setSearchParams}
                            searchParams={searchParams}
                        />
                    )}
                    filterComponent={(
                        <EventsStatusControl
                            baseUrl={`/manage/organizer/${organizerId}/events`}
                            eventsState={eventsState}
                        />
                    )}
                >
                    <CreateButton color="green" leftSection={<IconPlus size={16}/>} onClick={openCreateModal}>
                        {t`Create Event`}
                    </CreateButton>
                </ToolBar>

                {!events && <TableCardSkeleton rows={8}/>}

                {events?.length === 0 && (
                    <NoEventsBlankSlate
                        openCreateModal={isReadOnly ? undefined : openCreateModal}
                        eventsState={eventsState}
                    />
                )}

                {!!events?.length && (
                    <>
                        <EventsTable
                            events={events}
                            isRefreshing={eventsQuery.isFetching}
                            sort={tableSortFromQuery(searchParams, setSearchParams, pagination)}
                        />
                        <TableCardFooter
                            pagination={pagination}
                            page={searchParams.pageNumber}
                            onPageChange={(value) => setSearchParams({pageNumber: value})}
                        />
                    </>
                )}
            </TableCard>
            {createModalOpen && <CreateEventModal organizerId={organizerId} onClose={closeCreateModal}/>}
        </PageBody>
    );
}

export default Events;
