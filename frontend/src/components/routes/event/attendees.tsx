import {useParams} from "react-router-dom";
import {useGetAttendees} from "../../../queries/useGetAttendees.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {AttendeeTable} from "../../common/AttendeeTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {Pagination} from "../../common/Pagination";
import {Button} from "@mantine/core";
import {IconDownload, IconPlus} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {TableSkeleton} from "../../common/TableSkeleton";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, QueryFilters} from "../../../types.ts";
import {useDisclosure} from "@mantine/hooks";
import {CreateAttendeeModal} from "../../modals/CreateAttendeeModal";
import {downloadBinary} from "../../../utilites/download.ts";
import {attendeesClient} from "../../../api/attendee.client.ts";
import {useState} from "react";
import {t} from "@lingui/macro";
import {showError} from "../../../utilites/notifications.tsx";

const Attendees = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const attendeesQuery = useGetAttendees(eventId, searchParams as QueryFilters);
    const attendees = attendeesQuery?.data?.data;
    const pagination = attendeesQuery?.data?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [downloadPending, setDownloadPending] = useState(false);

    const handleExport = (eventId: IdParam) => {
        setDownloadPending(true);
        attendeesClient.export(eventId)
            .then(blob => {
                downloadBinary(blob, 'attendees.xlsx');
                setDownloadPending(false);
            }).catch(() => {
            setDownloadPending(false);
            showError(t`Failed to export attendees. Please try again.`)
        });
    }

    return (
        <>
            <PageBody>
                <PageTitle>
                    {t`Attendees`}
                </PageTitle>

                <ToolBar searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by attendee name, email or order #...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                        pagination={pagination}
                    />
                )}>
                    <Button color={'green'} size={'sm'} onClick={openCreateModal} rightSection={<IconPlus/>}>
                        {t`Add`}
                    </Button>

                    <Button color={'green'}
                            size={'sm'}
                            loading={downloadPending}
                            onClick={() => handleExport(eventId)}
                            rightSection={<IconDownload/>}
                    >
                        {t`Export`}
                    </Button>
                </ToolBar>

                <TableSkeleton isVisible={!attendees || attendeesQuery.isFetching}/>

                {(!!attendees) && <AttendeeTable openCreateModal={openCreateModal}
                                                 attendees={attendees}
                />}

                {!!attendees?.length
                    && <Pagination value={searchParams.pageNumber}
                                   onChange={(value) => setSearchParams({pageNumber: value})}
                                   total={Number(pagination?.last_page)}/>}
            </PageBody>
            {createModalOpen && <CreateAttendeeModal onClose={closeCreateModal} isOpen={createModalOpen}/>}
        </>
    );
};

export default Attendees;