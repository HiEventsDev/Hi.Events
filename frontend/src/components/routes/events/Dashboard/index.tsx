import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, Menu} from "@mantine/core";
import {useDisclosure} from "@mantine/hooks";
import {
    IconBuilding,
    IconCalendarPlus,
    IconChevronDown,
    IconChevronRight,
    IconPlus,
    IconUserPlus
} from "@tabler/icons-react";
import {Navigate, useNavigate, useParams} from "react-router";
import {QueryFilterOperator, QueryFilters} from "../../../../types.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventsStatusControl} from "../../../common/EventsStatusControl";
import {EventsTable} from "../../../common/EventsTable";
import {FilterModal} from "../../../common/FilterModal";
import {NoEventsBlankSlate} from "../../../common/NoEventsBlankSlate";
import {PageTitle} from "../../../common/PageTitle";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {tableSortFromQuery} from "../../../common/TanStackTable/sorting.tsx";
import {ToolBar} from "../../../common/ToolBar";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {CreateOrganizerModal} from "../../../modals/CreateOrganizerModal";
import {SwitchOrganizerModal} from "../../../modals/SwitchOrganizerModal";
import classes from "./Dashboard.module.scss";

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
    const eventsQuery = useGetEvents(getEventQueryFilters(searchParams) as QueryFilters);
    const organizersQuery = useGetOrganizers();
    const {canManageOrganizer, canCreateOrganizers, isReadOnly, isLoaded: isAccessLoaded} = useAccess();
    const pagination = eventsQuery.data?.meta;
    const events = eventsQuery.data?.data;
    const readableOrganizers = organizersQuery?.data?.data;
    const organizers = readableOrganizers?.filter(organizer => canManageOrganizer(organizer.id));
    const canCreateEvents = !isReadOnly && !!organizers?.length;

    if (isAccessLoaded && organizersQuery.isFetched && readableOrganizers?.length === 0 && canCreateOrganizers) {
        return <Navigate to={'/welcome'}/>
    }

    if (isAccessLoaded && organizersQuery.isFetched && readableOrganizers?.length === 1 && organizers?.length === 1) {
        return <Navigate to={'/manage/organizer/' + organizers[0].id}/>;
    }

    const organizerFilter = searchParams.filterFields?.organizer_id;
    const selectedOrganizerIds = organizerFilter && !Array.isArray(organizerFilter)
        ? String(organizerFilter.value).split(',')
        : [];

    const showOrganizers = (organizerIds: string[]) => {
        const filterFields = {...(searchParams.filterFields || {})};
        if (organizerIds.length > 0) {
            filterFields.organizer_id = {operator: QueryFilterOperator.In, value: organizerIds.join(',')};
        } else {
            delete filterFields.organizer_id;
        }

        setSearchParams({...searchParams, filterFields, pageNumber: 1} as QueryFilters, true);
    };

    return (
        <div className={classes.page}>
            <header className={classes.header}>
                <PageTitle subheading={t`All events across your organizers.`}>
                    {t`Events`}
                </PageTitle>

                {!!organizers?.length && (
                    <Button
                        className={classes.organizersButton}
                        variant="default"
                        leftSection={<IconBuilding size={16} stroke={1.5}/>}
                        rightSection={<IconChevronRight size={16} stroke={1.5}/>}
                        onClick={() => organizers.length === 1
                            ? navigate(`/manage/organizer/${organizers[0].id}`)
                            : setOrganizerModalOpen(true)}
                    >
                        {organizers.length === 1 ? organizers[0].name : t`${organizers.length} organizers`}
                    </Button>
                )}
            </header>

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
                        <div className={classes.filters}>
                            <EventsStatusControl baseUrl="/manage/events" eventsState={eventsState}/>
                            {readableOrganizers && readableOrganizers.length > 1 && (
                                <FilterModal
                                    filters={[{
                                        field: 'organizer_id',
                                        label: t`Organizer`,
                                        type: 'multi-select',
                                        options: readableOrganizers.map(organizer => ({
                                            value: String(organizer.id),
                                            label: organizer.name,
                                        })),
                                    }]}
                                    activeFilters={{organizer_id: selectedOrganizerIds}}
                                    onChange={values => showOrganizers(values.organizer_id ?? [])}
                                />
                            )}
                        </div>
                    )}
                >
                    {(canCreateEvents || canCreateOrganizers) && (
                        <Menu transitionProps={{transition: 'pop-top-right'}} position="bottom-end" width={200} withinPortal>
                            <Menu.Target>
                                <Button
                                    color="green"
                                    leftSection={<IconPlus size={16}/>}
                                    rightSection={<IconChevronDown size={14} stroke={1.5}/>}
                                    data-testid="create-new-menu-button"
                                >
                                    {t`Create new`}
                                </Button>
                            </Menu.Target>
                            <Menu.Dropdown>
                                {canCreateEvents && (
                                    <Menu.Item
                                        leftSection={<IconCalendarPlus size={16} stroke={1.5}/>}
                                        onClick={openCreateModal}
                                        data-testid="create-event-menu-item"
                                    >
                                        {t`Event`}
                                    </Menu.Item>
                                )}
                                {canCreateOrganizers && (
                                    <Menu.Item
                                        leftSection={<IconUserPlus size={16} stroke={1.5}/>}
                                        onClick={openCreateOrganizerModal}
                                    >
                                        {t`Organizer`}
                                    </Menu.Item>
                                )}
                            </Menu.Dropdown>
                        </Menu>
                    )}
                </ToolBar>

                {!events && <TableCardSkeleton rows={8}/>}

                {events?.length === 0 && (
                    <NoEventsBlankSlate
                        openCreateModal={canCreateEvents ? openCreateModal : undefined}
                        eventsState={eventsState}
                    />
                )}

                {!!events?.length && (
                    <>
                        <EventsTable
                            events={events}
                            showOrganizer
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

            {createModalOpen && <CreateEventModal onClose={closeCreateModal}/>}
            {createOrganizerModalOpen && <CreateOrganizerModal onClose={closeCreateOrganizerModal}/>}
            {organizerModalOpen && (
                <SwitchOrganizerModal
                    opened={organizerModalOpen}
                    onClose={() => setOrganizerModalOpen(false)}
                    onCreateOrganizer={() => {
                        setOrganizerModalOpen(false);
                        openCreateOrganizerModal();
                    }}
                    heading={t`Choose an Organizer`}
                />
            )}
        </div>
    );
}

export default Dashboard;
