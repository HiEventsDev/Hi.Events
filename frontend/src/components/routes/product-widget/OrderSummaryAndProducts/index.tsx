import React from "react";
import {t} from "@lingui/macro";
import {NavLink, useNavigate, useParams} from "react-router";
import {Badge, Button, Group, SimpleGrid, Text} from "@mantine/core";
import {
    IconBuilding,
    IconCalendar,
    IconCalendarEvent,
    IconCash,
    IconClock,
    IconId,
    IconMail,
    IconMapPin,
    IconMenuOrder,
    IconPrinter,
    IconUser
} from "@tabler/icons-react";

import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {eventCheckoutPath} from "../../../../utilites/urlHelper.ts";
import {dateToBrowserTz} from "../../../../utilites/dates.ts";
import {formatAddress} from "../../../../utilites/addressUtilities.ts";

import {Card} from "../../../common/Card";
import {LoadingMask} from "../../../common/LoadingMask";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {AttendeeTicket} from "../../../common/AttendeeTicket";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {EventDateRange} from "../../../common/EventDateRange";
import {OnlineEventDetails} from "../../../common/OnlineEventDetails";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {CheckoutFooter} from "../../../layouts/Checkout/CheckoutFooter";

import {Event, Order, Product} from "../../../../types.ts";
import classes from './OrderSummaryAndProducts.module.scss';

const PaymentStatus = ({order}: { order: Order }) => {
    const paymentStatuses: Record<string, string> = {
        'NO_PAYMENT_REQUIRED': t`No Payment Required`,
        'AWAITING_PAYMENT': t`Awaiting Payment`,
        'PAYMENT_FAILED': t`Payment Failed`,
        'PAYMENT_RECEIVED': t`Payment Received`,
        'AWAITING_OFFLINE_PAYMENT': t`Awaiting Offline Payment`,
    };

    return order?.payment_status ? <span>{paymentStatuses[order.payment_status] || ''}</span> : null;
};

const RefundStatusType = ({order}: { order: Order }) => {
    const refundStatuses: Record<string, string> = {
        'REFUND_PENDING': t`Refund Pending`,
        'REFUND_FAILED': t`Refund Failed`,
        'REFUNDED': t`Refunded`,
        'PARTIALLY_REFUNDED': t`Partially Refunded`,
    };

    return order?.refund_status ? <span>{refundStatuses[order.refund_status] || ''}</span> : null;
};

const OrderStatusType = ({order}: { order: Order }) => {
    const statuses: Record<string, { label: string, color: string }> = {
        'COMPLETED': {label: t`Order Completed`, color: 'green'},
        'CANCELLED': {label: t`Order Cancelled`, color: 'red'},
        'PAYMENT_FAILED': {label: t`Payment Failed`, color: 'red'},
        'AWAITING_PAYMENT': {label: t`Awaiting Payment`, color: 'orange'},
        'AWAITING_OFFLINE_PAYMENT': {label: t`Awaiting Offline Payment`, color: 'orange'},
    };

    const status = statuses[order?.status];
    if (!status) return null;

    return (
        <Badge variant="outline" color={status.color}>
            {status.label}
        </Badge>
    );
};

const DetailItem = ({icon: Icon, label, value}: { icon: any, label: string, value: React.ReactNode }) => (
    <div className={classes.detailItem}>
        <Group gap="xs" wrap="nowrap">
            <Icon size={20} style={{color: 'var(--mantine-color-gray-6)', flexShrink: 0}}/>
            <div className={classes.detailContent}>
                <Text size="sm" c="dimmed" className={classes.label}>{label}</Text>
                <Text className={classes.value}>{value}</Text>
            </div>
        </Group>
    </div>
);

const WelcomeHeader = ({order, event}: { order: Order; event: Event }) => {
    const message = {
        'COMPLETED': t`You're going to ${event.title}! 🎉`,
        'CANCELLED': t`Your order has been cancelled`,
        'RESERVED': null,
        'AWAITING_OFFLINE_PAYMENT': t`Your order is awaiting payment 🏦`
    }[order.status];

    return message ? <div className={classes.welcomeHeader}>{message}</div> : null;
};

const OrderDetails = ({order, event}: { order: Order, event: Event }) => (
    <Card style={{marginBottom: '40px'}}>
        <SimpleGrid cols={{base: 1, sm: 2}} spacing="md">
            <DetailItem
                icon={IconUser}
                label={t`Name`}
                value={`${order.first_name} ${order.last_name}`}
            />
            <DetailItem
                icon={IconId}
                label={t`Order Reference`}
                value={order.public_id}
            />
            <DetailItem
                icon={IconMail}
                label={t`Email`}
                value={order.email}
            />
            <DetailItem
                icon={IconCalendar}
                label={t`Order Date`}
                value={dateToBrowserTz(order.created_at, event.timezone)}
            />
            {!!order.refund_status && (
                <DetailItem
                    icon={IconMenuOrder}
                    label={t`Refund Status`}
                    value={<RefundStatusType order={order}/>}
                />
            )}
            {(order.payment_status !== 'PAYMENT_RECEIVED' && order.payment_status !== 'NO_PAYMENT_REQUIRED') && (
                <DetailItem
                    icon={IconCash}
                    label={t`Payment Status`}
                    value={<PaymentStatus order={order}/>}
                />
            )}
            {order.address && (
                <DetailItem
                    icon={IconMapPin}
                    label={t`Billing Address`}
                    value={formatAddress(order.address)}
                />
            )}
        </SimpleGrid>
    </Card>
);

