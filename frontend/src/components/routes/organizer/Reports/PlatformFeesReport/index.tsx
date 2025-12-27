import {Link, useParams} from "react-router";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerStats} from "../../../../../queries/useGetOrganizerStats.ts";
import {useGetOrganizerEvents} from "../../../../../queries/useGetOrganizerEvents.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import OrganizerReportTable from "../../../../common/OrganizerReportTable";
import {t} from "@lingui/macro";
import {Alert, Select} from "@mantine/core";
import {IconAlertTriangle} from "@tabler/icons-react";
import {useState} from "react";
import classes from "./PlatformFeesReport.module.scss";

const PlatformFeesReport = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);
    const organizer = organizerQuery.data;
    const [selectedEventId, setSelectedEventId] = useState<string | null>(null);

    const statsQuery = useGetOrganizerStats(organizerId, organizer?.currency);
    const allCurrencies = statsQuery.data?.all_organizers_currencies || [];

    const eventsQuery = useGetOrganizerEvents(organizerId, {
        pageNumber: 1,
        perPage: 100,
    });
    const events = eventsQuery.data?.data || [];

    if (!organizer) {
        return null;
    }

    const eventOptions = [
        {value: '', label: t`All Events`},
        ...events.map(event => ({
            value: String(event.id),
            label: event.title || ''
        }))
    ];

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
            key: 'payment_date' as const,
            label: t`Payment Date`,
            sortable: true,
            render: (value: string) => value ? new Date(value).toLocaleDateString() : '-'
        },
        {
            key: 'order_reference' as const,
            label: t`Order Ref`,
            sortable: false,
            render: (value: string, row: any) => (
                <Link to={`/manage/event/${row.event_id}/orders/${row.order_id}`} style={{textDecoration: 'none', color: 'inherit'}}>
                    {value}
                </Link>
            )
        },
        {
            key: 'amount_paid' as const,
            label: t`Amount Paid`,
            sortable: true,
            render: (value: number, row: any) => formatCurrency(value, row.currency)
        },
        {
            key: 'fee_amount' as const,
            label: t`Hi.Events Fee`,
            sortable: true,
            render: (value: number, row: any) => formatCurrency(value, row.currency)
        },
        {
            key: 'vat_rate' as const,
            label: t`VAT Rate`,
            sortable: false,
            render: (value: number) => value ? `${(value * 100).toFixed(0)}%` : '-'
        },
        {
            key: 'vat_amount' as const,
            label: t`VAT on Fee`,
            sortable: true,
            render: (value: number, row: any) => value ? formatCurrency(value, row.currency) : '-'
        },
        {
            key: 'total_fee' as const,
            label: t`Total Fee`,
            sortable: true,
            render: (value: number, row: any) => formatCurrency(value, row.currency)
        },
        {
            key: 'currency' as const,
            label: t`Currency`,
            sortable: false
        },
        {
            key: 'payment_intent_id' as const,
            label: t`Stripe Payment ID`,
            sortable: false,
            render: (value: string) => value || '-'
        }
    ];

    return (
        <>
            <Alert
                icon={<IconAlertTriangle size={16} />}
                title={t`Important Notice`}
                color="yellow"
                mb="lg"
            >
                {t`This report is for informational purposes only. Always consult with a tax professional before using this data for accounting or tax purposes. Please cross-reference with your Stripe dashboard as Hi.Events may be missing historical data.`}
            </Alert>

            <div className={classes.eventFilter}>
                <Select
                    label={t`Filter by Event`}
                    placeholder={t`Select an event`}
                    data={eventOptions}
                    value={selectedEventId}
                    onChange={setSelectedEventId}
                    clearable
                    searchable
                />
            </div>

            <OrganizerReportTable
                title={t`Platform Fees Report`}
                columns={columns}
                isLoading={organizerQuery.isLoading}
                showDateFilter={true}
                organizer={organizer}
                showCurrencyFilter={true}
                availableCurrencies={allCurrencies}
                eventId={selectedEventId ? parseInt(selectedEventId) : null}
            />
        </>
    );
};

export default PlatformFeesReport;
