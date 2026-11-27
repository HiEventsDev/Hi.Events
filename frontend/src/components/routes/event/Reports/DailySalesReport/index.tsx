import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {useReportParams} from "../../../../../hooks/useReportFilters.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {EventReport} from "../EventReport.tsx";

interface DailySalesRow {
    date: string;
    sales_total_gross: string;
    sales_total_before_additions: string;
    total_tax: string;
    total_fee: string;
    total_refunded: string;
    orders_created: number;
    products_sold: number;
    total_views: number;
}

const formatDay = (date: string) => formatDateWithLocale(date, 'shortDate', 'UTC');

const DailySalesReport = ({description}: { description: string }) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const isFilteredByOccurrence = !!useReportParams().param('occurrence');

    if (!event) {
        return null;
    }

    const columns: ReportColumn<DailySalesRow>[] = [
        {key: 'date', label: t`Date`, render: row => formatDay(row.date), csv: row => row.date.slice(0, 10)},
        {key: 'sales_total_gross', label: t`Gross sales`, kind: 'money'},
        {key: 'sales_total_before_additions', label: t`Sales before tax and fees`, kind: 'money'},
        {key: 'total_tax', label: t`Tax`, kind: 'money'},
        {key: 'total_fee', label: t`Service fees`, kind: 'money'},
        {key: 'total_refunded', label: t`Refunded`, kind: 'money'},
        {key: 'orders_created', label: t`Orders`, kind: 'count'},
        {key: 'products_sold', label: t`Products sold`, kind: 'count'},
        ...(isFilteredByOccurrence ? [] : [{key: 'total_views', label: t`Page views`, kind: 'count'} as ReportColumn<DailySalesRow>]),
    ];

    return (
        <EventReport<DailySalesRow>
            description={description}
            event={event}
            fileName="daily_sales_report.csv"
            columns={columns}
            emptyMessage={t`Sales will appear here once orders are completed.`}
            summary={sum => [
                {label: t`Gross sales`, value: sum('sales_total_gross'), kind: 'money'},
                {label: t`Tax`, value: sum('total_tax'), kind: 'money'},
                {label: t`Refunded`, value: sum('total_refunded'), kind: 'money'},
                {label: t`Orders`, value: sum('orders_created'), kind: 'count'},
            ]}
            chart={{
                dateKey: 'date',
                valueKey: 'sales_total_gross',
                label: t`Gross sales per day`,
                kind: 'money',
                formatDate: date => formatDateWithLocale(date, 'chartDate', 'UTC'),
            }}
            emptyRows={{
                isEmpty: row => Number(row.orders_created) === 0 && Number(row.sales_total_gross) === 0 && Number(row.total_refunded) === 0,
                toggleLabel: t`Show days with no sales`,
                allHiddenMessage: t`No sales or refunds in this period.`,
            }}
        />
    );
};

export default DailySalesReport;
