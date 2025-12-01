import {t} from "@lingui/macro";
import {NavLink, useParams} from "react-router";
import {Badge, Button, Group, SimpleGrid, Text, TextInput} from "@mantine/core";
import {
    IconCalendar,
    IconCalendarEvent,
    IconExternalLink,
    IconMapPin,
    IconPrinter,
    IconTicket,
    IconAlertCircle,
    IconMail,
} from "@tabler/icons-react";
import {useForm} from "@mantine/form";
import {useState} from "react";

import {useGetOrdersByLookupToken} from "../../../queries/useGetOrdersByLookupToken.ts";
import {useSendTicketLookupEmail} from "../../../mutations/useSendTicketLookupEmail.ts";
import {dateToBrowserTz} from "../../../utilites/dates.ts";
import {formatAddress} from "../../../utilites/addressUtilities.ts";
import {showError} from "../../../utilites/notifications.tsx";

import {Card} from "../../common/Card";
import {LoadingMask} from "../../common/LoadingMask";
import {PoweredByFooter} from "../../common/PoweredByFooter";
import {EventDateRange} from "../../common/EventDateRange";
import {CheckoutContent} from "../../layouts/Checkout/CheckoutContent";

import {Event, Order} from "../../../types.ts";
import classes from './MyTickets.module.scss';

const OrderStatusBadge = () => (
    <Badge variant="light" color="green" size="sm">
        {t`Completed`}
    </Badge>
);

const OrderCard = ({order}: { order: Order }) => {
    const event = order.event as Event;
    const location = event?.settings?.location_details ? formatAddress(event.settings.location_details) : null;
    const ticketCount = order.attendees?.length || 0;
    const orderUrl = `/checkout/${event?.id}/${order.short_id}/summary`;
    const printUrl = `/order/${event?.id}/${order.short_id}/print`;

    return (
        <Card className={classes.orderCard}>
            <div className={classes.orderHeader}>
                <div className={classes.eventInfo}>
                    <h3 className={classes.eventTitle}>{event?.title}</h3>
                    <OrderStatusBadge/>
                </div>
                <Text size="xs" c="dimmed">
                    {t`Order`} #{order.public_id}
                </Text>
            </div>

            <SimpleGrid cols={{base: 1, sm: 2}} spacing="md" className={classes.orderDetails}>
                <div className={classes.detailItem}>
                    <Group gap="xs" wrap="nowrap">
                        <IconCalendarEvent size={18} style={{color: 'var(--mantine-color-gray-6)'}}/>
                        <div>
                            <Text size="xs" c="dimmed">{t`Event Date`}</Text>
                            <Text size="sm"><EventDateRange event={event}/></Text>
                        </div>
                    </Group>
                </div>

                {location && (
                    <div className={classes.detailItem}>
                        <Group gap="xs" wrap="nowrap">
                            <IconMapPin size={18} style={{color: 'var(--mantine-color-gray-6)'}}/>
                            <div>
                                <Text size="xs" c="dimmed">{t`Location`}</Text>
                                <Text size="sm" lineClamp={1}>{location}</Text>
                            </div>
                        </Group>
                    </div>
                )}

                <div className={classes.detailItem}>
                    <Group gap="xs" wrap="nowrap">
                        <IconTicket size={18} style={{color: 'var(--mantine-color-gray-6)'}}/>
                        <div>
                            <Text size="xs" c="dimmed">{t`Tickets`}</Text>
                            <Text size="sm">{ticketCount} {ticketCount === 1 ? t`ticket` : t`tickets`}</Text>
                        </div>
                    </Group>
                </div>

                <div className={classes.detailItem}>
                    <Group gap="xs" wrap="nowrap">
                        <IconCalendar size={18} style={{color: 'var(--mantine-color-gray-6)'}}/>
                        <div>
                            <Text size="xs" c="dimmed">{t`Purchased`}</Text>
                            <Text size="sm">{event && dateToBrowserTz(order.created_at, event.timezone)}</Text>
                        </div>
                    </Group>
                </div>
            </SimpleGrid>

            <Group gap="sm" mt="md" wrap="wrap">
                <Button
                    component={NavLink}
                    to={orderUrl}
                    variant="gradient"
                    gradient={{ from: 'grape', to: 'pink', deg: 90 }}
                    size="sm"
                    leftSection={<IconExternalLink size={16}/>}
                >
                    {t`View Order`}
                </Button>
                {ticketCount > 0 && (
                    <Button
                        variant="subtle"
                        size="sm"
                        leftSection={<IconPrinter size={16}/>}
                        onClick={() => window?.open(printUrl, '_blank')}
                    >
                        {t`Print Tickets`}
                    </Button>
                )}
            </Group>
        </Card>
    );
};

