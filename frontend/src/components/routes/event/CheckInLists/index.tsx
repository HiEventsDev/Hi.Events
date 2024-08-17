import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router-dom";
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
import {CheckInListList} from "../../../common/CheckInListList";
import {CreateCheckInListModal} from "../../../modals/CreateCheckInListModal";

const CheckInLists = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: checkInListsData} = useGetEventCheckInLists(
        eventId,
        searchParams as QueryFilters,
    );
    const checkInLists = checkInListsData?.data;
    const pagination = checkInListsData?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    return (
        <PageBody>
            <PageTitle>
                {t`Check-In Lists`}
            </PageTitle>

            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search check-in lists...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <Button
                    leftSection={<IconPlus/>}
                    color={'green'}
                    onClick={openCreateModal}>{t`Create Check-In List`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!checkInLists}/>

            {checkInLists && <CheckInListList
                checkInLists={checkInLists}
                openCreateModal={openCreateModal}
            />}

            {createModalOpen && <CreateCheckInListModal onClose={closeCreateModal}/>}

            {(!!checkInLists?.length && (pagination?.total || 0) >= 20) && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
}

export default CheckInLists;
