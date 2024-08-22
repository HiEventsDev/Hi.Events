import {Event, QueryFilters} from "../../../../types.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {EventCard} from "../../../common/EventCard";
import {t} from "@lingui/macro";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button, Menu, Skeleton} from "@mantine/core";
import {IconCalendarPlus, IconChevronDown, IconPlus, IconUserPlus} from "@tabler/icons-react";
import {ToolBar} from "../../../common/ToolBar";
import {Pagination} from "../../../common/Pagination";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {Navigate, useParams} from "react-router-dom";
import {CreateOrganizerModal} from "../../../modals/CreateOrganizerModal";
import classes from "./Dashboard.module.scss";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventsDashboardStatusButtons} from "../../../common/EventsDashboardStatusButtons";
import {NoEventsBlankSlate} from "../../../common/NoEventsBlankSlate";

const DashboardSkeleton = () => {
    return (
        <>
            <Skeleton height={120} radius="l" mb="20px"/>
            <Skeleton height={120} radius="l" mb="20px"/>
            <Skeleton height={120} radius="l"/>
        </>
    );
}

export function Dashboard() {
    const {eventsState} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [createOrganizerModalOpen, {
        open: openCreateOrganizerModal,
        close: closeCreateOrganizerModal
    }] = useDisclosure(false);
    const {
        data: eventData,
        isFetched: isEventsFetched,
        isFetching: isEventsFetching,
    } = useGetEvents(getEventQueryFilters(searchParams) as QueryFilters);
    const organizersQuery = useGetOrganizers();
    const pagination = eventData?.meta;
    const events = eventData?.data;
    const organizers = organizersQuery?.data?.data;

    if (organizersQuery.isFetched && organizers?.length === 0) {
        return <Navigate to={'/welcome'}/>
    }

    const getHeading = () => {
        if (eventsState === 'upcoming' || !eventsState) {
            return t`Upcoming Events`;
        } else if (eventsState === 'ended') {
            return t`Ended Events`;
        } else if (eventsState === 'archived') {
            return t`Archived Events`;
        }
    }

    return (
        <div className={classes.eventsContainer}>
            <h1>{getHeading()}</h1>

            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search by event name...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <>
                    <Menu
                        transitionProps={{transition: 'pop-top-right'}}
                        position="top-end"
                        width={220}
                        withinPortal
                    >
                        <Menu.Target>
                            <Button
                                leftSection={<IconPlus/>}
                                color={'green'}
                                rightSection={
                                    <IconChevronDown stroke={1.5}/>
                                }
                                pr={12}
                            >
                                {t`Create new`}
                            </Button>
                        </Menu.Target>
                        <Menu.Dropdown>
                            <Menu.Item
                                leftSection={
                                    <IconCalendarPlus
                                        stroke={1.5}
                                    />
                                }
                                onClick={openCreateModal}
                            >
                                {t`Event`}
                            </Menu.Item>
                            <Menu.Item
                                leftSection={
                                    <IconUserPlus
                                        stroke={1.5}
                                    />
                                }
                                onClick={openCreateOrganizerModal}
                            >
                                {t`Organizer`}
                            </Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </>
            </ToolBar>

            <EventsDashboardStatusButtons
                baseUrl={`/manage/events`}
                eventsState={eventsState as string}
            />

            {(events?.length === 0 && isEventsFetched)
                && <NoEventsBlankSlate openCreateModal={openCreateModal} eventsState={eventsState}/>}

            <div>
                {(isEventsFetching && !events) && <DashboardSkeleton/>}

                {events?.map((event: Event) =>
                    (
                        <EventCard key={event.id} event={event}/>
                    ))}
            </div>
            {events && events.length > 0
                && <Pagination value={searchParams.pageNumber}
                               onChange={(value) => setSearchParams({pageNumber: value})}
                               total={Number(pagination?.last_page)}
                />
            }
            {createModalOpen && <CreateEventModal onClose={closeCreateModal}/>}
            {createOrganizerModalOpen && <CreateOrganizerModal onClose={closeCreateOrganizerModal}/>}
        </div>
    );
}

export default Dashboard;
