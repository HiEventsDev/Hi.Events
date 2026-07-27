import React, {useState} from "react";
import {useParams} from "react-router";
import {Button, Group} from "@mantine/core";
import {IconDownload} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../queries/useGetEvent";
import {useGetEventOrders} from "../../../queries/useGetEventOrders";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {OrdersTable} from "../../common/OrdersTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {Pagination} from "../../common/Pagination";
import {ToolBar} from "../../common/ToolBar";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync";
import {EventType, IdParam, QueryFilterOperator, QueryFilters} from "../../../types";
import {TableSkeleton} from "../../common/TableSkeleton";
import {orderClient} from "../../../api/order.client";
import {downloadBinary} from "../../../utilites/download";
import {FilterModal, FilterOption} from "../../common/FilterModal";
import {withLoadingNotification} from "../../../utilites/withLoadingNotification.tsx";
import {useGetEventOccurrences} from "../../../queries/useGetEventOccurrences";
import {SortSelector} from "../../common/SortSelector";
import {OccurrenceSelect} from "../../common/OccurrenceSelect";

const orderStatuses = [
    {label: t`Completed`, value: 'COMPLETED'},
    {label: t`Cancelled`, value: 'CANCELLED'},
    {label: t`Awaiting Offline Payment`, value: 'AWAITING_OFFLINE_PAYMENT'},
];

const refundStatuses = [
    {label: t`Refunded`, value: 'REFUNDED'},
    {label: t`Partially Refunded`, value: 'PARTIALLY_REFUNDED'},
];

export const Orders: React.FC = () => {
    const {eventId} = useParams<{ eventId: string }>();
    const {data: event} = useGetEvent(eventId);
    const isRecurring = event?.type === EventType.RECURRING;
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const ordersQuery = useGetEventOrders(eventId, searchParams as QueryFilters);
    const {data: occurrencesData} = useGetEventOccurrences(eventId, {pageNumber: 1, perPage: 100} as QueryFilters);
    const orders = ordersQuery?.data?.data;
    const pagination = ordersQuery?.data?.meta;
    const [downloadPending, setDownloadPending] = useState(false);

    const occurrences = occurrencesData?.data || [];
    const occurrenceFilter = searchParams.filterFields?.event_occurrence_id;
    const selectedOccurrenceId = (occurrenceFilter && !Array.isArray(occurrenceFilter) ? String(occurrenceFilter.value) : null);

    const filterOptions: FilterOption[] = [
        {
            field: 'status',
            label: t`Order Status`,
            type: 'multi-select',
            options: orderStatuses
        },
        {
            field: 'refund_status',
            label: t`Refund Status`,
            type: 'multi-select',
            options: refundStatuses
        }
    ];

    const handleOccurrenceChange = (value: string | null) => {
        const filterFields = {...(searchParams.filterFields || {})};
        if (value) {
            filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value};
        } else {
            delete filterFields.event_occurrence_id;
        }
        setSearchParams({
            ...searchParams,
            filterFields,
            pageNumber: 1,
        } as QueryFilters, true);
    };

    const handleFilterChange = (values: Record<string, any>) => {
        const filterFields: any = {};

        if (selectedOccurrenceId) {
            filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value: selectedOccurrenceId};
        }

        if (values.status?.length > 0) {
            filterFields.status = {operator: QueryFilterOperator.In, value: values.status};
        }
        if (values.refund_status?.length > 0) {
            filterFields.refund_status = {operator: QueryFilterOperator.In, value: values.refund_status};
        }

        setSearchParams({
            ...searchParams,
            filterFields,
            pageNumber: 1,
        } as QueryFilters, true);
    };

    const handleResetFilters = () => {
        const filterFields: any = {};
        if (selectedOccurrenceId) {
            filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value: selectedOccurrenceId};
        }
        setSearchParams({
            ...searchParams,
            filterFields,
            pageNumber: 1,
        } as QueryFilters, true);
    };

    const handleExport = async (eventId: IdParam) => {
        const occurrenceId = selectedOccurrenceId ? Number(selectedOccurrenceId) : null;
        await withLoadingNotification(async () => {
                setDownloadPending(true);
                const blob = await orderClient.exportOrders(eventId, occurrenceId);
                downloadBinary(blob, 'orders.xlsx');
            },
            {
                loading: {
                    title: t`Exporting Orders`,
                    message: t`Please wait while we prepare your orders for export...`
                },
                success: {
                    title: t`Orders Exported`,
                    message: t`Your orders have been exported successfully.`,
                    onRun: () => setDownloadPending(false)
                },
                error: {
                    title: t`Failed to export orders`,
                    message: t`Please try again.`,
                    onRun: () => setDownloadPending(false)
                }
            });
    };

    const getFilterValue = (field: any): any[] => {
        if (!field) return [];
        if (Array.isArray(field)) return field;
        if (Array.isArray(field.value)) return field.value;
        return field.value ? [field.value] : [];
    };

    const currentFilters = {
        status: getFilterValue(searchParams.filterFields?.status),
        refund_status: getFilterValue(searchParams.filterFields?.refund_status),
    };

    return (
        <PageBody>
            <PageTitle
                subheading={t`View order details, issue refunds, and resend confirmations.`}
            >{t`Orders`}</PageTitle>
            <ToolBar
                searchComponent={() => (
                    <SearchBarWrapper
                        placeholder={t`Search by name, email, or order #...`}
                        setSearchParams={setSearchParams}
                        searchParams={searchParams}
                    />
                )}
                filterComponent={
                    <Group gap="sm" wrap="wrap">
                        {pagination?.allowed_sorts && (
                            <SortSelector
                                selected={searchParams.sortBy && searchParams.sortDirection
                                    ? searchParams.sortBy + ':' + searchParams.sortDirection
                                    : pagination.default_sort + ':' + pagination.default_sort_direction}
                                options={pagination.allowed_sorts}
                                onSortSelect={(key, sortDirection) => {
                                    setSearchParams({sortBy: key, sortDirection});
                                }}
                            />
                        )}
                        {isRecurring && occurrences.length > 0 && event?.timezone && (
                            <OccurrenceSelect
                                occurrences={occurrences}
                                timezone={event.timezone}
                                value={selectedOccurrenceId}
                                onChange={handleOccurrenceChange}
                                placeholder={t`All Dates`}
                                clearable
                                size="sm"
                            />
                        )}
                        <FilterModal
                            filters={filterOptions}
                            activeFilters={currentFilters}
                            onChange={handleFilterChange}
                            onReset={handleResetFilters}
                            title={t`Filter Orders`}
                        />
                    </Group>
                }
                resultCount={pagination?.total}
                resultLabel={t`orders`}
            >
                <Button
                    onClick={() => handleExport(eventId)}
                    rightSection={<IconDownload size={14}/>}
                    color="green"
                    loading={downloadPending}
                    size="sm"
                >
                    {t`Export`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!orders || ordersQuery.isFetching}/>

            {orders && event && (
                <OrdersTable event={event} orders={orders}/>
            )}

            {!!orders?.length && (
                <Pagination
                    value={searchParams.pageNumber}
                    onChange={(value) => setSearchParams({pageNumber: value})}
                    total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
};

export default Orders;
