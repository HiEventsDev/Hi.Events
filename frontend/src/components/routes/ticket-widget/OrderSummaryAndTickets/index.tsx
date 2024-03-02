import {t} from "@lingui/macro";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {useNavigate, useParams} from "react-router-dom";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import classes from './OrderSummaryAndTickets.module.scss';
import {LoadingMask} from "../../../common/LoadingMask";
import {Ticket} from "../../../../types.ts";
import {Card} from "../../../common/Card";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {prettyDate} from "../../../../utilites/dates.ts";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {Button, Group} from "@mantine/core";
import {IconPrinter} from "@tabler/icons-react";

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
        // The user should never see this, but just in case
        return (
            <>
                {t`This order is processing.`}
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
        // A final catch-all for any other status
        return (
            <>
                {t`This order is processing.`}
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

            {!!event?.settings?.post_checkout_message && (
                <div style={{marginTop: '20px'}}>
                    <Card>
                        <div dangerouslySetInnerHTML={{__html: event?.settings?.post_checkout_message}}/>
                    </Card>
                </div>
            )}

            <Group justify={'space-between'}>
                <h2 className={classes.subHeading}>{t`Guests`}</h2>
                <Button
                    size={'sm'}
                    variant={'transparent'}
                    leftSection={<IconPrinter size={16}/>}
                    onClick={() => window.open(`/order/${eventId}/${orderShortId}/print`, '_blank')}
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

            <PoweredByFooter/>
        </>
    );
}
