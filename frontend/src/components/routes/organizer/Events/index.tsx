import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {Event, QueryFilters} from "../../../../types.ts";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button, Skeleton} from "@mantine/core";
import {IconCalendarPlus} from "@tabler/icons-react";
import {EventCard} from "../../../common/EventCard";
import {Pagination} from "../../../common/Pagination";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventsDashboardStatusButtons} from "../../../common/EventsDashboardStatusButtons";
import {NoEventsBlankSlate} from "../../../common/NoEventsBlankSlate";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";

const Events = () => {
    const {organizerId, eventsState} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const {data: eventsData, isFetched: isEventsFetched} = useGetEvents(
        getEventQueryFilters(searchParams) as QueryFilters
    );
    const pagination = eventsData?.meta;
    const events = eventsData?.data;

    const SkeletonEvents = () => {
        return (
            <div>
                {Array.from({length: 6}).map((_, index) => (
                    <Skeleton key={index} height={190} mb={10}/>
                ))}
            </div>
        );
    }

    return (
        <PageBody>
            <PageTitle>
                {t`Events`}
            </PageTitle>
            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search by event name...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <>
                    <Button
                        color={'green'}
                        rightSection={
                            <IconCalendarPlus stroke={1.5}/>
                        }
                        onClick={openCreateModal}
                        pr={12}
                    >
                        {t`Create Event`}
                    </Button>
                </>
            </ToolBar>

            <EventsDashboardStatusButtons
                baseUrl={`/manage/organizer/${organizerId}/events`}
                eventsState={eventsState as string}
            />

            {(events?.length === 0 && isEventsFetched)
                && <NoEventsBlankSlate openCreateModal={openCreateModal} eventsState={eventsState}/>}

            {!isEventsFetched && <SkeletonEvents/>}

            <div>
                {events?.map((event: Event) => (
                    <EventCard event={event} key={event.id}/>
                ))}
            </div>

            {isEventsFetched && events && events?.length > 0
                && <Pagination value={searchParams.pageNumber}
                               onChange={(value) => setSearchParams({pageNumber: value})}
                               total={Number(pagination?.last_page)}
                />
            }
            {createModalOpen && <CreateEventModal organizerId={organizerId} onClose={closeCreateModal}/>}
        </PageBody>
    );
}

export default Events;
