import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {Badge} from "@mantine/core";
import ReportTable from "../../../../common/ReportTable";

const statusColor = (status: string) => {
    switch (status) {
        case 'ACTIVE': return 'green';
        case 'CANCELLED': return 'red';
        case 'SOLD_OUT': return 'orange';
        default: return 'gray';
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'ACTIVE': return t`Active`;
        case 'CANCELLED': return t`Cancelled`;
        case 'SOLD_OUT': return t`Sold Out`;
        default: return status;
    }
};

const OccurrenceSummaryReport = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;

    if (!event) {
        return null;
    }

    const columns = [
        {
            key: 'start_date' as const,
            label: t`Date`,
            sortable: true,
            render: (value: string) => formatDateWithLocale(value, 'fullDateTime', event.timezone),
        },
        {
            key: 'label' as const,
            label: t`Label`,
            sortable: true,
            render: (value: string) => value || '—',
        },
        {
            key: 'status' as const,
            label: t`Status`,
            sortable: true,
            render: (value: string) => (
                <Badge variant="light" color={statusColor(value)} size="sm">
                    {statusLabel(value)}
                </Badge>
            ),
        },
        {
            key: 'attendees_registered' as const,
            label: t`Attendees`,
            sortable: true,
        },
        {
            key: 'used_capacity' as const,
            label: t`Capacity Used`,
            sortable: true,
            render: (value: number, row: Record<string, any>) =>
                row.capacity ? `${value}/${row.capacity}` : String(value),
        },
        {
            key: 'products_sold' as const,
            label: t`Products Sold`,
            sortable: true,
        },
        {
            key: 'orders_created' as const,
            label: t`Orders`,
            sortable: true,
        },
        {
            key: 'total_gross' as const,
            label: t`Gross Sales`,
            sortable: true,
            render: (value: string) => formatCurrency(value, event.currency),
        },
        {
            key: 'total_tax' as const,
            label: t`Tax`,
            sortable: true,
            render: (value: string) => formatCurrency(value, event.currency),
        },
        {
            key: 'checked_in' as const,
            label: t`Checked In`,
            sortable: true,
        },
    ];

    return (
        <ReportTable
            title={t`Occurrence Summary`}
            columns={columns}
            isLoading={eventQuery.isLoading}
            downloadFileName="occurrence_summary_report.csv"
            event={event}
            showDateFilter={false}
            showOccurrenceFilter={false}
        />
    );
};

export default OccurrenceSummaryReport;
