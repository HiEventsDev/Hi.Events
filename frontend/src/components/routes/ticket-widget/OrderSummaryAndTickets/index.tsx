import {t} from "@lingui/macro";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useNavigate, useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import classes from './OrderSummaryAndTickets.module.scss';
import {LoadingMask} from "../../../common/LoadingMask";
import {Order, Ticket} from "../../../../types.ts";
import {Card} from "../../../common/Card";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {dateToBrowserTz} from "../../../../utilites/dates.ts";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {Button, Group} from "@mantine/core";
import {IconPrinter} from "@tabler/icons-react";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {CheckoutFooter} from "../../../layouts/Checkout/CheckoutFooter";
import {eventCheckoutPath} from "../../../../utilites/urlHelper.ts";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {OnlineEventDetails} from "../../../common/OnlineEventDetails";

const OrderStatus = ({order}: { order: Order }) => {
    let message = t`This order is processing.`; // Default message

    if (order?.payment_status === 'AWAITING_PAYMENT') {
        message = t`This order is processing.`;
    } else if (order?.status === 'CANCELLED') {
        message = t`This order has been cancelled.`;
    } else if (order?.status === 'COMPLETED') {
        message = t`This order is complete.`;
    }

    return <HomepageInfoMessage message={message}/>;
};

export const OrderSummaryAndTickets = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order, isFetched: orderIsFetched} = useGetOrderPublic(eventId, orderShortId);
    const {data: event, isFetched: eventIsFetched} = useGetEventPublic(eventId);
    const navigate = useNavigate();

    if (!orderIsFetched || !eventIsFetched || !order || !event) {
        return <LoadingMask/>;
    }

    if (window?.location.search.includes('failed') || order?.status === 'PAYMENT_FAILED') {
        navigate(eventCheckoutPath(eventId, orderShortId, 'payment') + '?payment_failed=true');
        return;
    }
    if (order?.status !== 'COMPLETED') {
        return <OrderStatus order={order}/>;
    }

    return (
        <>
            <CheckoutContent hasFooter={true}>
                <h1 className={classes.heading}>{t`Order Details`}</h1>

                <Card className={classes.orderDetails}>
                    <div className={classes.orderDetail}>
                        <div className={classes.orderDetailLabel}>{t`Name`}</div>
                        <div className={classes.orderDetailContent}>{order?.first_name} {order?.last_name}</div>
                    </div>
                    <div className={classes.orderDetail}>
                        <div className={classes.orderDetailLabel}>{t`Order Reference`}</div>
                        <div className={classes.orderDetailContent}>{order?.public_id}</div>
                    </div>
                    <div className={classes.orderDetail}>
                        <div className={classes.orderDetailLabel}>{t`Email`}</div>
                        <div className={classes.orderDetailContent}>{order?.email}</div>
                    </div>
                    <div className={classes.orderDetail}>
                        <div className={classes.orderDetailLabel}>{t`Order Date`}</div>
                        <div className={classes.orderDetailContent}>
                            {dateToBrowserTz(order?.created_at, event?.timezone)}
                        </div>
                    </div>
                </Card>

                {!!event?.settings?.post_checkout_message && (
                    <div style={{marginTop: '20px'}}>
                        <Card>
                            <div dangerouslySetInnerHTML={{__html: event?.settings?.post_checkout_message}}/>
                        </Card>
                    </div>
                )}

                {(event?.settings?.is_online_event && <OnlineEventDetails eventSettings={event.settings}/>)}

                <Group justify={'space-between'}>
                    <h2 className={classes.subHeading}>{t`Guests`}</h2>
                    <Button
                        size={'sm'}
                        variant={'transparent'}
                        leftSection={<IconPrinter size={16}/>}
                        onClick={() => window?.open(`/order/${eventId}/${orderShortId}/print`, '_blank')}
                    >
                        {t`Print All Tickets`}
                    </Button>
                </Group>

                {order.attendees?.map((attendee) => {
                    return (
                        <AttendeeTicket
                            key={attendee.id}
                            attendee={attendee}
                            ticket={attendee.ticket as Ticket}
                            event={event}
                        />
                    );
                })}

                {
                    /**
                     * (c) Hi.Events Ltd 2024
                     *
                     * PLEASE NOTE:
                     *
                     * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
                     *
                     * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENSE
                     *
                     * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
                     *
                     * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
                     */
                }
                <PoweredByFooter/>
            </CheckoutContent>
            <CheckoutFooter
                isOrderComplete={true}
                isLoading={false}
                event={event}
                order={order}
            />
        </>
    );
}

export default OrderSummaryAndTickets;