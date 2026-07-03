import {useParams, useNavigate} from "react-router";
import {useCreateRazorpayOrder} from "../../../../../../queries/useCreateRazorpayOrder.ts";
import {useEffect} from "react";
import {useGetEventPublic} from "../../../../../../queries/useGetEventPublic.ts";
import {CheckoutContent} from "../../../../../layouts/Checkout/CheckoutContent";
import {HomepageInfoMessage} from "../../../../../common/HomepageInfoMessage";
import {t} from "@lingui/macro";
import {eventHomepagePath} from "../../../../../../utilites/urlHelper.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {Event} from "../../../../../../types.ts";
import {validateThemeSettings} from "../../../../../../utilites/themeUtils.ts";
import {orderClientPublic} from "../../../../../../api/order.client.ts";
import {showError} from "../../../../../../utilites/notifications.tsx";
import {trackEvent, AnalyticsEvents} from "../../../../../../utilites/analytics.ts";

// Add Razorpay types to window
declare global {
    interface Window {
        Razorpay: any;
    }
}

interface RazorpayPaymentMethodProps {
    enabled: boolean;
    setSubmitHandler: (submitHandler: () => () => Promise<void>) => void;
}

export const RazorpayPaymentMethod = ({enabled, setSubmitHandler}: RazorpayPaymentMethodProps) => {
    const {eventId, orderShortId} = useParams();
    const navigate = useNavigate();
    const {
        data: razorpayData,
        isFetched: isRazorpayFetched,
        error: razorpayOrderError
    } = useCreateRazorpayOrder(eventId, orderShortId);
    
    const {data: event} = useGetEventPublic(eventId);

    useEffect(() => {
        if (document.getElementById('razorpay-sdk')) {
            return;
        }
        
        const script = document.createElement("script");
        script.id = 'razorpay-sdk';
        script.src = "https://checkout.razorpay.com/v1/checkout.js";
        script.async = true;
        document.body.appendChild(script);
    }, []);

    useEffect(() => {
        if (!isRazorpayFetched || !razorpayData) {
            return;
        }

        const handlePaymentSubmit = async () => {
            return new Promise<void>((resolve, reject) => {
                if (!window.Razorpay) {
                    showError(t`Razorpay SDK failed to load. Please try again.`);
                    reject(new Error("Razorpay SDK not loaded"));
                    return;
                }

                const themeSettings = validateThemeSettings(event?.settings?.homepage_theme_settings);

                const options = {
                    key: razorpayData.key_id,
                    amount: razorpayData.amount_minor,
                    currency: razorpayData.currency,
                    name: event?.title || "Hi.Events",
                    description: t`Order ID: ` + orderShortId,
                    order_id: razorpayData.razorpay_order_id,
                    prefill: razorpayData.prefill,
                    theme: {
                        color: themeSettings.accent,
                    },
                    handler: async function (response: any) {
                        try {
                            // Send callback data to our backend for verification
                            await orderClientPublic.razorpayPaymentCallback(
                                Number(eventId), 
                                orderShortId as string, 
                                {
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_signature: response.razorpay_signature,
                                }
                            );
                            
                            trackEvent(AnalyticsEvents.PURCHASE_COMPLETED, { value: razorpayData.amount_minor });
                            navigate(`/checkout/${eventId}/${orderShortId}/summary`);
                            resolve();
                        } catch (error: any) {
                            showError(error.response?.data?.message || t`Payment verification failed.`);
                            reject(error);
                        }
                    },
                    modal: {
                        ondismiss: function () {
                            // User closed the modal
                            reject(new Error("Payment cancelled"));
                        },
                    },
                };

                const rzp = new window.Razorpay(options);
                rzp.on('payment.failed', function (response: any) {
                    showError(response.error.description || t`Payment failed`);
                    reject(new Error(response.error.description));
                });
                
                rzp.open();
            });
        };

        setSubmitHandler(() => handlePaymentSubmit);

        // Cleanup handler on unmount
        return () => {
            setSubmitHandler(() => async () => Promise.resolve());
        };
    }, [isRazorpayFetched, razorpayData, eventId, orderShortId, event, navigate, setSubmitHandler]);

    if (!enabled) {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="warning"
                    message={t`Payments not available`}
                    subtitle={t`Razorpay payments are not enabled for this event.`}
                    link={eventHomepagePath(event as Event)}
                    linkText={t`Return to Event`}
                />
            </CheckoutContent>
        );
    }

    if (razorpayOrderError && event) {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="error"
                    /* @ts-ignore */
                    message={razorpayOrderError.response?.data?.message || t`Something went wrong`}
                    subtitle={t`Please restart the checkout process.`}
                    link={eventHomepagePath(event)}
                    linkText={t`Return to Event`}
                />
            </CheckoutContent>
        );
    }

    return (
        <>
            {!isRazorpayFetched && <LoadingMask/>}
            {/* The actual UI is just the "Pay" button in the parent component which triggers the modal */}
            {(isRazorpayFetched && razorpayData) && (
                <div style={{ padding: '20px', textAlign: 'center', color: 'var(--mantine-color-dimmed)' }}>
                    {t`Click the Pay button below to securely complete your payment with Razorpay.`}
                </div>
            )}
        </>
    );
}
