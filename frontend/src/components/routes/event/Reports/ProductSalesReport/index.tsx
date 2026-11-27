import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {ReportColumn} from "../../../../common/ReportView";
import {EventReport} from "../EventReport.tsx";

interface ProductSalesRow {
    product_id: number;
    product_title: string;
    number_sold: number;
    total_gross: string;
    total_tax: string;
    total_service_fees: string;
}

const ProductSalesReport = ({description}: { description: string }) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);

    if (!event) {
        return null;
    }

    const columns: ReportColumn<ProductSalesRow>[] = [
        {key: 'product_title', label: t`Product`},
        {key: 'number_sold', label: t`Units sold`, kind: 'count'},
        {key: 'total_gross', label: t`Gross sales`, kind: 'money'},
        {key: 'total_tax', label: t`Tax`, kind: 'money'},
        {key: 'total_service_fees', label: t`Service fees`, kind: 'money'},
    ];

    return (
        <EventReport<ProductSalesRow>
            description={description}
            event={event}
            fileName="product_sales_report.csv"
            columns={columns}
            emptyMessage={t`Add tickets or products to this event to see their sales here.`}
            summary={sum => [
                {label: t`Units sold`, value: sum('number_sold'), kind: 'count'},
                {label: t`Gross sales`, value: sum('total_gross'), kind: 'money'},
                {label: t`Tax`, value: sum('total_tax'), kind: 'money'},
                {label: t`Service fees`, value: sum('total_service_fees'), kind: 'money'},
            ]}
        />
    );
};

export default ProductSalesReport;
