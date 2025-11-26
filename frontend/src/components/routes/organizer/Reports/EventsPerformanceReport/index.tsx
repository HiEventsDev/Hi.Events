import {Link, useParams} from "react-router";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerStats} from "../../../../../queries/useGetOrganizerStats.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDate} from "../../../../../utilites/dates.ts";
import OrganizerReportTable from "../../../../common/OrganizerReportTable";
import {t} from "@lingui/macro";
import {Badge} from "@mantine/core";

const EventsPerformanceReport = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const organizer = organizerQuery.data;

    const statsQuery = useGetOrganizerStats(organizerId, organizer?.currency);
    const allCurrencies = statsQuery.data?.all_organizers_currencies || [];

    if (!organizer) {
        return null;
    }

    const getStatusBadge = (eventState: string) => {
        const colors: Record<string, string> = {
            'past': 'gray',
            'ongoing': 'green',
            'on_sale': 'blue',
            'upcoming': 'orange',
        };
        const labels: Record<string, string> = {
            'past': t`Past`,
            'ongoing': t`Ongoing`,
            'on_sale': t`On Sale`,
            'upcoming': t`Upcoming`,
        };
        return <Badge color={colors[eventState] || 'gray'}>{labels[eventState] || eventState}</Badge>;
    };

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
            key: 'start_date' as const,
            label: t`Date`,
            sortable: true,
            render: (value: string) => value ? formatDate(value, 'MMM D, YYYY', organizer?.timezone) : '-'
        },
        {
            key: 'event_state' as const,
            label: t`Status`,
            sortable: true,
            render: (value: string) => getStatusBadge(value)
        },
        {
            key: 'products_sold' as const,
            label: t`Products Sold`,
            sortable: true
        },
        {
            key: 'gross_revenue' as const,
            label: t`Gross Revenue`,
            sortable: true,
            render: (value: string, row: any) => formatCurrency(value, row.event_currency)
        },
        {
            key: 'total_refunded' as const,
            label: t`Refunds`,
            sortable: true,
            render: (value: string, row: any) => formatCurrency(value, row.event_currency)
        },
        {
            key: 'net_revenue' as const,
            label: t`Net Revenue`,
            sortable: true,
            render: (value: string, row: any) => formatCurrency(value, row.event_currency)
        },
        {
            key: 'total_tax' as const,
            label: t`Total Tax`,
            sortable: true,
            render: (value: string, row: any) => formatCurrency(value, row.event_currency)
        },
        {
            key: 'total_fee' as const,
            label: t`Total Fees`,
            sortable: true,
            render: (value: string, row: any) => formatCurrency(value, row.event_currency)
        },
        {
            key: 'total_orders' as const,
            label: t`Orders`,
            sortable: true
        },
        {
            key: 'unique_customers' as const,
            label: t`Customers`,
            sortable: true
        },
        {
            key: 'page_views' as const,
            label: t`Page Views`,
            sortable: true
        }
    ];

    return (
        <OrganizerReportTable
            title={t`Events Performance`}
            columns={columns}
            isLoading={organizerQuery.isLoading}
            downloadFileName="events_performance_report.csv"
            showDateFilter={false}
            organizer={organizer}
            showCurrencyFilter={true}
            availableCurrencies={allCurrencies}
        />
    );
};

export default EventsPerformanceReport;
