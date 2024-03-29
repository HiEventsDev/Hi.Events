import {t} from "@lingui/macro";
import {OrderSummary} from "../../../common/OrderSummary";
import {LoadingMask} from "../../../common/LoadingMask";
import {Event, Order} from "../../../../types.ts";
import classes from './CheckoutSidebar.module.scss';
import classNames from "classnames";

interface SidebarProps {
    event: Event;
    order: Order;
    className?: string;
}

export const CheckoutSidebar = ({event, order, className = ''}: SidebarProps) => {
    const coverImage = event?.images?.find((image) => image.type === 'EVENT_COVER');

    return (
        <aside className={classNames(classes.sidebar, className)}>
            {coverImage && (
                <div className={classes.coverImage}>
                    <img src={coverImage?.url} alt={event?.title}/>
                </div>
            )}

            <div className={classes.checkoutSummary}>
                {(order && event) && (
                    <>
                        <h4>{t`Order Summary`}</h4>
                        <OrderSummary event={event} order={order}/>
                    </>
                )}
                <LoadingMask/>
            </div>
        </aside>
    );
}