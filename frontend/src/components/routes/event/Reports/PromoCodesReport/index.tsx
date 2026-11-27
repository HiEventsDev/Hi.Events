import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {PromoCodeDiscountType} from "../../../../../types.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";
import {formatNumber} from "../../../../../utilites/helpers.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {EmptyCell} from "../../../../common/TanStackTable/cells.tsx";
import {EventReport} from "../EventReport.tsx";

interface PromoCodesRow {
    promo_code: string;
    times_used: number;
    unique_customers: number;
    configured_discount: number;
    discount_type: string;
    total_gross_sales: string;
    total_before_discounts: string;
    total_discount_amount: string;
    first_used_at: string | null;
    last_used_at: string | null;
    max_allowed_usages: number | null;
    remaining_uses: number | null;
    status: string;
}

const PromoCodesReport = ({description}: { description: string }) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);

    if (!event) {
        return null;
    }

    const usedOn = (date: string | null) => date
        ? formatDateWithLocale(date, 'shortDate', event.timezone)
        : <EmptyCell/>;
    const limit = (value: number | null) => value === null ? t`Unlimited` : formatNumber(Number(value));
    const discount = (row: PromoCodesRow) => row.discount_type === PromoCodeDiscountType.Percentage
        ? `${Number(row.configured_discount)}%`
        : formatCurrency(row.configured_discount, event.currency);

    const columns: ReportColumn<PromoCodesRow>[] = [
        {key: 'promo_code', label: t`Promo code`},
        {key: 'status', label: t`Status`},
        {key: 'times_used', label: t`Times used`, kind: 'count'},
        {key: 'unique_customers', label: t`Unique customers`, kind: 'count', total: false},
        {key: 'configured_discount', label: t`Discount`, kind: 'count', total: false, render: discount, csv: discount},
        {key: 'total_before_discounts', label: t`Sales before discounts`, kind: 'money'},
        {key: 'total_discount_amount', label: t`Discounts given`, kind: 'money'},
        {key: 'total_gross_sales', label: t`Gross sales`, kind: 'money'},
        {key: 'first_used_at', label: t`First used`, render: row => usedOn(row.first_used_at)},
        {key: 'last_used_at', label: t`Last used`, render: row => usedOn(row.last_used_at)},
        {key: 'max_allowed_usages', label: t`Usage limit`, kind: 'count', total: false, render: row => limit(row.max_allowed_usages)},
        {key: 'remaining_uses', label: t`Uses left`, kind: 'count', total: false, render: row => limit(row.remaining_uses)},
    ];

    return (
        <EventReport<PromoCodesRow>
            description={description}
            event={event}
            fileName="promo_codes_report.csv"
            columns={columns}
            emptyMessage={t`Create a promo code to see how it performs here.`}
            summary={sum => [
                {label: t`Times used`, value: sum('times_used'), kind: 'count'},
                {label: t`Discounts given`, value: sum('total_discount_amount'), kind: 'money'},
                {label: t`Gross sales`, value: sum('total_gross_sales'), kind: 'money'},
            ]}
        />
    );
};

export default PromoCodesReport;
