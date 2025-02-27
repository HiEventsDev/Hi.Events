import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useGetEventCapacityAssignments} from "../../../../queries/useGetCapacityAssignments.ts";
import {CapacityAssignmentList} from "../../../common/CapacityAssignmentList";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {CreateCapacityAssignmentModal} from "../../../modals/CreateCapacityAssignmentModal";
import {useDisclosure} from "@mantine/hooks";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../types.ts";
import {Pagination} from "../../../common/Pagination";

const CapacityAssignments = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: capacityAssignmentsData} = useGetEventCapacityAssignments(
        eventId,
        searchParams as QueryFilters,
    );
    const capacityAssignments = capacityAssignmentsData?.data;
    const pagination = capacityAssignmentsData?.meta
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <PageBody>
            <PageTitle>
                {t`Capacity Management`}
            </PageTitle>

            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search capacity assignments...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <Button
                    leftSection={<IconPlus/>}
                    color={'green'}
                    onClick={() => openCreateModal()}>{t`Create Capacity Assignment`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!capacityAssignments}/>

            {capacityAssignments && <CapacityAssignmentList
                capacityAssignments={capacityAssignments}
                openCreateModal={openCreateModal}
            />}

            {createModalOpen && <CreateCapacityAssignmentModal onClose={closeCreateModal}/>}

            {(!!capacityAssignments?.length && (pagination?.total || 0) >= 20) && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
}

export default CapacityAssignments;
