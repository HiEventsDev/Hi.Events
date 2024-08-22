import {Event, EventStatus, QueryFilterOperator, QueryFilters} from "../../../../types.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {EventCard} from "../../../common/EventCard";
import {t} from "@lingui/macro";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button, Group, Menu, Skeleton} from "@mantine/core";
import {IconCalendarPlus, IconChevronDown, IconPlus, IconUserPlus} from "@tabler/icons-react";
import {ToolBar} from "../../../common/ToolBar";
import {Pagination} from "../../../common/Pagination";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {Navigate, useNavigate, useParams} from "react-router-dom";
import {NoResultsSplash} from "../../../common/NoResultsSplash";
import {CreateOrganizerModal} from "../../../modals/CreateOrganizerModal";
import classes from "./Dashboard.module.scss";

const DashboardSkeleton = () => {
    return (
        <>
            <Skeleton height={120} radius="l" mb="20px"/>
            <Skeleton height={120} radius="l" mb="20px"/>
            <Skeleton height={120} radius="l"/>
        </>
    );
}

export const getEventQueryFilters = (searchParams: Partial<QueryFilters>) => {
    const {eventsState, organizerId} = useParams();
    let filter = {};
    if (eventsState === 'upcoming' || !eventsState) {
        filter = {
            additionalParams: {
                eventsStatus: 'upcoming',
            },
            filterFields: {}
        };
    } else if (eventsState === 'ended') {
        filter = {
            filterFields: {
                end_date: {operator: QueryFilterOperator.LessThanOrEquals, value: 'now'},
                status: {operator: QueryFilterOperator.NotEquals, value: EventStatus.ARCHIVED},
            }
        };
    } else if (eventsState === 'archived') {
        filter = {
            filterFields: {
                status: {operator: QueryFilterOperator.Equals, value: EventStatus.ARCHIVED},
            }
        };
    }

    if (organizerId) {
        // add the organizer filter on top of the other filters
        filter = {
            ...filter,
            filterFields: {
                organizer_id: {operator: QueryFilterOperator.Equals, value: organizerId},
                ...filter.filterFields
            }
        }
    }

    return {
        ...searchParams,
        ...filter,
    };
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
    const navigate = useNavigate();

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

            <Group mt={10} mb={15}>
                <Button
                    size={'compact-sm'}
                    variant={eventsState === 'upcoming' || !eventsState ? 'light' : 'transparent'}
                    onClick={() => navigate('/manage/events' + window.location.search)}
                >
                    {t`Upcoming`}
                </Button>
                <Button size={'compact-sm'}
                        variant={eventsState === 'ended' ? 'light' : 'transparent'}
                        onClick={() => navigate('/manage/events/ended' + window.location.search)}
                >
                    {t`Ended`}
                </Button>
                <Button size={'compact-sm'}
                        variant={eventsState === 'archived' ? 'light' : 'transparent'}
                        onClick={() => navigate('/manage/events/archived' + window.location.search)}
                >
                    {t`Archived`}
                </Button>
            </Group>

            {events?.length === 0 && isEventsFetched && (
                <NoResultsSplash
                    heading={t`No events to show`}
                    imageHref={'/blank-slate/events.svg'}
                    subHeading={(
                        <>
                            <p>
                                {t`Once you create an event, you'll see it here.`}
                            </p>
                            <Button
                                size={'xs'}
                                leftSection={<IconPlus/>}
                                color={'green'}
                                onClick={() => openCreateModal()}>{t`Create Event`}
                            </Button>
                        </>
                    )}
                />
            )}

            <div>
                {isEventsFetching && <DashboardSkeleton/>}

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