export const MyTickets = () => {
    const {token} = useParams();
    const {data: orders, isLoading, isError, error} = useGetOrdersByLookupToken(token);
    const [requestSuccess, setRequestSuccess] = useState(false);
    const ticketLookupMutation = useSendTicketLookupEmail();

    const form = useForm({
        initialValues: {
            email: '',
        }
    });

    const handleRequestNewLink = (values: { email: string }) => {
        ticketLookupMutation.mutate(values.email, {
            onSuccess: () => {
                setRequestSuccess(true);
            },
            onError: () => {
                showError(t`Something went wrong. Please try again.`);
            }
        });
    };

    if (isLoading) {
        return <LoadingMask/>;
    }

    if (isError) {
        return (
            <CheckoutContent>
                <div className={classes.container}>
                    <div className={classes.header}>
                        <IconAlertCircle size={48} className={classes.headerIconError}/>
                        <h1>{t`Link Expired or Invalid`}</h1>
                        <p className={classes.subtitle}>
                            {error?.message || t`This link is invalid or has expired.`}
                        </p>
                    </div>

                    <Card className={classes.requestCard}>
                        <div className={classes.requestHeader}>
                            <IconMail size={20}/>
                            <Text fw={500}>{t`Request a new link`}</Text>
                        </div>

                        {requestSuccess ? (
                            <div className={classes.successMessage}>
                                <p>{t`Check your inbox! If tickets are associated with this email, you'll receive a link to view them.`}</p>
                                <Button
                                    variant="subtle"
                                    size="sm"
                                    onClick={() => {
                                        setRequestSuccess(false);
                                        form.reset();
                                    }}
                                >
                                    {t`Try another email`}
                                </Button>
                            </div>
                        ) : (
                            <form onSubmit={form.onSubmit(handleRequestNewLink)}>
                                <div className={classes.requestForm}>
                                    <TextInput
                                        {...form.getInputProps('email')}
                                        type="email"
                                        placeholder={t`Enter your email`}
                                        required
                                        className={classes.emailInput}
                                    />
                                    <Button
                                        type="submit"
                                        color="var(--hi-pink)"
                                        loading={ticketLookupMutation.isPending}
                                        disabled={ticketLookupMutation.isPending}
                                    >
                                        {t`Send`}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </Card>
                </div>
                <PoweredByFooter/>
            </CheckoutContent>
        );
    }

    if (!orders || orders.length === 0) {
        return (
            <CheckoutContent>
                <div className={classes.container}>
                    <div className={classes.header}>
                        <IconTicket size={48} className={classes.headerIcon}/>
                        <h1>{t`No Tickets Found`}</h1>
                        <p className={classes.subtitle}>
                            {t`We couldn't find any orders associated with this email address.`}
                        </p>
                    </div>
                </div>
                <PoweredByFooter/>
            </CheckoutContent>
        );
    }

    return (
        <CheckoutContent>
            <div className={classes.container}>
                <div className={classes.header}>
                    <IconTicket size={48} className={classes.headerIcon}/>
                    <h1>{t`My Tickets`}</h1>
                    <p className={classes.subtitle}>
                        {t`Here are all the tickets associated with your email address.`}
                    </p>
                </div>

                <div className={classes.ordersList}>
                    {orders.map((order) => (
                        <OrderCard key={order.short_id} order={order}/>
                    ))}
                </div>
            </div>
            <PoweredByFooter/>
        </CheckoutContent>
    );
};

export default MyTickets;
