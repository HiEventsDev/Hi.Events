import {Event, Order} from "../../../types.ts";
import classes from "./OrderSummary.module.scss";
import {Currency} from "../Currency";
import {t} from "@lingui/macro";

interface OrderSummaryProps {
    event: Event,
    order: Order,
    showFreeWhenZeroTotal?: boolean,
}

export const OrderSummary = ({event, order, showFreeWhenZeroTotal = true}: OrderSummaryProps) => {
    return (
        <div className={classes.summary}>
            <div className={classes.items}>
                {order?.order_items?.map(item => {
                    return (
                        <div key={item.id} className={classes.itemRow}>
                            {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
                            <div className={classes.itemName}>{item.quantity} x {item.item_name}</div>
                            <div className={classes.itemValue}>
                                {!!item.price_before_discount && (
                                    <div style={{color: '#888', marginRight: '5px', display: 'inline-block'}}>
                                        <Currency
                                            currency={event.currency}
                                            price={item.price_before_discount * item.quantity}
                                            strikeThrough
                                        />
                                    </div>
                                )}
                                <Currency
                                    currency={event.currency}
                                    price={item.price * item.quantity}
                                />
                            </div>
                        </div>
                    )
                })}
                <div className={classes.separator}/>
                <div className={classes.itemRow}>
                    <div className={classes.itemName}><b className={classes.total}>{t`Subtotal`}</b></div>
                    <div className={classes.itemValue}>
                        <Currency
                            currency={event.currency}
                            price={order.total_before_additions}
                            freeLabel={showFreeWhenZeroTotal ? t`Free` : null}
                        />
                    </div>
                </div>
                {order?.taxes_and_fees_rollup?.fees?.map(fee => {
                    return (
                        <div key={fee.name} className={classes.itemRow}>
                            <div className={classes.itemName}>{fee.name}</div>
                            <div className={classes.itemValue}>
                                <Currency
                                    currency={event.currency}
                                    price={fee.value}
                                />
                            </div>
                        </div>
                    )
                })}
                {order?.taxes_and_fees_rollup?.taxes?.map(tax => {
                    return (
                        <div key={tax.name} className={classes.itemRow}>
                            <div className={classes.itemName}>{tax.name}</div>
                            <div className={classes.itemValue}>
                                <Currency
                                    currency={event.currency}
                                    price={tax.value}
                                />
                            </div>
                        </div>
                    )
                })}
                {(order.total_refunded > 0 && (
                    <>
                        <div className={classes.separator}/>
                        <div className={classes.itemRow}>
                            <div className={classes.itemName}>{t`Refunded`}</div>
                            <div className={classes.itemValue}>
                                - <Currency
                                currency={event.currency}
                                price={order.total_refunded}
                            />
                            </div>
                        </div>
                    </>
                ))}
                <div className={classes.separator}/>
                <div className={classes.itemRow}>
                    <div className={classes.itemName}><b className={classes.total}>{t`Total`}</b></div>
                    <div className={classes.itemValue}>
                        <Currency
                            currency={event.currency}
                            price={
                                order.total_refunded ? order.total_gross - order.total_refunded : order.total_gross
                            }
                            freeLabel={showFreeWhenZeroTotal ? t`Free` : null}
                        />
                    </div>
                </div>
            </div>
        </div>
    )
}