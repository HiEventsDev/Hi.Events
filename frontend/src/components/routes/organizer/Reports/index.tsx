import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {IconCalendarEvent, IconChartBar, IconReceipt, IconReceiptTax, IconUserCheck} from "@tabler/icons-react";
import {OrganizerReportTypes} from "../../../../types.ts";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {ReportDefinition, ReportWorkspace} from "../../../common/ReportWorkspace";
import RevenueSummaryReport from "./RevenueSummaryReport";
import EventsPerformanceReport from "./EventsPerformanceReport";
import TaxSummaryReport from "./TaxSummaryReport";
import CheckInSummaryReport from "./CheckInSummaryReport";
import PlatformFeesReport from "./PlatformFeesReport";

const OrganizerReports = () => {
    const {organizerId} = useParams();
    const {isLoaded, can} = useAccess();
    const {data: account} = useGetAccount();
    const canViewFinancials = can('VIEW_FINANCIALS');

    const reports: ReportDefinition[] = [
        ...(canViewFinancials ? [
            {
                id: OrganizerReportTypes.RevenueSummary,
                label: t`Revenue`,
                description: t`Sales, refunds, tax and fees from completed orders across all your events, day by day. Days are counted in UTC.`,
                icon: IconChartBar,
                component: RevenueSummaryReport,
            },
            {
                id: OrganizerReportTypes.EventsPerformance,
                label: t`Events`,
                description: t`Sales, orders and page views for every event, across all time.`,
                icon: IconCalendarEvent,
                component: EventsPerformanceReport,
            },
            {
                id: OrganizerReportTypes.TaxSummary,
                label: t`Tax`,
                description: t`Tax collected on completed orders, by tax and event.`,
                icon: IconReceiptTax,
                component: TaxSummaryReport,
            },
        ] : []),
        {
            id: OrganizerReportTypes.CheckInSummary,
            label: t`Check-ins`,
            description: t`Attendees and check-ins for every event, across all time.`,
            icon: IconUserCheck,
                component: CheckInSummaryReport,
        },
        ...(canViewFinancials && account?.is_saas_mode_enabled ? [{
            id: OrganizerReportTypes.PlatformFees,
            label: t`Platform fees`,
            description: t`Hi.Events platform fees and the VAT charged on them, payment by payment.`,
            icon: IconReceipt,
                component: PlatformFeesReport,
        }] : []),
    ];

    return (
        <ReportWorkspace
            basePath={`/manage/organizer/${organizerId}`}
            reports={reports}
            isReady={isLoaded && !!account}
        />
    );
};

export default OrganizerReports;
