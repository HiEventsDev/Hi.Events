import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {PageTitle} from "../../common/PageTitle";
import {PageBody} from "../../common/PageBody";
import {useGetEventOrders} from "../../../queries/useGetEventOrders.ts";
import {OrdersTable} from "../../common/OrdersTable";
import {SearchBarWrapper} from "../../common/SearchBar";
import {Pagination} from "../../common/Pagination";
import {IconDownload} from "@tabler/icons-react";
import {ToolBar} from "../../common/ToolBar";
import {useFilterQueryParamSync} from "../../../hooks/useFilterQueryParamSync.ts";
import {IdParam, QueryFilters} from "../../../types.ts";
import {TableSkeleton} from "../../common/TableSkeleton";
import {orderClient} from "../../../api/order.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {useState} from "react";
import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {showError} from "../../../utilites/notifications.tsx";

export const Orders = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const [searchParams, setSearchParams] = useFilterQueryParamSync();
    const ordersQuery = useGetEventOrders(eventId, searchParams as QueryFilters);
    const orders = ordersQuery?.data?.data;
    const pagination = ordersQuery?.data?.meta;
    const [downloadPending, setDownloadPending] = useState(false);

    const handleExport = (eventId: IdParam) => {
        setDownloadPending(true);
        orderClient.exportOrders(eventId)
            .then(blob => {
                downloadBinary(blob, 'orders.xlsx');
                setDownloadPending(false);
            }).catch(() => {
            setDownloadPending(false);
            showError(t`Failed to export orders. Please try again.`)
        });
    }

    return (
        <PageBody>
            <PageTitle>{t`Orders`}</PageTitle>
            <ToolBar searchComponent={() => (
                <SearchBarWrapper
                    placeholder={t`Search by name, email, or order #...`}
                    setSearchParams={setSearchParams}
                    searchParams={searchParams}
                    pagination={pagination}
                />
            )}>
                <Button
                    onClick={() => handleExport(eventId)}
                    rightSection={<IconDownload size={14}/>}
                    color={'green'}
                    loading={downloadPending}>
                    {t`Export`}
                </Button>
            </ToolBar>

            <TableSkeleton isVisible={!orders || ordersQuery.isFetching}/>

            {(orders && event) && (
                <OrdersTable event={event} orders={orders}/>
            )}

            {!!orders?.length && (
                <Pagination value={searchParams.pageNumber}
                            onChange={(value) => setSearchParams({pageNumber: value})}
                            total={Number(pagination?.last_page)}
                />
            )}
        </PageBody>
    );
};

export default Orders;