const EventDetails = ({event}: { event: Event }) => {
    const location = event.settings?.location_details ? formatAddress(event.settings.location_details) : null;
    const venueDetails = event.settings?.location_details?.venue_name
        ? `${event.settings.location_details.venue_name}${location ? `, ${location}` : ''}`
        : location;

    return (
        <Card>
            <SimpleGrid cols={{base: 1, sm: 2}} spacing="md">
                <DetailItem
                    icon={IconCalendarEvent}
                    label={t`Event Date`}
                    value={<EventDateRange event={event}/>}
                />
                {venueDetails && (
                    <DetailItem
                        icon={IconMapPin}
                        label={t`Location`}
                        value={(
                            <NavLink
                                to={event.settings?.maps_url || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(formatAddress(event?.settings?.location_details))}`}
                                target="_blank"
                            >
                                {venueDetails}
                            </NavLink>

                        )}
                    />
                )}
                <DetailItem
                    icon={IconClock}
                    label={t`Timezone`}
                    value={event.timezone}
                />
                <DetailItem
                    icon={IconBuilding}
                    label={t`Organizer`}
                    value={(
                        <>
                            {event.organizer?.email && (
                                <NavLink to={event.organizer?.email ? `mailto:${event.organizer.email}` : '#'}>
                                    {event.organizer?.name}
                                </NavLink>
                            )}
                            {!event.organizer?.email && event.organizer?.name}
                        </>
                    )}
                />
            </SimpleGrid>
        </Card>
    );
};

const OrderStatus = ({order}: { order: Order }) => {
    let message = t`This order is processing.`;

    if (order?.payment_status === 'AWAITING_PAYMENT') {
        message = t`This order is processing.`;
    } else if (order?.status === 'CANCELLED') {
        message = t`This order has been cancelled.`;
    } else if (order?.status === 'COMPLETED') {
        message = t`This order is complete.`;
    }

    return <HomepageInfoMessage message={message}/>;
};

const PostCheckoutMessage = ({ message }: { message: string }) => (
    <div style={{ marginTop: '20px', marginBottom: '40px' }}>
        <h1 className={classes.heading}>{t`Additional Information`}</h1>
        <Card>
            <div dangerouslySetInnerHTML={{ __html: message }} />
        </Card>
    </div>
);

const OfflinePaymentInstructions = ({ event }: { event: Event }) => (
    <div style={{ marginTop: '20px', marginBottom: '40px' }}>
        <h2>{t`Payment Instructions`}</h2>
        <Card>
            <div
                dangerouslySetInnerHTML={{
                    __html: event?.settings?.offline_payment_instructions || "",
                }}
            />
        </Card>
    </div>
);

export const OrderSummaryAndProducts = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order, isFetched: orderIsFetched} = useGetOrderPublic(eventId, orderShortId, ['event']);
    const event = order?.event;
    const navigate = useNavigate();

    if (!orderIsFetched || !order || !event) {
        return <LoadingMask/>;
    }

    if (window?.location.search.includes('failed') || order?.payment_status === 'PAYMENT_FAILED') {
        navigate(eventCheckoutPath(eventId, orderShortId, 'payment') + '?payment_failed=true');
        return;
    }

    if (order?.status !== 'COMPLETED' && order?.status !== 'CANCELLED' && order?.status !== 'AWAITING_OFFLINE_PAYMENT') {
        return <OrderStatus order={order}/>;
    }

    return (
        <>
            <CheckoutContent hasFooter={true}>
                <WelcomeHeader order={order} event={event}/>

                {order?.status === 'AWAITING_OFFLINE_PAYMENT' && <OfflinePaymentInstructions event={event}/>}

                <Group justify="space-between" align="center">
                    <h1 className={classes.heading}>{t`Order Details`}</h1>
                    <OrderStatusType order={order}/>
                </Group>

                <OrderDetails order={order} event={event}/>

                {event?.settings?.is_online_event && <OnlineEventDetails eventSettings={event.settings}/>}

                {!!event?.settings?.post_checkout_message && <PostCheckoutMessage message={event.settings.post_checkout_message}/>}

                <h1 className={classes.heading}>{t`Event Details`}</h1>
                <EventDetails event={event}/>

                {(order?.attendees && order.attendees.length > 0) && (
                    <Group justify="space-between" align="center">
                        <h1 className={classes.heading}>{t`Guests`}</h1>
                        <Button
                            size="sm"
                            variant="transparent"
                            leftSection={<IconPrinter size={16}/>}
                            onClick={() => window?.open(`/order/${eventId}/${orderShortId}/print`, '_blank')}
                        >
                            {t`Print All Tickets`}
                        </Button>
                    </Group>
                )}

                {order.attendees?.map((attendee) => (
                    <AttendeeTicket
                        key={attendee.id}
                        attendee={attendee}
                        product={attendee.product as Product}
                        event={event}
                    />
                ))}

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
};

export default OrderSummaryAndProducts;
