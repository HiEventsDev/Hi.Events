import {PageBody} from "../../../common/PageBody";
import {CreateButton} from "../../../common/CreateButton";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useGetEventCapacityAssignments} from "../../../../queries/useGetCapacityAssignments.ts";
import {CapacityAssignmentList} from "../../../common/CapacityAssignmentList";
import {CreateCapacityAssignmentModal} from "../../../modals/CreateCapacityAssignmentModal";
import {useDisclosure} from "@mantine/hooks";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../types.ts";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {tableSortFromQuery} from "../../../common/TanStackTable/sorting.tsx";
import {SortSelector} from "../../../common/SortSelector";

const CapacityAssignments = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const capacityAssignmentsQuery = useGetEventCapacityAssignments(eventId, searchParams as QueryFilters);
    const capacityAssignments = capacityAssignmentsQuery.data?.data;
    const pagination = capacityAssignmentsQuery.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <PageBody>
            <PageTitle
                subheading={t`Prevent overselling by sharing inventory across multiple ticket types.`}
            >
                {t`Shared Capacity Management`}
            </PageTitle>

            <TableCard>
            <ToolBar
                embedded
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search capacity assignments...`}
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
            >
                <CreateButton
                    leftSection={<IconPlus size={16}/>}
                    color={'green'}
                    data-testid="capacity-create-button"
                    onClick={() => openCreateModal()}>{t`Create Capacity Assignment`}
                </CreateButton>
            </ToolBar>

            {!capacityAssignments && <TableCardSkeleton rows={5}/>}

            {capacityAssignments && <CapacityAssignmentList
                capacityAssignments={capacityAssignments}
                openCreateModal={openCreateModal}
                isRefreshing={capacityAssignmentsQuery.isFetching}
                sort={tableSortFromQuery(searchParams, setSearchParams, pagination)}
            />}

            {!!capacityAssignments?.length && (
                <TableCardFooter
                    pagination={pagination}
                    page={searchParams.pageNumber}
                    onPageChange={(value) => setSearchParams({pageNumber: value})}
                />
            )}
            </TableCard>

            {createModalOpen && <CreateCapacityAssignmentModal onClose={closeCreateModal}/>}
        </PageBody>
    );
}

export default CapacityAssignments;
