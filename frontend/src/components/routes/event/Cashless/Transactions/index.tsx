import {t} from "@lingui/macro";
import {Button, Group, Pill} from "@mantine/core";
import {IconDownload} from "@tabler/icons-react";
import {useState} from "react";
import {useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {PageTitle} from "../../../../common/PageTitle";
import {ToolBar} from "../../../../common/ToolBar";
import {SearchBarWrapper} from "../../../../common/SearchBar";
import {SortSelector} from "../../../../common/SortSelector";
import {FilterModal, FilterOption} from "../../../../common/FilterModal";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {Pagination} from "../../../../common/Pagination";
import {CashlessTransactionTable, typeLabel} from "../../../../common/CashlessTransactionTable";
import {useFilterQueryParamSync} from "../../../../../hooks/useFilterQueryParamSync.ts";
import {useGetCashlessTransactions} from "../../../../../queries/useGetCashlessTransactions.ts";
import {useGetCashlessSalesPoints} from "../../../../../queries/useGetCashlessSalesPoints.ts";
import {useGetCashlessWallet} from "../../../../../queries/useGetCashlessWallet.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {cashlessClient} from "../../../../../api/cashless.client.ts";
import {downloadBinary} from "../../../../../utilites/download.ts";
import {withLoadingNotification} from "../../../../../utilites/withLoadingNotification.tsx";
import {CashlessTransactionType, IdParam, QueryFilterOperator, QueryFilters} from "../../../../../types.ts";

const TRANSACTION_TYPES: CashlessTransactionType[] = [
    'TOPUP_ONLINE',
    'TOPUP_STAFF',
    'PURCHASE',
    'REVERSAL',
    'REFUND_REMAINING',
    'CLOSURE',
];

const getFilterValue = (field: any): any[] => {
    if (!field) return [];
    if (Array.isArray(field)) return field;
    if (Array.isArray(field.value)) return field.value;
    return field.value ? [field.value] : [];
};

const CashlessTransactions = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: event} = useGetEvent(eventId);
    const {data: transactionsData} = useGetCashlessTransactions(eventId, searchParams as QueryFilters);
    const {data: salesPointsData} = useGetCashlessSalesPoints(eventId, {pageNumber: 1, perPage: 100} as QueryFilters);
    const [downloadPending, setDownloadPending] = useState(false);

    const transactions = transactionsData?.data;
    const pagination = transactionsData?.meta;

    const walletId = getFilterValue(searchParams.filterFields?.cashless_wallet_id)[0];
    const {data: filteredWallet} = useGetCashlessWallet(eventId, walletId);

    const filterOptions: FilterOption[] = [
        {
            field: 'type',
            label: t`Type`,
            type: 'multi-select',
            options: TRANSACTION_TYPES.map((type) => ({value: type, label: typeLabel(type)})),
        },
        {
            field: 'cashless_sales_point_id',
            label: t`Sales point`,
            type: 'multi-select',
            options: salesPointsData?.data?.map((salesPoint) => ({
                value: String(salesPoint.id),
                label: salesPoint.name,
            })) ?? [],
        },
    ];

    const currentFilters = {
        type: getFilterValue(searchParams.filterFields?.type),
        cashless_sales_point_id: getFilterValue(searchParams.filterFields?.cashless_sales_point_id),
    };

    const walletFilter = () => walletId
        ? {cashless_wallet_id: {operator: QueryFilterOperator.Equals, value: walletId}}
        : {};

    const handleFilterChange = (values: Record<string, any>) => {
        const filterFields: any = {...walletFilter()};

        if (values.type?.length > 0) {
            filterFields.type = {operator: QueryFilterOperator.In, value: values.type};
        }
        if (values.cashless_sales_point_id?.length > 0) {
            filterFields.cashless_sales_point_id = {
                operator: QueryFilterOperator.In,
                value: values.cashless_sales_point_id,
            };
        }

        setSearchParams({...searchParams, filterFields, pageNumber: 1} as QueryFilters, true);
    };

    const handleResetFilters = () => {
        setSearchParams({...searchParams, filterFields: walletFilter(), pageNumber: 1} as QueryFilters, true);
    };

    const handleWalletRemoved = () => {
        const filterFields: any = {...(searchParams.filterFields || {})};
        delete filterFields.cashless_wallet_id;

        setSearchParams({...searchParams, filterFields, pageNumber: 1} as QueryFilters, true);
    };

    const handleExport = async (eventId: IdParam) => {
        await withLoadingNotification(async () => {
            setDownloadPending(true);
            const blob = await cashlessClient.exportTransactions(eventId);
            downloadBinary(blob, 'cashless-transactions.xlsx');
        }, {
            loading: {
                title: t`Exporting transactions`,
                message: t`Please wait while we prepare your cashless transactions for export...`,
            },
            success: {
                title: t`Transactions exported`,
                message: t`Your cashless transactions have been exported successfully.`,
                onRun: () => setDownloadPending(false),
            },
            error: {
                title: t`Failed to export transactions`,
                message: t`Please try again.`,
                onRun: () => setDownloadPending(false),
            },
        });
    };

    return (
        <PageBody>
            <PageTitle
                subheading={t`Every top-up, purchase, reversal and refund, in the order it happened.`}
            >
                {t`Cashless Transactions`}
            </PageTitle>

            <ToolBar
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by name, email or ticket ID...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                    />
                )}
                filterComponent={(
                    <Group gap="sm" wrap="wrap">
                        {pagination?.allowed_sorts && (
                            <SortSelector
                                selected={searchParams.sortBy && searchParams.sortDirection
                                    ? searchParams.sortBy + ':' + searchParams.sortDirection
                                    : pagination.default_sort + ':' + pagination.default_sort_direction}
                                options={pagination.allowed_sorts}
                                onSortSelect={(key, sortDirection) => setSearchParams({sortBy: key, sortDirection})}
                            />
                        )}
                        <FilterModal
                            filters={filterOptions}
                            activeFilters={currentFilters}
                            onChange={handleFilterChange}
                            onReset={handleResetFilters}
                            title={t`Filter Transactions`}
                        />
                        {walletId && (
                            <Pill
                                withRemoveButton
                                onRemove={handleWalletRemoved}
                                data-testid="cashless-transactions-wallet-pill"
                            >
                                {filteredWallet
                                    ? `${filteredWallet.attendee_first_name ?? ''} ${filteredWallet.attendee_last_name ?? ''}`.trim()
                                    : t`Selected attendee`}
                            </Pill>
                        )}
                    </Group>
                )}
                resultCount={pagination?.total}
                resultLabel={t`transactions`}
            >
                <Button
                    onClick={() => handleExport(eventId)}
                    rightSection={<IconDownload size={14}/>}
                    color="green"
                    loading={downloadPending}
                    size="sm"
                    data-testid="cashless-transactions-export-button"
                >
                    {t`Export`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!transactions}/>

            {transactions && (
                <CashlessTransactionTable
                    transactions={transactions}
                    currency={event?.currency ?? 'USD'}
                    timezone={event?.timezone ?? 'UTC'}
                />
            )}

            {(!!transactions?.length && (pagination?.last_page || 0) > 1) && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={(value) => setSearchParams({pageNumber: value})}
                    total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
};

export default CashlessTransactions;
