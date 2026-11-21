import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {OrganizerReport} from "../OrganizerReport.tsx";

interface RevenueSummaryRow {
    date: string;
    gross_sales: string;
    net_revenue: string;
    total_refunded: string;
    total_tax: string;
    total_fee: string;
    order_count: number;
}

const RevenueSummaryReport = ({description}: { description: string }) => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);

    if (!organizer) {
        return null;
    }

    const columns: ReportColumn<RevenueSummaryRow>[] = [
        {key: 'date', label: t`Date`, render: row => formatDateWithLocale(row.date, 'shortDate', 'UTC')},
        {key: 'gross_sales', label: t`Gross sales`, kind: 'money'},
        {key: 'total_refunded', label: t`Refunded`, kind: 'money'},
        {key: 'net_revenue', label: t`After refunds`, kind: 'money'},
        {key: 'total_tax', label: t`Tax`, kind: 'money'},
        {key: 'total_fee', label: t`Service fees`, kind: 'money'},
        {key: 'order_count', label: t`Orders`, kind: 'count'},
    ];

    return (
        <OrganizerReport<RevenueSummaryRow>
            description={description}
            organizer={organizer}
            currencyFilter="required"
            columns={columns}
            emptyMessage={t`Revenue will appear here once orders are completed.`}
            summary={sum => [
                {label: t`Gross sales`, value: sum('gross_sales'), kind: 'money'},
                {label: t`Refunded`, value: sum('total_refunded'), kind: 'money'},
                {label: t`After refunds`, value: sum('net_revenue'), kind: 'money'},
                {label: t`Orders`, value: sum('order_count'), kind: 'count'},
            ]}
            chart={{
                dateKey: 'date',
                valueKey: 'gross_sales',
                label: t`Gross sales per day`,
                kind: 'money',
                formatDate: date => formatDateWithLocale(date, 'chartDate', 'UTC'),
            }}
            emptyRows={{
                isEmpty: row => Number(row.order_count) === 0 && Number(row.gross_sales) === 0 && Number(row.total_refunded) === 0,
                toggleLabel: t`Show days with no sales`,
                allHiddenMessage: t`No sales or refunds in this period.`,
            }}
        />
    );
};

export default RevenueSummaryReport;
