import {NavLink, Outlet, useNavigate, useParams} from "react-router";
import classes from './Checkout.module.scss';
import {useGetOrderPublic} from "../../../queries/useGetOrderPublic.ts";
import {t} from "@lingui/macro";
import {Countdown} from "../../common/Countdown";
import {CheckoutSidebar} from "./CheckoutSidebar";
import {ActionIcon, Button, Group, Modal, Tooltip} from "@mantine/core";
import {IconArrowLeft, IconPrinter, IconReceipt} from "@tabler/icons-react";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {ShareComponent} from "../../common/ShareIcon";
import {AddToEventCalendarButton} from "../../common/AddEventToCalendarButton";
import {useMediaQuery} from "@mantine/hooks";
import {useState} from "react";
import {Invoice} from "../../../types.ts";
import {orderClientPublic} from "../../../api/order.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {withLoadingNotification} from "../../../utilites/withLoadingNotification.tsx";

const Checkout = () => {
    const {eventId, orderShortId} = useParams();
    const {data: order} = useGetOrderPublic(eventId, orderShortId, ['event']);
    const event = order?.event;
    const navigate = useNavigate();
    const orderIsCompleted = order?.status === 'COMPLETED';
    const orderIsReserved = order?.status === 'RESERVED';
    const orderIsAwaitingOfflinePayment = order?.status === 'AWAITING_OFFLINE_PAYMENT';
    const isMobile = useMediaQuery('(max-width: 768px)');
    const [isExpired, setIsExpired] = useState(false);
    const orderHasAttendees = order?.attendees && order.attendees.length > 0;

    const handleExpiry = () => {
        setIsExpired(true);
    };

    const handleReturn = () => {
        navigate(`/event/${event?.id}/${event?.slug}`);
    };

    const handleInvoiceDownload = async (invoice: Invoice) => {
        await withLoadingNotification(
            async () => {
                const blob = await orderClientPublic.downloadInvoice(eventId, orderShortId);
                downloadBinary(blob, invoice.invoice_number + '.pdf');
            },
            {
                loading: {
                    title: t`Downloading Invoice`,
                    message: t`Please wait while we prepare your invoice...`
                },
                success: {
                    title: t`Success`,
                    message: t`Invoice downloaded successfully`
                },
                error: {
                    title: t`Error`,
                    message: t`Failed to download invoice. Please try again.`
                }
            }
        );
    }

    return (
        <>
            <div className={classes.container}>
                <div className={classes.mainContent}>
                    <header className={classes.header}>
                        {(event) && (
                            <div className={classes.actionBar}>
                                <Group justify="space-between" wrap="nowrap">
                                    <Button
                                        title={t`Back to event page`}
                                        component={NavLink}
                                        variant="subtle"
                                        leftSection={<IconArrowLeft size={20}/>}
                                        to={eventHomepageUrl(event)}
                                    >
                                        {!isMobile && t`Event Homepage`}
                                    </Button>

                                    <span className={classes.title}>
                                        {order.status === 'RESERVED' && t`Checkout`}
                                        {order.status === 'COMPLETED' && t`Your Order`}
                                    </span>

                                    {orderIsReserved && (
                                        <Group gap="5px">
                                            <span>
                                                {t`Time left:`}
                                            </span>
                                            <Countdown
                                                displayType={'short'}
                                                className={classes.countdown}
                                                closeToExpiryClassName={classes.countdownCloseToExpiry}
                                                targetDate={order.reserved_until}
                                                onExpiry={handleExpiry}
                                            />
                                        </Group>
                                    )}

                                    {(orderIsCompleted || orderIsAwaitingOfflinePayment) && (
                                        <Group gap="2px">
                                            <ShareComponent
                                                title={event.title}
                                                text={t`Check out this event!`}
                                                url={eventHomepageUrl(event)}
                                                hideShareButtonText={isMobile}
                                            />

                                            <AddToEventCalendarButton event={event}/>

                                            {orderHasAttendees && (
                                                <Tooltip label={t`Print Tickets`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        onClick={() => window?.open(`/order/${eventId}/${orderShortId}/print`, '_blank')}
                                                    >
                                                        <IconPrinter size={20}/>
                                                    </ActionIcon>
                                                </Tooltip>
                                            )}

                                            {order.latest_invoice && (
                                                <Tooltip
                                                    label={t`Download Invoice`}>
                                                    <ActionIcon
                                                        variant="subtle"
                                                        onClick={() => handleInvoiceDownload(order.latest_invoice as Invoice)}
                                                    >
                                                        <IconReceipt size={20}/>
                                                    </ActionIcon>
                                                </Tooltip>
                                            )}
                                        </Group>
                                    )}
                                </Group>
                            </div>
                        )}
                    </header>
                    <Outlet/>
                </div>

                {(order && event) && <CheckoutSidebar className={classes.sidebar} event={event} order={order}/>}
            </div>

            <Modal
                opened={isExpired}
                onClose={handleReturn}
                withCloseButton={false}
                centered
                size="m"
            >
                <div style={{textAlign: 'center', padding: '20px 0'}}>
                    <h3>
                        {t`You have run out of time to complete your order.`}
                    </h3>
                    <p>
                        {t`Please return to the event page to start over.`}
                    </p>
                    <Button
                        onClick={handleReturn}
                        variant="filled"
                    >
                        {t`Return to Event Page`}
                    </Button>
                </div>
            </Modal>
        </>
    );
}

export default Checkout;
