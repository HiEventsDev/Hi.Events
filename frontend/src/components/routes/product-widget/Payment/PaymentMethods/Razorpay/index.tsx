import {useEffect, useState} from "react";
import {useNavigate, useParams} from "react-router";
import {useCreateRazorpayOrder} from "../../../../../../queries/useCreateRazorpayOrder.ts";
import {useGetEventPublic} from "../../../../../../queries/useGetEventPublic.ts";
import {CheckoutContent} from "../../../../../layouts/Checkout/CheckoutContent";
import {HomepageInfoMessage} from "../../../../../common/HomepageInfoMessage";
import {t} from "@lingui/macro";
import {eventHomepagePath} from "../../../../../../utilites/urlHelper.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {Event} from "../../../../../../types.ts";
import {useGetOrderPublic} from "../../../../../../queries/useGetOrderPublic.ts";
import {eventCheckoutPath} from "../../../../../../utilites/urlHelper.ts";
import { useMutation } from "@tanstack/react-query";
import { orderClientPublic } from "../../../../../../api/order.client.ts";

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
    const navigate = useNavigate();
    const {eventId, orderShortId} = useParams();
    const {
        data: razorpayData,
        isFetched: isRazorpayFetched,
        error: razorpayOrderError
    } = useCreateRazorpayOrder(eventId, orderShortId);
    const {data: event} = useGetEventPublic(eventId);
    const {data: order} = useGetOrderPublic(eventId, orderShortId, ['event']);
    const [isLoading, setIsLoading] = useState(false);

    const verifyMutation = useMutation({
        mutationFn: (payload: {
            razorpay_payment_id: string,
            razorpay_order_id: string,
            razorpay_signature: string,
        }) => {
            if (!eventId || !orderShortId) {
                throw new Error('Missing event ID or order ID');
            }
            return orderClientPublic.verifyRazorpayPayment(Number(eventId), orderShortId, payload);
        },
        onSuccess: () => navigate(eventCheckoutPath(eventId, orderShortId, 'summary'))
    });

    const loadRazorpayScript = () => {
        return new Promise((resolve, reject) => {
            if (window.Razorpay) {
                resolve(true);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://checkout.razorpay.com/v1/checkout.js';
            script.async = true;
            script.onload = () => resolve(true);
            script.onerror = () => reject(new Error('Failed to load Razorpay script'));
            document.body.appendChild(script);
        });
    };

    const handleRazorpayPayment = async () => {
        if (!razorpayData || !order || !event) return;

        setIsLoading(true);
        try {
            await loadRazorpayScript();

            const options = {
                key: razorpayData.key_id,
                amount: razorpayData.amount,
                currency: razorpayData.currency,
                name: event.title,
                description: `Order ${order.short_id}`,
                order_id: razorpayData.razorpay_order_id,
                handler: async (response: any) => {
                    try {
                        await verifyMutation.mutate({
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature,
                        });

                    } catch (error) {
                        console.error('Payment verification error:', error);
                    } finally {
                        setIsLoading(false);
                    }
                },
                prefill: {
                    name: `${order.first_name} ${order.last_name}`,
                    email: order.email,
                    contact: '', // Optional: Could collect phone number in earlier step
                },
                notes: {
                    order_short_id: order.short_id,
                    event_id: eventId,
                },
                theme: {
                    color: '#10B981', // Use theme accent color
                },
                modal: {
                    ondismiss: () => {
                        setIsLoading(false);
                    },
                },
            };

            const razorpayInstance = new window.Razorpay(options);
            razorpayInstance.open();
        } catch (error) {
            console.error('Razorpay payment error:', error);
            setIsLoading(false);
            // Handle error
        }
    };

    useEffect(() => {
        if (setSubmitHandler) {
            setSubmitHandler(() => handleRazorpayPayment);
        }
    }, [setSubmitHandler, razorpayData, order, event]);

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
                    message={razorpayOrderError.response?.data?.message || t`Something went wrong`}
                    subtitle={t`Please restart the checkout process.`}
                    link={eventHomepagePath(event)}
                    linkText={t`Return to Event`}
                />
            </CheckoutContent>
        );
    }

    if (!isRazorpayFetched) {
        return <LoadingMask/>;
    }

    return (
        <div>
            <h2>{t`Payment`}</h2>
            <p className="text-gray-600 mb-6">
                {t`You will be redirected to Razorpay's secure payment page to complete your transaction.`}
            </p>
            
            {isLoading && <LoadingMask/>}
            
            {/* Payment method details display */}
            <div className="p-4 border rounded-lg bg-gray-50">
                <div className="flex items-center gap-3 mb-2">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg className="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 className="font-medium">{t`Secure Payment`}</h3>
                        <p className="text-sm text-gray-600">
                            {t`Powered by Razorpay`}
                        </p>
                    </div>
                </div>
                
                {razorpayData && (
                    <div className="mt-4 text-sm text-gray-600">
                        <div className="flex justify-between">
                            <span>{t`Order ID:`}</span>
                            <span className="font-mono">{order?.short_id}</span>
                        </div>
                        <div className="flex justify-between mt-1">
                            <span>{t`Amount:`}</span>
                            <span className="font-medium">
                                {new Intl.NumberFormat('en-IN', {
                                    style: 'currency',
                                    currency: razorpayData.currency
                                }).format(razorpayData.amount / 100)}
                            </span>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};