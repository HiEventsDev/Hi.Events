import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {Event, QueryFilters} from "../../../../types.ts";
import {NavLink, useParams} from "react-router-dom";
import {t, Trans} from "@lingui/macro";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button, Skeleton} from "@mantine/core";
import {IconArrowLeft, IconCalendarPlus, IconPlus} from "@tabler/icons-react";
import {EventCard} from "../../../common/EventCard";
import {Pagination} from "../../../common/Pagination";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerEvents} from "../../../../queries/useGetOrganizerEvents.ts";
import {NoResultsSplash} from "../../../common/NoResultsSplash";

const OrganizerDashboard = () => {
    const {organizerId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const {data: eventsData, isFetched: isEventsFetched} = useGetOrganizerEvents(
        organizerId,
        searchParams as QueryFilters
    );
    const pagination = eventsData?.meta;
    const events = eventsData?.data;
    const {data: organizer, isFetched: isOrganizerFetched} = useGetOrganizer(organizerId)

    return (
        <>
            <Button
                mt={20}
                pl={0}
                variant="transparent"
                leftSection={<IconArrowLeft/>}
                component={NavLink} to={`/manage/events`}>
                {t`Back to all events`}
            </Button>

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


            {events?.length === 0 && isEventsFetched && (
                <NoResultsSplash
                    heading={t`No events for this organizer`}
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
                {events?.map((event: Event) =>
                    (
                        <EventCard event={event}/>
                    ))}
            </div>

            {isEventsFetched && events && events?.length > 0
                && <Pagination value={searchParams.pageNumber}
                               onChange={(value) => setSearchParams({pageNumber: value})}
                               total={Number(pagination?.last_page)}
                />
            }
            {createModalOpen && <CreateEventModal onClose={closeCreateModal} isOpen={createModalOpen}/>}
        </>
    );
}

export default OrganizerDashboard;