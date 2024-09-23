import {Outlet, useNavigate, useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import classes from './Checkout.module.scss';
import {useGetOrderPublic} from "../../../queries/useGetOrderPublic.ts";
import {t} from "@lingui/macro";
import {Countdown} from "../../common/Countdown";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {Event, Order} from "../../../types.ts";
import {CheckoutSidebar} from "./CheckoutSidebar";

const SubTitle = ({order, event}: { order: Order, event: Event }) => {
    const navigate = useNavigate();
    const orderStatuses: any = {
        'COMPLETED': t`Order Completed`,
        'CANCELLED': t`Order Cancelled`,
        'PAYMENT_FAILED': t`Payment Failed`,
        'AWAITING_PAYMENT': t`Awaiting Payment`
    };

    if (order?.status === 'RESERVED') {
        return (
            <Countdown
                className={classes.countdown}
                targetDate={order.reserved_until}
                onExpiry={() => {
                    showSuccess(t`Sorry, your order has expired. Please start a new order.`);
                    navigate(`/event/${event.id}/${event.slug}`);
                }}
            />
        )
    }

    return <span className={classes.subTitle}>{orderStatuses[order?.status] || <></>}</span>;
}

const Checkout = () => {
    const {eventId, orderShortId} = useParams();
    const {
        data: order,
    } = useGetOrderPublic(eventId, orderShortId);
    const {data: event} = useGetEventPublic(eventId);

    return (
        <>
            <div className={classes.container}>
                <div className={classes.mainContent}>
                    <header className={classes.header}>
                        <h1>
                            {event?.title}
                        </h1>
                        {(order && event) ? <SubTitle order={order} event={event}/> : <span>...</span>}
                    </header>
                    <Outlet/>
                </div>

                {(order && event) && <CheckoutSidebar className={classes.sidebar} event={event} order={order}/>}
            </div>
        </>
    );
}

export default Checkout;
