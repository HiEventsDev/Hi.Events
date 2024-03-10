import {Outlet, useNavigate, useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import classes from './Checkout.module.scss';
import {OrderSummary} from "../../common/OrderSummary";
import {useGetOrderPublic} from "../../../queries/useGetOrderPublic.ts";
import {LoadingMask} from "../../common/LoadingMask";
import {t} from "@lingui/macro";
import {Countdown} from "../../common/Countdown";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {Event, Order} from "../../../types.ts";

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
    const coverImage = event?.images?.find((image) => image.type === 'EVENT_COVER');

    return (
        <>
            <div className={classes.container}>
                <div className={classes.mainContent}>
                    <header className={classes.header}>
                        <h2>{event?.title}</h2>
                        {(order && event) ? <SubTitle order={order} event={event}/> : <span>...</span>}
                    </header>
                    <main className={classes.main}>
                        <div className={classes.innerContainer}>
                            <Outlet/>
                        </div>
                        <LoadingMask/>
                    </main>
                </div>

                <aside className={classes.sidebar}>
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
            </div>
        </>
    );
}

export default Checkout;