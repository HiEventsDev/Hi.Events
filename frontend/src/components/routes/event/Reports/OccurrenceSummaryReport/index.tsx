import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {formatNumber} from "../../../../../utilites/helpers.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {StatusBadge, StatusTone} from "../../../../common/StatusBadge";
import {EventReport} from "../EventReport.tsx";

interface OccurrenceSummaryRow {
    occurrence_id: number;
    start_date: string;
    label: string | null;
    status: string;
    capacity: number | null;
    used_capacity: number;
    attendees_registered: number;
    products_sold: number;
    orders_created: number;
    total_gross: string;
    total_tax: string;
    checked_in: number;
}

const statusTone = (status: string): StatusTone => ({
    ACTIVE: 'positive',
    CANCELLED: 'negative',
    SOLD_OUT: 'waiting',
} as Record<string, StatusTone>)[status] ?? 'neutral';

const statusLabel = (status: string): string => ({
    ACTIVE: t`Active`,
    CANCELLED: t`Cancelled`,
    SOLD_OUT: t`Sold Out`,
} as Record<string, string>)[status] ?? status;

const OccurrenceSummaryReport = ({description}: { description: string }) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);

    if (!event) {
        return null;
    }

    const capacityUsed = (row: OccurrenceSummaryRow) => {
        const used = formatNumber(Number(row.used_capacity));
        const capacity = formatNumber(Number(row.capacity));

        return row.capacity ? t`${used} of ${capacity}` : t`${used} (no limit)`;
    };

    const columns: ReportColumn<OccurrenceSummaryRow>[] = [
        {
            key: 'start_date',
            label: t`Date`,
            render: row => formatDateWithLocale(row.start_date, 'shortDateTime', event.timezone),
        },
        {key: 'label', label: t`Label`},
        {
            key: 'status',
            label: t`Status`,
            render: row => <StatusBadge tone={statusTone(row.status)}>{statusLabel(row.status)}</StatusBadge>,
            csv: row => statusLabel(row.status),
        },
        {key: 'attendees_registered', label: t`Attendees`, kind: 'count'},
        {key: 'checked_in', label: t`Checked in`, kind: 'count'},
        {key: 'used_capacity', label: t`Capacity used`, kind: 'count', total: false, render: capacityUsed, csv: capacityUsed},
        {key: 'products_sold', label: t`Products sold`, kind: 'count'},
        {key: 'orders_created', label: t`Orders`, kind: 'count'},
        {key: 'total_gross', label: t`Gross sales`, kind: 'money'},
        {key: 'total_tax', label: t`Tax`, kind: 'money'},
    ];

    return (
        <EventReport<OccurrenceSummaryRow>
            description={description}
            event={event}
            fileName="occurrence_summary_report.csv"
            columns={columns}
            hasPeriod={false}
            hasOccurrenceFilter={false}
            emptyMessage={t`Add dates to this event's schedule to see them here.`}
            summary={sum => [
                {label: t`Attendees`, value: sum('attendees_registered'), kind: 'count'},
                {label: t`Checked in`, value: sum('checked_in'), kind: 'count'},
                {label: t`Orders`, value: sum('orders_created'), kind: 'count'},
                {label: t`Gross sales`, value: sum('total_gross'), kind: 'money'},
            ]}
        />
    );
};

export default OccurrenceSummaryReport;
