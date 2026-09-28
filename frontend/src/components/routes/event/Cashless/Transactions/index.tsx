import {t} from "@lingui/macro";
import {Button, Group, Pill, SegmentedControl, Select} from "@mantine/core";
import {IconDownload} from "@tabler/icons-react";
import {useState} from "react";
import {useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {PageTitle} from "../../../../common/PageTitle";
import {ToolBar} from "../../../../common/ToolBar";
import {SearchBarWrapper} from "../../../../common/SearchBar";
import {SortSelector} from "../../../../common/SortSelector";
import {TableSkeleton} from "../../../../common/TableSkeleton";
import {Pagination} from "../../../../common/Pagination";
import {CashlessTransactionTable} from "../../../../common/CashlessTransactionTable";
import {useFilterQueryParamSync} from "../../../../../hooks/useFilterQueryParamSync.ts";
import {useGetCashlessTransactions} from "../../../../../queries/useGetCashlessTransactions.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useGetCashlessSalesPoints} from "../../../../../queries/useGetCashlessSalesPoints.ts";
import {useGetCashlessWallet} from "../../../../../queries/useGetCashlessWallet.ts";
import {cashlessClient} from "../../../../../api/cashless.client.ts";
import {downloadBinary} from "../../../../../utilites/download.ts";
import {withLoadingNotification} from "../../../../../utilites/withLoadingNotification.tsx";
import {IdParam, QueryFilterOperator, QueryFilters} from "../../../../../types.ts";

const KIND_FILTERS: Record<string, string[]> = {
    all: [],
    topups: ['TOPUP_ONLINE', 'TOPUP_STAFF'],
    purchases: ['PURCHASE'],
    reversals: ['REVERSAL'],
    refunds: ['REFUND_REMAINING'],
    closure: ['CLOSURE'],
};

const singleFilterValue = (condition: unknown): string | null => {
    if (!condition || Array.isArray(condition)) {
        return null;
    }

    return String((condition as { value: string | string[] }).value);
};

const activeKind = (condition: unknown): string => {
    const raw = (condition as { value?: string | string[] } | undefined)?.value;

    if (!raw) {
        return 'all';
    }

    const values = Array.isArray(raw) ? raw : [raw];

    return Object.entries(KIND_FILTERS).find(
        ([, kinds]) => kinds.length === values.length && kinds.every((kind) => values.includes(kind)),
    )?.[0] ?? 'all';
};

const CashlessTransactions = () => {
    const {eventId} = useParams();
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const {data: event} = useGetEvent(eventId);
    const {data: transactionsData} = useGetCashlessTransactions(eventId, searchParams as QueryFilters);
    const [downloadPending, setDownloadPending] = useState(false);
    const {data: salesPointsData} = useGetCashlessSalesPoints(eventId, {pageNumber: 1, perPage: 100} as QueryFilters);

    const walletId = singleFilterValue(searchParams.filterFields?.cashless_wallet_id);
    const salesPointId = singleFilterValue(searchParams.filterFields?.cashless_sales_point_id);
    const {data: filteredWallet} = useGetCashlessWallet(eventId, walletId ?? undefined);

    const applyFilters = (changes: Record<string, { operator: QueryFilterOperator; value: string | string[] } | null>) => {
        const filterFields: Record<string, unknown> = {...(searchParams.filterFields || {})};

        Object.entries(changes).forEach(([field, condition]) => {
            if (condition) {
                filterFields[field] = condition;
            } else {
                delete filterFields[field];
            }
        });

        setSearchParams({...searchParams, filterFields, pageNumber: 1} as QueryFilters, true);
    };

    const handleKindChange = (kind: string) => {
        const kinds = KIND_FILTERS[kind];

        applyFilters({type: kinds.length > 0 ? {operator: QueryFilterOperator.In, value: kinds} : null});
    };

    const transactions = transactionsData?.data;
    const pagination = transactionsData?.meta;

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
                        <SegmentedControl
                            size="xs"
                            value={activeKind(searchParams.filterFields?.type)}
                            onChange={handleKindChange}
                            data={[
                                {value: 'all', label: t`All`},
                                {value: 'topups', label: t`Top-ups`},
                                {value: 'purchases', label: t`Purchases`},
                                {value: 'reversals', label: t`Reversals`},
                                {value: 'refunds', label: t`Refunds`},
                                {value: 'closure', label: t`Closure`},
                            ]}
                            data-testid="cashless-transactions-kind-filter"
                        />
                        <Select
                            size="sm"
                            clearable
                            placeholder={t`All sales points`}
                            value={salesPointId}
                            onChange={(value) => applyFilters({
                                cashless_sales_point_id: value ? {operator: QueryFilterOperator.Equals, value} : null,
                            })}
                            data={salesPointsData?.data?.map((salesPoint) => ({
                                value: String(salesPoint.id),
                                label: salesPoint.name,
                            })) ?? []}
                            data-testid="cashless-transactions-sales-point-filter"
                        />
                        {pagination?.allowed_sorts && (
                            <SortSelector
                                selected={searchParams.sortBy && searchParams.sortDirection
                                    ? searchParams.sortBy + ':' + searchParams.sortDirection
                                    : pagination.default_sort + ':' + pagination.default_sort_direction}
                                options={pagination.allowed_sorts}
                                onSortSelect={(key, sortDirection) => setSearchParams({sortBy: key, sortDirection})}
                            />
                        )}
                        {walletId && (
                            <Pill
                                withRemoveButton
                                onRemove={() => applyFilters({cashless_wallet_id: null})}
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
