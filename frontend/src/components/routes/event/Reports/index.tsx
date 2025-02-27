import {PageTitle} from "../../../common/PageTitle";
import {t} from "@lingui/macro";
import {PageBody} from "../../../common/PageBody";
import {IconChartBar, IconChevronRight, IconReportMoney} from "@tabler/icons-react";
import classes from './Reports.module.scss';
import {Card} from "../../../common/Card";
import {Avatar, UnstyledButton} from "@mantine/core";
import {Link, useParams} from "react-router";
import {ReportTypes} from "../../../../types.ts";

const Reports = () => {
    const {eventId} = useParams();

    const reports = [
        {
            id: ReportTypes.ProductSales,
            title: t`Product Sales`,
            description: t`Product sales, revenue, and tax breakdown`,
            icon: <Avatar size={40} color={'#831781'}><IconReportMoney/></Avatar>
        },
        {
            id: ReportTypes.DailySales,
            title: t`Daily Sales Report`,
            description: t`Daily sales, tax, and fee breakdown`,
            icon: <Avatar size={40} color={'#00a3e0'}><IconChartBar/></Avatar>
        },
        {
            id: ReportTypes.PromoCodes,
            title: t`Promo Codes Report`,
            description: t`Promo code usage and discount breakdown`,
            icon: <Avatar size={40} color={'#634fc0'}><IconReportMoney/></Avatar>
        }
    ];

    return (
        <PageBody>
            <PageTitle
                subheading={t`View and download reports for your event. Please note, only completed orders are included in these reports.`}>
                {t`Reports`}
            </PageTitle>

            {reports.map((report) => (
                <UnstyledButton component={Link} key={report.id} to={`/manage/event/${eventId}/report/${report.id}`}>
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

export default Reports;
