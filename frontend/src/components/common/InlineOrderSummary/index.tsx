import {useState} from "react";
import {Collapse} from "@mantine/core";
import {IconCalendarEvent, IconChevronDown, IconShieldCheck, IconTag} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classNames from "classnames";
import {Event, Order} from "../../../types.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {prettyDate} from "../../../utilites/dates.ts";
import classes from './InlineOrderSummary.module.scss';

interface InlineOrderSummaryProps {
    event: Event;
    order: Order;
    defaultExpanded?: boolean;
    showBuyerProtection?: boolean;
}

export const InlineOrderSummary = ({
    event,
    order,
    defaultExpanded = true,
    showBuyerProtection = true,
}: InlineOrderSummaryProps) => {
    const [expanded, setExpanded] = useState(defaultExpanded);

    const totalAmount = order.total_refunded
        ? order.total_gross - order.total_refunded
        : order.total_gross;

    const coverImage = event?.images?.find((image) => image.type === 'EVENT_COVER');
    const location = event?.settings?.location_details?.city ||
        event?.settings?.location_details?.venue_name ||
        null;

    const totalFee = order.taxes_and_fees_rollup?.fees?.reduce((sum, fee) => sum + fee.value, 0) || 0;
    const totalTax = order.taxes_and_fees_rollup?.taxes?.reduce((sum, tax) => sum + tax.value, 0) || 0;
    const totalDiscount = order.order_items?.reduce((sum, item) => {
        if (item.total_before_discount && item.total_before_additions) {
            return sum + (item.total_before_discount - item.total_before_additions);
        }
        return sum;
    }, 0) || 0;

    return (
        <div className={classes.inlineOrderSummary}>
            <div
                className={classes.header}
                onClick={() => setExpanded(!expanded)}
                role="button"
                aria-expanded={expanded}
                tabIndex={0}
                onKeyDown={(e) => e.key === 'Enter' && setExpanded(!expanded)}
            >
                <span className={classes.headerTitle}>{t`Order Summary`}</span>
                <div className={classes.headerRight}>
                    <span className={classes.headerTotal}>
                        {formatCurrency(totalAmount, order.currency)} {order.currency}
                    </span>
                    <IconChevronDown
                        size={20}
                        className={classNames(classes.chevron, {
                            [classes.chevronRotated]: expanded
                        })}
                    />
                </div>
            </div>

            <Collapse in={expanded}>
                <div className={classes.content}>
                    <div className={classes.eventInfo}>
                        <div className={classes.eventImage}>
                            {coverImage ? (
                                <img src={coverImage.url} alt={event.title}/>
                            ) : (
                                <div className={classes.eventImagePlaceholder}>
                                    <IconCalendarEvent size={24}/>
                                </div>
                            )}
                        </div>
                        <div className={classes.eventDetails}>
                            <div className={classes.eventTitle}>{event.title}</div>
                            <div className={classes.eventMeta}>
                                {prettyDate(event.start_date, event.timezone, false)}
                            </div>
                            {location && (
                                <div className={classes.eventMeta}>{location}</div>
                            )}
                        </div>
                    </div>

                    <div className={classes.divider}/>

                    <div className={classes.lineItems}>
                        {order.order_items?.map((item) => (
                            <div key={item.id} className={classes.lineItem}>
                                <div className={classes.lineItemLeft}>
                                    <span className={classes.lineItemName}>{item.item_name}</span>
                                    {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
                                    <span className={classes.lineItemQuantity}>× {item.quantity}</span>
                                </div>
                                <div className={classes.lineItemPriceWrapper}>
                                    {!!item.price_before_discount && (
                                        <span className={classes.lineItemPriceOriginal}>
                                            {formatCurrency(item.price_before_discount * item.quantity, order.currency)}
                                        </span>
                                    )}
                                    <span className={classes.lineItemPrice}>
                                        {formatCurrency(item.price * item.quantity, order.currency)}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    {order.promo_code && totalDiscount > 0 && (
                        <div className={classes.promoCode}>
                            <div className={classes.promoCodeLeft}>
                                <IconTag size={16}/>
                                <span>{order.promo_code}</span>
                            </div>
                            <span className={classes.promoCodeDiscount}>
                                -{formatCurrency(totalDiscount, order.currency)}
                            </span>
                        </div>
                    )}

                    <div className={classes.divider}/>

                    <div className={classes.totals}>
                        <div className={classes.totalsRow}>
                            <span className={classes.totalsLabel}>{t`Subtotal`}</span>
                            <span className={classes.totalsValue}>
                                {formatCurrency(order.total_before_additions, order.currency)}
                            </span>
                        </div>

                        <div className={classes.totalsRow}>
                            <span className={classes.totalsLabel}>{t`Fees`}</span>
                            <span className={classNames(classes.totalsValue, {
                                [classes.totalsValueFree]: totalFee === 0
                            })}>
                                {formatCurrency(totalFee, order.currency)}
                            </span>
                        </div>

                        {totalTax > 0 && (
                            <div className={classes.totalsRow}>
                                <span className={classes.totalsLabel}>{t`Taxes`}</span>
                                <span className={classes.totalsValue}>
                                    {formatCurrency(totalTax, order.currency)}
                                </span>
                            </div>
                        )}

                        <div className={classNames(classes.totalsRow, classes.totalsRowFinal)}>
                            <span className={classes.totalsFinalLabel}>{t`Total`}</span>
                            <span className={classes.totalsFinalValue}>
                                {formatCurrency(totalAmount, order.currency)}
                                <span className={classes.totalsCurrency}>{order.currency}</span>
                            </span>
                        </div>
                    </div>

                    {showBuyerProtection && order.is_payment_required && (
                        <div className={classes.buyerProtection}>
                            <IconShieldCheck size={20} className={classes.buyerProtectionIcon}/>
                            <div className={classes.buyerProtectionText}>
                                <span className={classes.buyerProtectionTitle}>{t`Secure Checkout`}</span>
                                <span className={classes.buyerProtectionSubtitle}>
                                    {t`Your payment is protected with bank-level encryption`}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </Collapse>
        </div>
    );
};
