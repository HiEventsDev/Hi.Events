import {useNavigate, useParams} from "react-router";
import {Select} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconInfoCircle} from "@tabler/icons-react";
import {useGetOrganizer} from "../../../../../queries/useGetOrganizer.ts";
import {useGetOrganizerEvents} from "../../../../../queries/useGetOrganizerEvents.ts";
import {useGetOrganizerStripeConnect} from "../../../../../queries/useGetOrganizerStripeConnect.ts";
import {useReportParams} from "../../../../../hooks/useReportFilters.ts";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {CellCopy, CellLink, CellText, EmptyCell} from "../../../../common/TanStackTable/cells.tsx";
import {OrganizerReport} from "../OrganizerReport.tsx";
import {EventLink} from "../EventLink.tsx";
import classes from "../../../../common/ReportView/ReportView.module.scss";

interface PlatformFeesRow {
    event_id: number;
    event_name: string;
    order_id: number;
    order_reference: string;
    payment_date: string | null;
    amount_paid: number;
    fee_amount: number;
    vat_rate: number | null;
    vat_amount: number | null;
    total_fee: number;
    currency: string;
    payment_intent_id: string | null;
    stripe_account_id: string | null;
}

const stripePaymentUrl = (row: PlatformFeesRow): string =>
    ['https://dashboard.stripe.com', row.stripe_account_id, 'payments', row.payment_intent_id].filter(Boolean).join('/');

const PlatformFeesReport = ({description}: { description: string }) => {
    const {organizerId} = useParams();
    const navigate = useNavigate();
    const {data: organizer} = useGetOrganizer(organizerId);
    const {param, setFilter} = useReportParams();
    const events = useGetOrganizerEvents(organizerId, {pageNumber: 1, perPage: 100}).data?.data ?? [];
    const canManagePayments = useAccess().can('MANAGE_PAYMENTS');
    const stripeAccounts = useGetOrganizerStripeConnect(organizerId, {enabled: canManagePayments})
        .data?.stripe_connect_accounts ?? [];
    const accountsWithStripeDashboard = new Set(stripeAccounts
        .filter(account => account.account_type?.toLowerCase() === 'standard')
        .map(account => account.stripe_account_id));
    const canOpenInStripe = (row: PlatformFeesRow) =>
        !!row.stripe_account_id && accountsWithStripeDashboard.has(row.stripe_account_id);

    if (!organizer) {
        return null;
    }

    const timezone = organizer.timezone || 'UTC';
    const eventParam = param('event');
    const selectedEventId = eventParam && events.some(event => String(event.id) === eventParam) ? eventParam : null;

    const columns: ReportColumn<PlatformFeesRow>[] = [
        {key: 'event_name', label: t`Event`, render: row => <EventLink eventId={row.event_id}>{row.event_name}</EventLink>},
        {
            key: 'payment_date',
            label: t`Payment date`,
            render: row => row.payment_date ? formatDateWithLocale(row.payment_date, 'shortDate', timezone) : <EmptyCell/>,
        },
        {
            key: 'order_reference',
            label: t`Order`,
            sortable: false,
            render: row => (
                <CellLink mono onClick={() => navigate(`/manage/event/${row.event_id}/orders#order-${row.order_id}`)}>
                    {row.order_reference}
                </CellLink>
            ),
        },
        {key: 'currency', label: t`Currency`},
        {key: 'amount_paid', label: t`Amount paid`, kind: 'money'},
        {key: 'fee_amount', label: t`Hi.Events fee`, kind: 'money'},
        {
            key: 'vat_rate',
            label: t`VAT rate`,
            kind: 'count',
            total: false,
            render: row => row.vat_rate ? `${(Number(row.vat_rate) * 100).toFixed(0)}%` : <EmptyCell/>,
        },
        {key: 'vat_amount', label: t`VAT on fee`, kind: 'money'},
        {key: 'total_fee', label: t`Total fee`, kind: 'money'},
        {
            key: 'payment_intent_id',
            label: t`Stripe payment ID`,
            sortable: false,
            render: row => row.payment_intent_id
                ? (
                    <CellCopy value={row.payment_intent_id}>
                        {canOpenInStripe(row) ? (
                            <CellLink
                                mono
                                title={t`Open this payment in Stripe`}
                                onClick={() => window.open(stripePaymentUrl(row), '_blank', 'noopener,noreferrer')}
                            >
                                {row.payment_intent_id}
                            </CellLink>
                        ) : <CellText mono>{row.payment_intent_id}</CellText>}
                    </CellCopy>
                )
                : <EmptyCell/>,
        },
    ];

    return (
        <OrganizerReport<PlatformFeesRow>
            description={description}
            organizer={organizer}
            eventId={selectedEventId ? Number(selectedEventId) : null}
            columns={columns}
            rowCurrency={row => row.currency}
            emptyMessage={t`No platform fees were charged in this period.`}
            summary={sum => [
                {label: t`Amount paid`, value: sum('amount_paid'), kind: 'money'},
                {label: t`Hi.Events fee`, value: sum('fee_amount'), kind: 'money'},
                {label: t`VAT on fee`, value: sum('vat_amount'), kind: 'money'},
                {label: t`Total fee`, value: sum('total_fee'), kind: 'money'},
            ]}
            extraFilters={events.length > 1 && (
                <Select
                    className={classes.eventSelect}
                    aria-label={t`Event`}
                    placeholder={t`All events`}
                    data={events.map(event => ({value: String(event.id), label: event.title || ''}))}
                    value={selectedEventId}
                    onChange={value => setFilter('event', value)}
                    clearable
                    searchable
                />
            )}
            notice={(
                <p className={classes.note}>
                    <IconInfoCircle size={16} stroke={1.5} aria-hidden/>
                    {t`These figures are a guide. Your Stripe dashboard has the complete payment history, and a tax adviser can confirm what to use in your accounts.`}
                </p>
            )}
        />
    );
};

export default PlatformFeesReport;
