import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {Event, QueryFilters} from "../../../../types.ts";
import {NavLink, useParams} from "react-router-dom";
import {t, Trans} from "@lingui/macro";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button, Skeleton} from "@mantine/core";
import {IconArrowLeft, IconCalendarPlus, IconPencil} from "@tabler/icons-react";
import {EventCard} from "../../../common/EventCard";
import {Pagination} from "../../../common/Pagination";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import classes from './OrganizerDashboard.module.scss';
import {EditOrganizerModal} from "../../../modals/EditOrganizerModal";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventsDashboardStatusButtons} from "../../../common/EventsDashboardStatusButtons";
import {NoEventsBlankSlate} from "../../../common/NoEventsBlankSlate";

const OrganizerDashboard = () => {
    const {organizerId, eventsState} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [editModalOpen, {open: openEditModal, close: closeEditModal}] = useDisclosure(false);
    const {data: eventsData, isFetched: isEventsFetched} = useGetEvents(
        getEventQueryFilters(searchParams) as QueryFilters
    );
    const pagination = eventsData?.meta;
    const events = eventsData?.data;
    const {data: organizer, isFetched: isOrganizerFetched} = useGetOrganizer(organizerId);

    return (
        <>
            <div className={classes.topLinks}>
                <Button
                    mt={20}
                    pl={0}
                    variant="transparent"
                    leftSection={<IconArrowLeft/>}
                    component={NavLink} to={`/manage/events`}>
                    {t`Back to all events`}
                </Button>
                <Button
                    mt={20}
                    pl={0}
                    variant="transparent"
                    leftSection={<IconPencil/>}
                    onClick={openEditModal}
                >
                    {t`Edit Organizer`}
                </Button>
            </div>

            <h1 style={{marginTop: '15px'}}>
                {isOrganizerFetched && (
                    <Trans>
                        {organizer?.name}'s Events
                    </Trans>
                )}
                {!isOrganizerFetched && <Skeleton height={30}/>}
            </h1>

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
            {createModalOpen && <CreateEventModal onClose={closeCreateModal}/>}
            {(editModalOpen && organizer) && <EditOrganizerModal organizerId={organizerId} onClose={closeEditModal}/>}
        </>
    );
}

export default OrganizerDashboard;
