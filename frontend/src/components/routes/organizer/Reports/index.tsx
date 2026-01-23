import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {IconChartBar, IconChevronRight, IconReceipt, IconReceiptTax, IconReportMoney, IconUserCheck} from "@tabler/icons-react";
import classes from './Reports.module.scss';
import {Card} from "../../../common/Card";
import {Avatar, UnstyledButton} from "@mantine/core";
import {Link, useParams} from "react-router";
import {OrganizerReportTypes} from "../../../../types.ts";

const OrganizerReports = () => {
    const {organizerId} = useParams();

    const reports = [
        {
            id: OrganizerReportTypes.RevenueSummary,
            title: t`Revenue Summary`,
            description: t`Daily revenue, taxes, fees, and refunds across all events`,
            icon: <Avatar size={40} color={'#7C63E6'}><IconReportMoney/></Avatar>
        },
        {
            id: OrganizerReportTypes.EventsPerformance,
            title: t`Events Performance`,
            description: t`Sales, orders, and performance metrics for all events`,
            icon: <Avatar size={40} color={'#4B7BE5'}><IconChartBar/></Avatar>
        },
        {
            id: OrganizerReportTypes.TaxSummary,
            title: t`Tax Summary`,
            description: t`Tax collected grouped by tax type and event`,
            icon: <Avatar size={40} color={'#49A6B7'}><IconReceiptTax/></Avatar>
        },
        {
            id: OrganizerReportTypes.CheckInSummary,
            title: t`Check-in Summary`,
            description: t`Attendance and check-in rates across all events`,
            icon: <Avatar size={40} color={'#5FB98B'}><IconUserCheck/></Avatar>
        },
        {
            id: OrganizerReportTypes.PlatformFees,
            title: t`Platform Fees`,
            description: t`Hi.Events platform fees and VAT breakdown by transaction`,
            icon: <Avatar size={40} color={'#E67C63'}><IconReceipt/></Avatar>
        }
    ];

    return (
        <PageBody>
            <PageTitle
                subheading={t`View and download reports across all your events. Only completed orders are included.`}>
                {t`Reports`}
            </PageTitle>

            {reports.map((report) => (
                <UnstyledButton component={Link} key={report.id} to={`/manage/organizer/${organizerId}/report/${report.id}`}>
                    <Card className={classes.reportType}>
                        <div className={classes.icon}>
                            {report.icon}
                        </div>
                        <div className={classes.content}>
                            <h3>{report.title}</h3>
                            <p>{report.description}</p>
                        </div>
                        <div className={classes.rightCaret}>
                            <IconChevronRight/>
                        </div>
                    </Card>
                </UnstyledButton>
            ))}
        </PageBody>
    )
}

export default OrganizerReports;
