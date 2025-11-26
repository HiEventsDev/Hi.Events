import React, {useState} from "react";
import {useNavigate, useParams} from "react-router";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {StripePaymentMethod} from "./PaymentMethods/Stripe";
import {OfflinePaymentMethod} from "./PaymentMethods/Offline";
import {Event} from "../../../../types.ts";
import {Button, Group, Text} from "@mantine/core";
import {IconBuildingBank, IconLock, IconWallet} from "@tabler/icons-react";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {t, Trans} from "@lingui/macro";
import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {
    useTransitionOrderToOfflinePaymentPublic
} from "../../../../mutations/useTransitionOrderToOfflinePaymentPublic.ts";
import {Card} from "../../../common/Card";
import {InlineOrderSummary} from "../../../common/InlineOrderSummary";
import {showError} from "../../../../utilites/notifications.tsx";
import {getConfig} from "../../../../utilites/config.ts";
import classes from "./Payment.module.scss";

const Payment = () => {
    const navigate = useNavigate();
    const {eventId, orderShortId} = useParams();
    const {data: event, isFetched: isEventFetched} = useGetEventPublic(eventId);
    const {data: order, isFetched: isOrderFetched} = useGetOrderPublic(eventId, orderShortId, ['event']);
    const isLoading = !isOrderFetched;
    const [isPaymentLoading, setIsPaymentLoading] = useState(false);
    const [activePaymentMethod, setActivePaymentMethod] = useState<'STRIPE' | 'OFFLINE' | null>(null);
    const [submitHandler, setSubmitHandler] = useState<(() => Promise<void>) | null>(null);
    const transitionOrderToOfflinePaymentMutation = useTransitionOrderToOfflinePaymentPublic();

    const isStripeEnabled = event?.settings?.payment_providers?.includes('STRIPE');
    const isOfflineEnabled = event?.settings?.payment_providers?.includes('OFFLINE');

    React.useEffect(() => {
        // Automatically set the first available payment method
        if (isStripeEnabled) {
            setActivePaymentMethod('STRIPE');
        } else if (isOfflineEnabled) {
            setActivePaymentMethod('OFFLINE');
        } else {
            setActivePaymentMethod(null); // No methods available
        }
    }, [isStripeEnabled, isOfflineEnabled]);

    React.useEffect(() => {
        // Scroll to top when payment page loads
        window?.scrollTo(0, 0);
    }, []);

    const handleParentSubmit = () => {
        if (submitHandler) {
            setIsPaymentLoading(true);
            submitHandler().finally(() => setIsPaymentLoading(false));
        }
    };

    const handleSubmit = async () => {
        if (activePaymentMethod === 'STRIPE') {
            handleParentSubmit();
        } else if (activePaymentMethod === 'OFFLINE') {
            setIsPaymentLoading(true);

            await transitionOrderToOfflinePaymentMutation.mutateAsync({
                eventId,
                orderShortId
            }, {
                onSuccess: () => {
                    navigate(`/checkout/${eventId}/${orderShortId}/summary`);
                },
                onError: (error: any) => {
                    setIsPaymentLoading(false);
                    showError(error.response?.data?.message || t`Offline payment failed. Please try again or contact the event organizer.`);
                }
            });
        }
    };

    if (!isStripeEnabled && !isOfflineEnabled && isOrderFetched && isEventFetched) {
        return (
            <CheckoutContent>
                <Card>
                    {t`No payment methods are currently available. Please contact the event organizer for assistance.`}
                </Card>
            </CheckoutContent>
        );
    }

    return (
        <>
            <CheckoutContent>
                {(event && order) && (
                    <InlineOrderSummary event={event} order={order} defaultExpanded={false}/>
                )}
                {isStripeEnabled && (
                    <div style={{display: activePaymentMethod === 'STRIPE' ? 'block' : 'none'}}>
                        <StripePaymentMethod enabled={true} setSubmitHandler={setSubmitHandler}/>
                    </div>
                )}

                {isOfflineEnabled && (
                    <div style={{display: activePaymentMethod === 'OFFLINE' ? 'block' : 'none'}}>
                        <OfflinePaymentMethod event={event as Event}/>
                    </div>
                )}

                {(isStripeEnabled && isOfflineEnabled) && (
                    <div className={classes.paymentMethodSelector}>
                        <Text size="sm" c="dimmed" className={classes.paymentMethodLabel}>
                            {t`Payment method`}
                        </Text>
                        <div className={classes.paymentMethodTabs}>
                            <button
                                type="button"
                                className={`${classes.paymentMethodTab} ${activePaymentMethod === 'STRIPE' ? classes.active : ''}`}
                                onClick={() => setActivePaymentMethod('STRIPE')}
                            >
                                <IconWallet size={18}/>
                                <span>{t`Online`}</span>
                            </button>
                            <button
                                type="button"
                                className={`${classes.paymentMethodTab} ${activePaymentMethod === 'OFFLINE' ? classes.active : ''}`}
                                onClick={() => setActivePaymentMethod('OFFLINE')}
                            >
                                <IconBuildingBank size={18}/>
                                <span>{t`Offline`}</span>
                            </button>
                        </div>
                    </div>
                )}

                <div className={classes.checkoutActions}>
                    <Button
                        className={classes.continueButton}
                        loading={isLoading || isPaymentLoading}
                        onClick={handleSubmit}
                    >
                        {order?.is_payment_required ? (
                            <Group gap={8} wrap="nowrap">
                                <IconLock size={16}/>
                                <Text fw={600}>{t`Pay`} {formatCurrency(order.total_gross, order.currency)}</Text>
                            </Group>
                        ) : t`Complete Payment`}
                    </Button>
                    <p className={classes.tosNotice}>
                        <Trans>
                            By continuing, you agree to the{' '}
                            <a
                                href={getConfig('VITE_TOS_URL', 'https://hi.events/terms-of-service') as string}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {getConfig('VITE_APP_NAME', 'Hi.Events')} Terms of Service
                            </a>
                        </Trans>
                    </p>
                </div>
            </CheckoutContent>
        </>
    );
}

export default Payment;
