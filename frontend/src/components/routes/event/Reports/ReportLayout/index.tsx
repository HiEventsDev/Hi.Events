import {Link, useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {Button} from "@mantine/core";
import {IconChevronLeft} from "@tabler/icons-react";
import ProductSalesReport from "../ProductSalesReport";
import {ReportTypes} from "../../../../../types.ts";
import {DailySalesReport} from "../DailySalesReport";
import PromoCodesReport from "../PromoCodesReport";
import OccurrenceSummaryReport from "../OccurrenceSummaryReport";
import SeatingSalesReport from "../../../../../ee/seating/components/routes/event/Reports/SeatingSalesReport";
import {RequirePermission} from "../../../../common/WriteAccess";

const renderReport = (reportType: string) => {
    switch (reportType) {
        case ReportTypes.ProductSales:
            return <ProductSalesReport/>;
        case ReportTypes.DailySales:
            return <DailySalesReport/>;
        case ReportTypes.PromoCodes:
            return <PromoCodesReport/>;
        case ReportTypes.OccurrenceSummary:
            return <OccurrenceSummaryReport/>;
        case ReportTypes.SeatingSales:
            return <SeatingSalesReport/>;
        default:
            return <div>Report not found</div>;
    }
};

const ReportLayout = () => {
    const {eventId, reportType} = useParams();

    return (
        <PageBody>
            <Button mb={20}
                    leftSection={<IconChevronLeft/>}
                    variant={'transparent'}
                    component={Link}
                    to={`/manage/event/${eventId}/reports`}
                    pl={0}
            >
                Back to Reports
            </Button>
            <RequirePermission permission="VIEW_FINANCIALS" imageHref="/blank-slate/reports.svg">
                <div>
                    {renderReport(reportType as string)}
                </div>
            </RequirePermission>
        </PageBody>
    );
}

export default ReportLayout;
