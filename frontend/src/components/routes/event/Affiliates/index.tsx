import {PageBody} from "../../../common/PageBody";
import {CreateButton} from "../../../common/CreateButton";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useDisclosure} from "@mantine/hooks";
import {ToolBar} from "../../../common/ToolBar";
import {SearchBarWrapper} from "../../../common/SearchBar";
import {Button} from "@mantine/core";
import {IconDownload, IconPlus} from "@tabler/icons-react";
import {useFilterQueryParamSync} from "../../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, QueryFilters} from "../../../../types.ts";
import {TableCard, TableCardFooter, TableCardSkeleton} from "../../../common/TableCard";
import {tableSortFromQuery} from "../../../common/TanStackTable/sorting.tsx";
import {useGetAffiliates} from "../../../../queries/useGetAffiliates.ts";
import {AffiliateTable} from "../../../common/AffiliateTable";
import {CreateAffiliateModal} from "../../../modals/CreateAffiliateModal";
import {affiliateClient} from "../../../../api/affiliate.client.ts";
import {downloadBinary} from "../../../../utilites/download.ts";
import {withLoadingNotification} from "../../../../utilites/withLoadingNotification.tsx";
import {useState} from "react";

const Affiliates = () => {
    const canViewFinancials = useAccess().can('VIEW_FINANCIALS');
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const affiliatesQuery = useGetAffiliates(eventId, searchParams as QueryFilters);
    const affiliates = affiliatesQuery.data?.data;
    const pagination = affiliatesQuery.data?.meta;
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
            <PageTitle
                subheading={t`Create trackable links to reward partners who promote your event.`}
            >
                {t`Affiliates`}
            </PageTitle>

            <TableCard>
                <ToolBar
                    embedded
                    searchComponent={() => (
                        <SearchBarWrapper
                            placeholder={t`Search affiliates...`}
                            setSearchParams={setSearchParams}
                            searchParams={searchParams}
                        />
                    )}
                >
                    {canViewFinancials && <Button
                        onClick={() => handleExport(eventId)}
                        leftSection={<IconDownload size={16}/>}
                        variant="default"
                        loading={downloadPending}
                    >
                        {t`Export`}
                    </Button>}
                    <CreateButton
                        leftSection={<IconPlus size={16}/>}
                        color="green"
                        data-testid="affiliate-create-button"
                        onClick={openCreateModal}>{t`Create Affiliate`}
                    </CreateButton>
                </ToolBar>

                {!affiliates && <TableCardSkeleton rows={5}/>}

                {affiliates && <AffiliateTable
                    affiliates={affiliates}
                    openCreateModal={openCreateModal}
                    isRefreshing={affiliatesQuery.isFetching}
                    sort={tableSortFromQuery(searchParams, setSearchParams, pagination)}
                />}

                {!!affiliates?.length && (
                    <TableCardFooter
                        pagination={pagination}
                        page={searchParams.pageNumber}
                        onPageChange={(value) => setSearchParams({pageNumber: value})}
                    />
                )}
            </TableCard>

            {createModalOpen && <CreateAffiliateModal onClose={closeCreateModal}/>}
        </PageBody>
    );
}

export default Affiliates;
