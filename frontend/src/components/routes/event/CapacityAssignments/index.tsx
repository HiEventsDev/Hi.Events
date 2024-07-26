import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router-dom";
import {useGetEventCapacityAssignments} from "../../../../queries/useGetCapacityAssignments.ts";
import {CapacityAssignmentList} from "../../../common/CapacityAssignmentList/index.tsx";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {CreateCapacityAssignmentModal} from "../../../modals/CreateCapacityAssignmentModal";
import {useDisclosure} from "@mantine/hooks";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {QueryFilters} from "../../../../types.ts";

const CapacityAssignments = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: capcityAssignmentsData} = useGetEventCapacityAssignments(
        eventId,
        searchParams as QueryFilters,
    );
    const capacityAssignments = capcityAssignmentsData?.data;
    const pagination = capcityAssignmentsData?.meta
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
                    size={'xs'}
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
        </PageBody>
    );
}

export default CapacityAssignments;
