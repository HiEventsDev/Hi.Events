import classes from "./StatBoxes.module.scss";
import {IconCash, IconCreditCardRefund, IconEye, IconReceipt, IconShoppingCart, IconUsers} from "@tabler/icons-react";
import {Card} from "../Card";
import {useGetEventStats} from "../../../queries/useGetEventStats.ts";
import {useParams} from "react-router-dom";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";

export const StatBoxes = () => {
    const {eventId} = useParams();
    const eventStatsQuery = useGetEventStats(eventId);
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery?.data;
    const {data: eventStats} = eventStatsQuery;

    const data = [
        {
            number: formatNumber(eventStats?.total_products_sold as number),
            description: t`Products sold`,
            icon: <IconShoppingCart size={18}/>
        },
        {
            number: formatNumber(eventStats?.total_attendees_registered as number),
            description: t`Attendees`,
            icon: <IconUsers size={18}/>
        },
        {
            number: formatCurrency(eventStats?.total_refunded as number || 0, event?.currency),
            description: t`Refunded`,
            icon: <IconCreditCardRefund size={18}/>
        },
        {
            number: formatCurrency(eventStats?.total_gross_sales || 0, event?.currency),
            description: t`Gross sales`,
            icon: <IconCash size={18}/>
        },
        {
            number: formatNumber(eventStats?.total_views as number),
            description: t`Page views`,
            icon: <IconEye size={18}/>
        },
        {
            number: formatNumber(eventStats?.total_orders as number),
            description: t`Orders Created`,
            icon: <IconReceipt size={18}/>
        }
    ];

    const stats = data.map((stat) => {
        return (
            <Card className={classes.statistic} key={stat.description}>
                <div className={classes.leftPanel}>
                    <div className={classes.number}>{stat.number}</div>
                    <div className={classes.description}>{stat.description}</div>
                </div>
                <div className={classes.rightPanel}>
                    <div className={classes.icon}>
                        {stat.icon}
                    </div>
                </div>
            </Card>
        )
    })

    return (
        <div className={classes.statistics}>
            {stats}
        </div>
    );
};

