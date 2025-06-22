import {Event, QueryFilters} from "../../../../types.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {EventCard} from "../../../common/EventCard";
import {t, Trans} from "@lingui/macro";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button, Menu, Modal, Skeleton} from "@mantine/core";
import {
    IconArrowRight,
    IconBuilding,
    IconCalendarPlus,
    IconChevronDown,
    IconPlus,
    IconUserPlus
} from "@tabler/icons-react";
import {ToolBar} from "../../../common/ToolBar";
import {Pagination} from "../../../common/Pagination";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {Navigate, useNavigate, useParams} from "react-router";
import {CreateOrganizerModal} from "../../../modals/CreateOrganizerModal";
import classes from "./Dashboard.module.scss";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventsDashboardStatusButtons} from "../../../common/EventsDashboardStatusButtons";
import {NoEventsBlankSlate} from "../../../common/NoEventsBlankSlate";
import {useState} from "react";
import {getConfig} from "../../../../utilites/config.ts";

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
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [createOrganizerModalOpen, {
        open: openCreateOrganizerModal,
        close: closeCreateOrganizerModal
    }] = useDisclosure(false);
    const [organizerModalOpen, setOrganizerModalOpen] = useState(false);
    const {
        data: eventData,
        isFetched: isEventsFetched,
        isFetching: isEventsFetching,
    } = useGetEvents(getEventQueryFilters(searchParams) as QueryFilters);
    const organizersQuery = useGetOrganizers();
    const pagination = eventData?.meta;
    const events = eventData?.data;
    const organizers = organizersQuery?.data?.data;

    // If there are no organizers, redirect to the welcome page
    if (organizersQuery.isFetched && organizers?.length === 0) {
        return <Navigate to={'/welcome'}/>
    }

    // If there's only one organizer, redirect to their dashboard
    if (organizersQuery.isFetched && organizers?.length === 1) {
        return <Navigate to={'/manage/organizer/' + organizers[0].id}/>;
    }

    const getHeading = () => {
        if (eventsState === 'upcoming' || !eventsState) {
            return t`All Upcoming Events`;
        } else if (eventsState === 'ended') {
            return t`All Ended Events`;
        } else if (eventsState === 'archived') {
            return t`All Archived Events`;
        }
    }

    return (
        <div className={classes.eventsContainer}>
            <div className={classes.pageHeader}>
                <div className={classes.headerContent}>
                    <h1 className={classes.pageTitle}>{getHeading()}</h1>
                    <p className={classes.welcomeMessage}>
                        <Trans>Welcome to {getConfig('VITE_APP_NAME', 'Hi.Events')}, here's a listing of all your events</Trans>
                    </p>
                </div>

                {/* Organizer Navigation */}
                {organizers && organizers.length === 1 ? (
                    <button
                        className={classes.organizerButton}
                        onClick={() => navigate(`/manage/organizer/${organizers[0].id}`)}
                    >
                        <div className={classes.organizerLogo}>
                            {organizers[0].images?.find((image) => image.type === 'ORGANIZER_LOGO') ? (
                                <img
                                    src={organizers[0].images.find((image) => image.type === 'ORGANIZER_LOGO')?.url}
                                    alt={organizers[0].name}
                                />
                            ) : (
                                <div className={classes.logoPlaceholder}>
                                    <IconBuilding size={20}/>
                                </div>
                            )}
                        </div>
                        <span className={classes.organizerName}>{organizers[0].name}</span>
                        <IconArrowRight size={16} className={classes.arrowIcon}/>
                    </button>
                ) : organizers && organizers.length > 1 ? (
                    <button
                        className={classes.organizerButton}
                        onClick={() => setOrganizerModalOpen(true)}
                    >
                        <div className={classes.organizerLogos}>
                            {organizers.slice(0, 3).map((organizer, index) => (
                                <div
                                    key={organizer.id}
                                    className={classes.miniLogo}
                                    style={{zIndex: 3 - index}}
                                >
                                    {organizer.images?.find((image) => image.type === 'ORGANIZER_LOGO') ? (
                                        <img
                                            src={organizer.images.find((image) => image.type === 'ORGANIZER_LOGO')?.url}
                                            alt={organizer.name}
                                        />
                                    ) : (
                                        <IconBuilding size={12}/>
                                    )}
                                </div>
                            ))}
                            {organizers.length > 3 && (
                                <div className={classes.miniLogo} style={{zIndex: 0}}>
                                    <span>+{organizers.length - 3}</span>
                                </div>
                            )}
                        </div>
                        <span className={classes.organizerCount}>
                            {t`${organizers.length} organizers`}
                        </span>
                        <IconArrowRight size={16} className={classes.arrowIcon}/>
                    </button>
                ) : null}
            </div>

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
                        position="bottom"
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

            {/* Organizers Modal */}
            <Modal
                opened={organizerModalOpen}
                onClose={() => setOrganizerModalOpen(false)}
                title={t`Choose an Organizer`}
                size="md"
                centered
            >
                <div className={classes.organizerModalContent}>
                    <p className={classes.modalDescription}>
                        {t`Select an organizer to view their dashboard and events.`}
                    </p>
                    <div className={classes.organizerGrid}>
                        {organizers?.map((organizer) => (
                            <button
                                key={organizer.id}
                                className={classes.organizerModalCard}
                                onClick={() => {
                                    navigate(`/manage/organizer/${organizer.id}`);
                                    setOrganizerModalOpen(false);
                                }}
                            >
                                <div className={classes.organizerModalLogo}>
                                    {organizer.images?.find((image) => image.type === 'ORGANIZER_LOGO') ? (
                                        <img
                                            src={organizer.images.find((image) => image.type === 'ORGANIZER_LOGO')?.url}
                                            alt={organizer.name}
                                        />
                                    ) : (
                                        <div className={classes.logoModalPlaceholder}>
                                            <IconBuilding size={32}/>
                                        </div>
                                    )}
                                </div>
                                <h3 className={classes.organizerModalName}>{organizer.name}</h3>
                            </button>
                        ))}
                    </div>
                </div>
            </Modal>
        </div>
    );
}

export default Dashboard;
