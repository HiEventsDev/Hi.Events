import {Link, useParams} from "react-router";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerStats} from "../../../../../queries/useGetOrganizerStats.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import OrganizerReportTable from "../../../../common/OrganizerReportTable";
import {t} from "@lingui/macro";

const TaxSummaryReport = () => {
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
            key: 'event_name' as const,
            label: t`Event`,
            sortable: true,
            render: (value: string, row: any) => (
                <Link to={`/manage/event/${row.event_id}/dashboard`} style={{textDecoration: 'none', color: 'inherit'}}>
                    {value}
                </Link>
            )
        },
        {
            key: 'event_currency' as const,
            label: t`Currency`,
            sortable: true
        },
        {
            key: 'tax_name' as const,
            label: t`Tax Name`,
            sortable: true
        },
        {
            key: 'tax_rate' as const,
            label: t`Rate`,
            sortable: true,
            render: (value: number) => `${value}%`
        },
        {
            key: 'total_collected' as const,
            label: t`Total Collected`,
            sortable: true,
            render: (value: string, row: any) => formatCurrency(value, row.event_currency)
        },
        {
            key: 'order_count' as const,
            label: t`Orders`,
            sortable: true
        }
    ];

    return (
        <OrganizerReportTable
            title={t`Tax Summary`}
            columns={columns}
            isLoading={organizerQuery.isLoading}
            downloadFileName="tax_summary_report.csv"
            showDateFilter={true}
            organizer={organizer}
            showCurrencyFilter={true}
            availableCurrencies={allCurrencies}
        />
    );
};

export default TaxSummaryReport;
