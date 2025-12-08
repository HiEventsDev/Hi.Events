import {Link, useParams} from "react-router";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import OrganizerReportTable from "../../../../common/OrganizerReportTable";
import {t} from "@lingui/macro";

const CheckInSummaryReport = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const organizer = organizerQuery.data;

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
            key: 'start_date' as const,
            label: t`Event Date`,
            sortable: true,
            render: (value: string) => value ? formatDateWithLocale(value, 'shortDate', organizer?.timezone || 'UTC') : '-'
        },
        {
            key: 'total_attendees' as const,
            label: t`Total Attendees`,
            sortable: true
        },
        {
            key: 'total_checked_in' as const,
            label: t`Checked In`,
            sortable: true
        },
        {
            key: 'check_in_rate' as const,
            label: t`Check-in Rate`,
            sortable: true,
            render: (value: number) => `${value}%`
        },
        {
            key: 'check_in_lists_count' as const,
            label: t`Check-in Lists`,
            sortable: true
        }
    ];

    return (
        <OrganizerReportTable
            title={t`Check-in Summary`}
            columns={columns}
            isLoading={organizerQuery.isLoading}
            downloadFileName="check_in_summary_report.csv"
            showDateFilter={false}
            organizer={organizer}
            showCurrencyFilter={false}
        />
    );
};

export default CheckInSummaryReport;
