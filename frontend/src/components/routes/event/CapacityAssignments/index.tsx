import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router-dom";
import {useGetEventCapacityAssignments} from "../../../../queries/useGetCapacityAssignments.ts";
import {CapacityAssignmentList} from "../../../common/CapacityAssignmentList/index.tsx";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {CreateCapacityAssignmentModal} from "../../../modals/CreateCapacityAssignmentModal";
import {useDisclosure} from "@mantine/hooks";

const CapacityAssignments = () => {
    const {eventId} = useParams();
    const {data: capacityAssignments} = useGetEventCapacityAssignments(eventId);
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <PageBody>
            <PageTitle>
                {t`Capacity Management`}
            </PageTitle>

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
