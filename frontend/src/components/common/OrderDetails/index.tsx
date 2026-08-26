import {Anchor, Tooltip} from "@mantine/core";
import {formatDateWithLocale, prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {OrderStatusBadge} from "../OrderStatusBadge";
import {Currency} from "../Currency";
import {Card, CardVariant} from "../Card";
import {Event, Order} from "../../../types.ts";
import classes from "./OrderDetails.module.scss";
import {t} from "@lingui/macro";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {capitalize} from "../../../utilites/stringHelper.ts";

export const OrderDetails = ({order, event, cardVariant = 'lightGray'}: {
    order: Order,
    event: Event,
    cardVariant?: CardVariant,
}) => {
    const occurrenceItems = order.order_items?.filter(item => item.event_occurrence) ?? [];
    const uniqueOccurrences = Array.from(
        new Map(occurrenceItems.map(item => [item.event_occurrence!.id, item.event_occurrence!])).values()
    );

    return (
        <Card className={classes.orderDetails} variant={cardVariant}>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Name`}
                </div>
                <div className={classes.amount}>
                    {order.first_name} {order.last_name}
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Email`}
                </div>
                <div className={classes.value}>
                    <Anchor href={'mailto:' + order.email} target={'_blank'}>{order.email}</Anchor>
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Date`}
                </div>
                <div className={classes.amount}>
                    <Tooltip label={prettyDate(order.created_at, event.timezone)} position={'bottom'} withArrow>
                            <span>
                                {relativeDate(order.created_at)}
                            </span>
                    </Tooltip>
                </div>
            </div>
            {uniqueOccurrences.length > 0 && (
                <div className={classes.block}>
                    <div className={classes.title}>
                        {uniqueOccurrences.length === 1 ? t`Occurrence` : t`Occurrences`}
                    </div>
                    <div className={classes.amount}>
                        {uniqueOccurrences.map(occurrence => (
                            <div key={occurrence.id}>
                                {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                                {' '}
                                {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                                {occurrence.label && ` · ${occurrence.label}`}
                            </div>
                        ))}
                    </div>
                </div>
            )}
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Status`}
                </div>
                <div className={classes.amount}>
                    <OrderStatusBadge order={order} variant={'outline'}/>
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Total order amount`}
                </div>
                <div className={classes.amount}>
                    <Currency currency={order.currency} price={order.total_gross}/>
                </div>
            </div>
            <div className={classes.block}>
                <div className={classes.title}>
                    {t`Total refunded`}
                </div>
                <div className={classes.amount}>
                    <Currency currency={order.currency} price={order.total_refunded}/>
                </div>
            </div>
            {order.payment_provider && (
                <div className={classes.block}>
                    <div className={classes.title}>
                        {t`Payment provider`}
                    </div>
                    <div className={classes.amount}>
                        {capitalize(order.payment_provider)}
                    </div>
                </div>
            )}
            {order.promo_code && (
                <div className={classes.block}>
                    <div className={classes.title}>
                        {t`Promo code`}
                    </div>
                    <div className={classes.amount}>
                        {order.promo_code}
                    </div>
                </div>
            )}
            {order.address && (
                <div className={classes.block}>
                    <div className={classes.title}>
                        {t`Address`}
                    </div>
                    <div className={classes.amount}>
                        {formatAddress(order.address)}
                    </div>
                </div>
            )}
        </Card>
    );
}
