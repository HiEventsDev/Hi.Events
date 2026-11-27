import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {percentOf} from "../../../../../utilites/reportTotals.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {EmptyCell} from "../../../../common/TanStackTable/cells.tsx";
import {OrganizerReport} from "../OrganizerReport.tsx";
import {EventLink} from "../EventLink.tsx";

interface CheckInSummaryRow {
    event_id: number;
    event_name: string;
    start_date: string | null;
    total_attendees: number;
    total_checked_in: number;
    check_in_rate: number;
    check_in_lists_count: number;
}

const CheckInSummaryReport = ({description}: { description: string }) => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);

    if (!organizer) {
        return null;
    }

    const timezone = organizer.timezone || 'UTC';

    const columns: ReportColumn<CheckInSummaryRow>[] = [
        {key: 'event_name', label: t`Event`, render: row => <EventLink eventId={row.event_id}>{row.event_name}</EventLink>},
        {
            key: 'start_date',
            label: t`Start date`,
            render: row => row.start_date ? formatDateWithLocale(row.start_date, 'shortDate', timezone) : <EmptyCell/>,
        },
        {key: 'total_attendees', label: t`Attendees`, kind: 'count'},
        {key: 'total_checked_in', label: t`Checked in`, kind: 'count'},
        {key: 'check_in_rate', label: t`Check-in rate`, kind: 'count', total: false, render: row => `${Number(row.check_in_rate)}%`},
        {key: 'check_in_lists_count', label: t`Check-in lists`, kind: 'count'},
    ];

    return (
        <OrganizerReport<CheckInSummaryRow>
            description={description}
            organizer={organizer}
            hasPeriod={false}
            currencyFilter="none"
            columns={columns}
            emptyMessage={t`Create an event to see its check-ins here.`}
            summary={sum => [
                {label: t`Attendees`, value: sum('total_attendees'), kind: 'count'},
                {label: t`Checked in`, value: sum('total_checked_in'), kind: 'count'},
                {label: t`Check-in rate`, value: `${percentOf(sum('total_checked_in'), sum('total_attendees'))}%`, kind: 'text'},
            ]}
        />
    );
};

export default CheckInSummaryReport;
