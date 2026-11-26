import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {OrganizerReport} from "../OrganizerReport.tsx";
import {EventLink} from "../EventLink.tsx";

interface TaxSummaryRow {
    event_id: number;
    event_name: string;
    event_currency: string;
    tax_name: string;
    tax_rate: number;
    total_collected: string;
    order_count: number;
    total_order_count: number;
}

const distinctOrderCount = (rows: TaxSummaryRow[]) => Number(rows[0]?.total_order_count ?? 0);

const TaxSummaryReport = ({description}: { description: string }) => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);

    if (!organizer) {
        return null;
    }

    const columns: ReportColumn<TaxSummaryRow>[] = [
        {key: 'event_name', label: t`Event`, render: row => <EventLink eventId={row.event_id}>{row.event_name}</EventLink>},
        {key: 'tax_name', label: t`Tax`},
        {key: 'tax_rate', label: t`Tax rate`, kind: 'count', total: false, render: row => `${Number(row.tax_rate)}%`},
        {key: 'event_currency', label: t`Currency`},
        {key: 'total_collected', label: t`Tax collected`, kind: 'money'},
        {key: 'order_count', label: t`Orders`, kind: 'count', totalValue: distinctOrderCount},
    ];

    return (
        <OrganizerReport<TaxSummaryRow>
            description={description}
            organizer={organizer}
            columns={columns}
            rowCurrency={row => row.event_currency}
            emptyMessage={t`No tax was collected on completed orders in this period.`}
            summary={(sum, rows) => [
                {label: t`Tax collected`, value: sum('total_collected'), kind: 'money'},
                {label: t`Orders`, value: distinctOrderCount(rows), kind: 'count'},
            ]}
        />
    );
};

export default TaxSummaryReport;
