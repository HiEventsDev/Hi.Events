import {t} from "@lingui/macro";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useNavigate, useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {Button} from "@mantine/core";
import {IconDownload} from "@tabler/icons-react";
import classes from './OrderSummaryAndTickets.module.scss';
import {LoadingMask} from "../../../common/LoadingMask";
import {Ticket} from "../../../../types.ts";
import {Card} from "../../../common/Card";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {prettyDate} from "../../../../utilites/dates.ts";

export const OrderSummaryAndTickets = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order, isFetched: orderIsFetched} = useGetOrderPublic(eventId, orderShortId);
    const {data: event, isFetched: eventIsFetched} = useGetEventPublic(eventId);
    const navigate = useNavigate();

    if (!orderIsFetched || !eventIsFetched || !order || !event) {
        return <LoadingMask/>;
    }

    if (window.location.search.includes('failed') || order?.status === 'PAYMENT_FAILED') {
        navigate(`/checkout/${eventId}/${orderShortId}/payment?payment_failed=true`);
        return;
    }

    if (order?.payment_status === 'AWAITING_PAYMENT') {
        return (
            <>
                {t`This order is processing. TODO - a nice image and poll the API`}
            </>
        );
    }

    if (order?.status === 'CANCELLED') {
        return (
            <>
                {t`This order has been cancelled.`}
            </>
        );
    }

    if (order?.status !== 'COMPLETED') {
        return (
            <>
                {t`This order is processing. TODO - a nice image and poll the API`}
            </>
        );
    }

    return (
        <>
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
                        {prettyDate(order?.created_at, event?.timezone)}
                    </div>
                </div>
            </Card>

            <h2 className={classes.subHeading}>{t`Guests`}</h2>
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

            <Button variant={'transparent'} mt={20} size={'sm'} leftSection={<IconDownload/>}
                    fullWidth>{t`Download Tickets PDF`}</Button>
        </>
    );
}
