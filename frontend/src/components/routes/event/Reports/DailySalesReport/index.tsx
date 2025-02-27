import {useParams} from "react-router";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDate} from "../../../../../utilites/dates.ts";
import ReportTable from "../../../../common/ReportTable";

export const DailySalesReport = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;

    if (!event) {
        return null;
    }

    const columns = [
        {
            key: 'date' as const,
            label: 'Date',
            sortable: true,
            render: (value: string) => formatDate(value, 'MMM D, YYYY', event?.timezone)
        },
        {
            key: 'sales_total_gross' as const,
            label: 'Sales Total Gross',
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'total_tax' as const,
            label: 'Total Tax',
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'sales_total_before_additions' as const,
            label: 'Net Sales',
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'products_sold' as const,
            label: 'Products Sold',
            sortable: true
        },
        {
            key: 'orders_created' as const,
            label: 'Completed Orders',
            sortable: true
        },
        {
            key: 'total_fee' as const,
            label: 'Total Fee',
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'total_refunded' as const,
            label: 'Total Refunded',
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'total_views' as const,
            label: 'Total Views',
            sortable: true
        }
    ];

    return (
        <ReportTable
            title="Daily Sales Report"
            columns={columns}
            isLoading={eventQuery.isLoading}
            downloadFileName="daily_sales_report.csv"
            showDateFilter={true}
            event={event}
        />
    );
};
