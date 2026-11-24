import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {IconArmchair, IconCalendarEvent, IconChartBar, IconDiscount, IconTicket} from "@tabler/icons-react";
import {EventType, ReportTypes} from "../../../../types.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {ReportDefinition, ReportWorkspace} from "../../../common/ReportWorkspace";
import {RequirePermission} from "../../../common/WriteAccess";
import DailySalesReport from "./DailySalesReport";
import ProductSalesReport from "./ProductSalesReport";
import PromoCodesReport from "./PromoCodesReport";
import OccurrenceSummaryReport from "./OccurrenceSummaryReport";
import SeatingSalesReport from "../../../../ee/seating/components/routes/event/Reports/SeatingSalesReport";

const Reports = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {isLoaded, can} = useAccess();

    if (isLoaded && !can('VIEW_FINANCIALS')) {
        return (
            <PageBody>
                <PageTitle>{t`Reports`}</PageTitle>
                <RequirePermission permission="VIEW_FINANCIALS" imageHref="/blank-slate/reports.svg">{null}</RequirePermission>
            </PageBody>
        );
    }

    const reports: ReportDefinition[] = [
        {
            id: ReportTypes.DailySales,
            label: t`Daily sales`,
            description: t`Sales, tax, fees and refunds from completed orders, day by day. Days are counted in UTC.`,
            icon: IconChartBar,
            component: DailySalesReport,
        },
        {
            id: ReportTypes.ProductSales,
            label: t`Product sales`,
            description: t`Units sold and revenue from completed orders for each ticket and product.`,
            icon: IconTicket,
            component: ProductSalesReport,
        },
        {
            id: ReportTypes.PromoCodes,
            label: t`Promo codes`,
            description: t`How often each promo code was used on completed orders, and the discount it gave.`,
            icon: IconDiscount,
            component: PromoCodesReport,
        },
        ...(event?.type === EventType.RECURRING ? [{
            id: ReportTypes.OccurrenceSummary,
            label: t`Occurrences`,
            description: t`Sales, attendance and check-ins for every occurrence of this event.`,
            icon: IconCalendarEvent,
            component: OccurrenceSummaryReport,
        }] : []),
        ...(event?.has_seat_map ? [{
            id: ReportTypes.SeatingSales,
            label: t`Seating`,
            description: t`Capacity, sold, held and free seats for each price band and area, with revenue per band.`,
            icon: IconArmchair,
            component: SeatingSalesReport,
        }] : []),
    ];

    return (
        <ReportWorkspace
            basePath={`/manage/event/${eventId}`}
            reports={reports}
            isReady={!!event && isLoaded}
        />
    );
};

export default Reports;
