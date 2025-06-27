import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {TableSkeleton} from "../../../common/TableSkeleton";
import {useDisclosure} from "@mantine/hooks";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button} from "@mantine/core";
import {IconDownload, IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, QueryFilters} from "../../../../types.ts";
import {Pagination} from "../../../common/Pagination";
import {useGetAffiliates} from "../../../../queries/useGetAffiliates.ts";
import {AffiliateTable} from "../../../common/AffiliateTable";
import {CreateAffiliateModal} from "../../../modals/CreateAffiliateModal";
import {affiliateClient} from "../../../../api/affiliate.client.ts";
import {downloadBinary} from "../../../../utilites/download.ts";
import {withLoadingNotification} from "../../../../utilites/withLoadingNotification.tsx";
import {useState} from "react";

const Affiliates = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: affiliatesData} = useGetAffiliates(
        eventId,
        searchParams as QueryFilters,
    );
    const affiliates = affiliatesData?.data;
    const pagination = affiliatesData?.meta;
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [downloadPending, setDownloadPending] = useState(false);

    const handleExport = async (eventId: IdParam) => {
        await withLoadingNotification(async () => {
                setDownloadPending(true);
                const blob = await affiliateClient.exportAffiliates(eventId);
                downloadBinary(blob, 'affiliates.xlsx');
            },
            {
                loading: {
                    title: t`Exporting Affiliates`,
                    message: t`Please wait while we prepare your affiliates for export...`
                },
                success: {
                    title: t`Affiliates Exported`,
                    message: t`Your affiliates have been exported successfully.`,
                    onRun: () => setDownloadPending(false)
                },
                error: {
                    title: t`Failed to export affiliates`,
                    message: t`Please try again.`,
                    onRun: () => setDownloadPending(false)
                }
            });
    };

    return (
        <PageBody>
            <PageTitle>
                {t`Affiliates`}
            </PageTitle>

            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search affiliates...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <Button
                    onClick={() => handleExport(eventId)}
                    rightSection={<IconDownload size={14}/>}
                    color="green"
                    loading={downloadPending}
                    size="sm"
                >
                    {t`Export`}
                </Button>
                <Button
                    leftSection={<IconPlus/>}
                    color={'green'}
                    onClick={openCreateModal}>{t`Create Affiliate`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!affiliates}/>

            {affiliates && <AffiliateTable
                affiliates={affiliates}
                openCreateModal={openCreateModal}
            />}

            {createModalOpen && <CreateAffiliateModal onClose={closeCreateModal}/>}

            {(!!affiliates?.length && (pagination?.last_page || 0) > 1) && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
}

export default Affiliates;