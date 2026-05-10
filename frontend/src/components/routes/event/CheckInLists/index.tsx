import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {useDisclosure} from "@mantine/hooks";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../types.ts";
import {Pagination} from "../../../common/Pagination";
import {useGetEventCheckInLists} from "../../../../queries/useGetCheckInLists.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {CheckInListTable} from "../../../common/CheckInListTable";
import {CreateCheckInListModal} from "../../../modals/CreateCheckInListModal";
import {SortSelector} from "../../../common/SortSelector";

const CheckInLists = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const checkInListsQuery = useGetEventCheckInLists(
        eventId,
        searchParams as QueryFilters,
    );
    const checkInLists = checkInListsQuery?.data?.data;
    const pagination = checkInListsQuery?.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <PageBody>
            <PageTitle
                subheading={t`Set up check-in lists for different entrances, sessions, or days.`}
            >
                {t`Check-In Lists`}
            </PageTitle>

            <ToolBar
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search check-in lists...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                    />
                )}
                filterComponent={pagination?.allowed_sorts ? (
                    <SortSelector
                        selected={searchParams.sortBy && searchParams.sortDirection
                            ? searchParams.sortBy + ':' + searchParams.sortDirection
                            : pagination.default_sort + ':' + pagination.default_sort_direction}
                        options={pagination.allowed_sorts}
                        onSortSelect={(key, sortDirection) => {
                            setSearchParams({sortBy: key, sortDirection});
                        }}
                    />
                ) : undefined}
                resultCount={pagination?.total}
                resultLabel={t`check-in lists`}
            >
                <Button
                    leftSection={<IconPlus/>}
                    color={'green'}
                    size={'sm'}
                    onClick={openCreateModal}>{t`Create Check-In List`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!checkInLists || checkInListsQuery.isFetching}/>

            {checkInLists && <CheckInListTable
                checkInLists={checkInLists}
                openCreateModal={openCreateModal}
                event={event}
            />}

            {createModalOpen && <CreateCheckInListModal onClose={closeCreateModal}/>}

            {!!checkInLists?.length && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
}

export default CheckInLists;
