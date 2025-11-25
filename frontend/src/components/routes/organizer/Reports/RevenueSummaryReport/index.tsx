import {useParams} from "react-router";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerStats} from "../../../../../queries/useGetOrganizerStats.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDate} from "../../../../../utilites/dates.ts";
import OrganizerReportTable from "../../../../common/OrganizerReportTable";
import {t} from "@lingui/macro";

const RevenueSummaryReport = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const organizer = organizerQuery.data;

    const statsQuery = useGetOrganizerStats(organizerId, organizer?.currency);
    const allCurrencies = statsQuery.data?.all_organizers_currencies || [];

    if (!organizer) {
        return null;
    }

    const columns = [
        {
            key: 'date' as const,
            label: t`Date`,
            sortable: true,
            render: (value: string) => formatDate(value, 'MMM D, YYYY', organizer?.timezone)
        },
        {
            key: 'gross_sales' as const,
            label: t`Gross Sales`,
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'net_revenue' as const,
            label: t`Net Revenue`,
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'total_refunded' as const,
            label: t`Refunds`,
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'total_tax' as const,
            label: t`Taxes`,
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'total_fee' as const,
            label: t`Fees`,
            sortable: true,
            render: (value: string) => formatCurrency(value)
        },
        {
            key: 'order_count' as const,
            label: t`Orders`,
            sortable: true
        }
    ];

    return (
        <OrganizerReportTable
            title={t`Revenue Summary`}
            columns={columns}
            isLoading={organizerQuery.isLoading}
            downloadFileName="revenue_summary_report.csv"
            showDateFilter={true}
            organizer={organizer}
            showCurrencyFilter={true}
            availableCurrencies={allCurrencies}
        />
    );
};

export default RevenueSummaryReport;
