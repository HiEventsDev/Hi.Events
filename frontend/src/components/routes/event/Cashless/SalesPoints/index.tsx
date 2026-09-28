import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {IconPlus} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {PageTitle} from "../../../../common/PageTitle";
import {ToolBar} from "../../../../common/ToolBar";
import {SearchBarWrapper} from "../../../../common/SearchBar";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {Pagination} from "../../../../common/Pagination";
import {CashlessSalesPointTable} from "../../../../common/CashlessSalesPointTable";
import {CreateCashlessSalesPointModal} from "../../../../modals/CreateCashlessSalesPointModal";
import {CashlessDisabledNotice} from "../CashlessDisabledNotice";
import {useFilterQueryParamSync} from "../../../../../hooks/useFilterQueryParamSync.ts";
import {useGetCashlessSalesPoints} from "../../../../../queries/useGetCashlessSalesPoints.ts";
import {useGetCashlessSettings} from "../../../../../queries/useGetCashlessSettings.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {QueryFilters} from "../../../../../types.ts";

const CashlessSalesPoints = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: event} = useGetEvent(eventId);
    const {data: settings} = useGetCashlessSettings(eventId);
    const {data: salesPointsData} = useGetCashlessSalesPoints(eventId, searchParams as QueryFilters);
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);

    const salesPoints = salesPointsData?.data;
    const pagination = salesPointsData?.meta;

    return (
        <PageBody>
            <PageTitle
                subheading={t`Each bar or stand gets its own link. Staff open it, scan a ticket and take payment from the balance.`}
            >
                {t`Sales Points`}
            </PageTitle>

            {settings && !settings.cashless_enabled && <CashlessDisabledNotice/>}

            <ToolBar
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search sales points...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                    />
                )}
            >
                <Button
                    leftSection={<IconPlus/>}
                    color="green"
                    onClick={openCreateModal}
                    data-testid="cashless-sales-point-create-button"
                >
                    {t`Create sales point`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!salesPoints}/>

            {salesPoints && (
                <CashlessSalesPointTable
                    salesPoints={salesPoints}
                    currency={event?.currency ?? 'USD'}
                    openCreateModal={openCreateModal}
                />
            )}

            {createModalOpen && <CreateCashlessSalesPointModal onClose={closeCreateModal}/>}

            {(!!salesPoints?.length && (pagination?.last_page || 0) > 1) && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={(value) => setSearchParams({pageNumber: value})}
                    total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
};

export default CashlessSalesPoints;
