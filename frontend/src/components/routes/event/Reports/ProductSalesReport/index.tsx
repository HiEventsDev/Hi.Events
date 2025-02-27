import {useParams} from "react-router";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import ReportTable from "../../../../common/ReportTable";

const ProductSalesReport = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;

    if (!event) {
        return null;
    }

    const columns = [
        {
            key: 'product_title' as const,
            label: 'Product Title',
            sortable: true
        },
        {
            key: 'number_sold' as const,
            label: 'Units Sold',
            sortable: true
        },
        {
            key: 'total_gross' as const,
            label: 'Gross Sales',
            sortable: true,
            render: (value: string) => formatCurrency(value, event?.currency)
        },
        {
            key: 'total_tax' as const,
            label: 'Tax',
            sortable: true,
            render: (value: string) => formatCurrency(value, event?.currency)
        },
        {
            key: 'total_service_fees' as const,
            label: 'Service Fees',
            sortable: true,
            render: (value: string) => formatCurrency(value, event?.currency)
        }
    ];

    return (
        <ReportTable
            title="Product Sales Report"
            columns={columns}
            isLoading={eventQuery.isLoading}
            downloadFileName="product_sales_report.csv"
            event={event}
        />
    );
};

export default ProductSalesReport;
