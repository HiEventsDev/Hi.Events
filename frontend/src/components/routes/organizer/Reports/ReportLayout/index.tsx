import {Link, useParams} from "react-router";
import {PageBody} from "../../../../common/PageBody";
import {Button} from "@mantine/core";
import {IconChevronLeft} from "@tabler/icons-react";
import {OrganizerReportTypes} from "../../../../../types.ts";
import RevenueSummaryReport from "../RevenueSummaryReport";
import EventsPerformanceReport from "../EventsPerformanceReport";
import TaxSummaryReport from "../TaxSummaryReport";
import CheckInSummaryReport from "../CheckInSummaryReport";
import PlatformFeesReport from "../PlatformFeesReport";
import {t} from "@lingui/macro";

const renderReport = (reportType: string) => {
    switch (reportType) {
        case OrganizerReportTypes.RevenueSummary:
            return <RevenueSummaryReport/>;
        case OrganizerReportTypes.EventsPerformance:
            return <EventsPerformanceReport/>;
        case OrganizerReportTypes.TaxSummary:
            return <TaxSummaryReport/>;
        case OrganizerReportTypes.CheckInSummary:
            return <CheckInSummaryReport/>;
        case OrganizerReportTypes.PlatformFees:
            return <PlatformFeesReport/>;
        default:
            return <div>{t`Report not found`}</div>;
    }
};

const OrganizerReportLayout = () => {
    const {organizerId, reportType} = useParams();

    return (
        <PageBody>
            <Button mb={20}
                    leftSection={<IconChevronLeft/>}
                    variant={'transparent'}
                    component={Link}
                    to={`/manage/organizer/${organizerId}/reports`}
                    pl={0}
            >
                {t`Back to Reports`}
            </Button>
            <div>
                {renderReport(reportType as string)}
            </div>
        </PageBody>
    );
}

export default OrganizerReportLayout;
