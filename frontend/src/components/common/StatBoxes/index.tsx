import classes from "./StatBoxes.module.scss";
import {IconCash, IconCreditCardRefund, IconEye, IconReceipt, IconShoppingCart, IconUsers} from "@tabler/icons-react";
import {Card} from "../Card";
import {useGetEventStats} from "../../../queries/useGetEventStats.ts";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import React from "react";

interface StatBoxProps {
    number: string;
    description: string;
    icon: React.ReactNode;
    backgroundColor: string;
}

export const StatBox = ({number, description, icon, backgroundColor}: StatBoxProps) => {
    return (
        <Card className={classes.statistic}>
            <div className={classes.leftPanel}>
                <div className={classes.number}>{number}</div>
                <div className={classes.description}>{description}</div>
            </div>
            <div className={classes.rightPanel}>
                <div className={classes.icon} style={{backgroundColor}}>
                    {icon}
                </div>
            </div>
        </Card>
    );
};

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
            icon: <IconShoppingCart size={18}/>,
            backgroundColor: '#4B7BE5' // Deep blue
        },
        {
            number: formatNumber(eventStats?.total_attendees_registered as number),
            description: t`Attendees`,
            icon: <IconUsers size={18}/>,
            backgroundColor: '#E6677E' // Rose pink
        },
        {
            number: formatCurrency(eventStats?.total_refunded as number || 0, event?.currency),
            description: t`Refunded`,
            icon: <IconCreditCardRefund size={18}/>,
            backgroundColor: '#49A6B7' // Teal
        },
        {
            number: formatCurrency(eventStats?.total_gross_sales || 0, event?.currency),
            description: t`Gross sales`,
            icon: <IconCash size={18}/>,
            backgroundColor: '#7C63E6' // Purple
        },
        {
            number: formatNumber(eventStats?.total_views as number),
            description: t`Page views`,
            icon: <IconEye size={18}/>,
            backgroundColor: '#63B3A1' // Sage green
        },
        {
            number: formatNumber(eventStats?.total_orders as number),
            description: t`Completed orders`,
            icon: <IconReceipt size={18}/>,
            backgroundColor: '#E67D49' // Coral orange
        }
    ];

    return (
        <div className={classes.statistics}>
            {data.map((stat) => (
                <StatBox
                    key={stat.description}
                    number={stat.number}
                    description={stat.description}
                    icon={stat.icon}
                    backgroundColor={stat.backgroundColor}
                />
            ))}
        </div>
    );
};
