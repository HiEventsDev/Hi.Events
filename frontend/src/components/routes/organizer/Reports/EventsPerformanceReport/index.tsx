import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {StatusBadge, StatusTone} from "../../../../common/StatusBadge";
import {EmptyCell} from "../../../../common/TanStackTable/cells.tsx";
import {OrganizerReport} from "../OrganizerReport.tsx";
import {EventLink} from "../EventLink.tsx";

interface EventsPerformanceRow {
    event_id: number;
    event_name: string;
    event_currency: string;
    start_date: string | null;
    event_state: string;
    products_sold: number;
    gross_revenue: string;
    total_refunded: string;
    net_revenue: string;
    total_tax: string;
    total_fee: string;
    total_orders: number;
    unique_customers: number;
    page_views: number;
}

const stateTone = (state: string): StatusTone => ({
    ongoing: 'positive',
    on_sale: 'active',
    upcoming: 'waiting',
} as Record<string, StatusTone>)[state] ?? 'neutral';

const stateLabel = (state: string): string => ({
    past: t`Past`,
    ongoing: t`Ongoing`,
    on_sale: t`On Sale`,
    upcoming: t`Upcoming`,
} as Record<string, string>)[state] ?? state;

const EventsPerformanceReport = ({description}: { description: string }) => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);

    if (!organizer) {
        return null;
    }

    const timezone = organizer.timezone || 'UTC';

    const columns: ReportColumn<EventsPerformanceRow>[] = [
        {key: 'event_name', label: t`Event`, render: row => <EventLink eventId={row.event_id}>{row.event_name}</EventLink>},
        {
            key: 'start_date',
            label: t`Start date`,
            render: row => row.start_date ? formatDateWithLocale(row.start_date, 'shortDate', timezone) : <EmptyCell/>,
        },
        {
            key: 'event_state',
            label: t`Status`,
            render: row => <StatusBadge tone={stateTone(row.event_state)}>{stateLabel(row.event_state)}</StatusBadge>,
        },
        {key: 'event_currency', label: t`Currency`},
        {key: 'gross_revenue', label: t`Gross sales`, kind: 'money'},
        {key: 'total_refunded', label: t`Refunded`, kind: 'money'},
        {key: 'net_revenue', label: t`After refunds`, kind: 'money'},
        {key: 'total_tax', label: t`Tax`, kind: 'money'},
        {key: 'total_fee', label: t`Service fees`, kind: 'money'},
        {key: 'total_orders', label: t`Orders`, kind: 'count'},
        {key: 'products_sold', label: t`Products sold`, kind: 'count'},
        {key: 'unique_customers', label: t`Unique customers`, kind: 'count', total: false},
        {key: 'page_views', label: t`Page views`, kind: 'count'},
    ];

    return (
        <OrganizerReport<EventsPerformanceRow>
            description={description}
            organizer={organizer}
            hasPeriod={false}
            columns={columns}
            rowCurrency={row => row.event_currency}
            emptyMessage={t`Create an event to see how it performs here.`}
            summary={(sum, rows) => [
                {label: t`Events`, value: rows.length, kind: 'count'},
                {label: t`Gross sales`, value: sum('gross_revenue'), kind: 'money'},
                {label: t`Orders`, value: sum('total_orders'), kind: 'count'},
                {label: t`Products sold`, value: sum('products_sold'), kind: 'count'},
            ]}
        />
    );
};

export default EventsPerformanceReport;
