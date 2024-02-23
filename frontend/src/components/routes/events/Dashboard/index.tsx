import {Event, QueryFilters} from "../../../../types.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {EventCard} from "../../../common/EventCard";
import {t} from "@lingui/macro";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button} from "@mantine/core";
import {IconCalendar} from "@tabler/icons-react";
import {ToolBar} from "../../../common/ToolBar";
import {Pagination} from "../../../common/Pagination";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {Navigate} from "react-router-dom";
import {LoadingMask} from "../../../common/LoadingMask";

export function Dashboard() {
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const eventsQuery = useGetEvents(searchParams as QueryFilters);
    const organizersQuery = useGetOrganizers();
    const pagination = eventsQuery?.data?.meta;
    const events = eventsQuery?.data?.data;
    const organizers = organizersQuery?.data?.data;

    if (organizersQuery.isFetched && organizers?.length === 0) {
        return <Navigate to={'/welcome'}/>
    }

    if (eventsQuery.isFetching) {
        return <LoadingMask/>;
    }

    return (
        <>
            <h1>{t`Events`}</h1>

            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search by event name...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <Button onClick={openCreateModal} color={'green'}
                        rightSection={<IconCalendar/>}>
                    {t`Create Event`}
                </Button>
            </ToolBar>

            <div>
                {events?.map((event: Event) =>
                    (
                        <EventCard event={event}/>
                    ))}
            </div>
            {!!events?.length
                && <Pagination value={searchParams.pageNumber}
                               onChange={(value) => setSearchParams({pageNumber: value})}
                               total={Number(pagination?.last_page)}
                />
            }
            {createModalOpen && <CreateEventModal onClose={closeCreateModal} isOpen={createModalOpen}/>}
        </>
    );
}