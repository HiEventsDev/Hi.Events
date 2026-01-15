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
import { Badge, Card, Divider, Group, Stack, Text, Title } from "@mantine/core";
import { Image } from "@mantine/core";
import { AxiosError } from "axios";

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
        const errorMessage = (razorpayOrderError as AxiosError<any>)?.response?.data?.message ?? t`Something went wrong`;

        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="error"
                    message={ errorMessage || t`Something went wrong`}
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
    <>
        <Stack gap="xs" mb="lg">
            <Title order={3}>{t`Payment`}</Title>
            <Text size="sm" c="dimmed">
                {t`You will be redirected to Razorpay to securely complete your payment.`}
            </Text>
        </Stack>

        {isLoading && <LoadingMask />}

        {/* Payment Method Card */}
        <Card withBorder radius="md" padding="lg">
            <Stack gap="md">
                {/* Method Header */}
                <Group justify="space-between">
                    <Group gap="sm">
                        <Image
                            src="https://razorpay.com/favicon.png"
                            alt="Razorpay"
                            w={32}
                            h={32}
                            fit="contain"
                        />
                        <div>
                            <Text fw={500}>Razorpay</Text>
                            <Text size="xs" c="dimmed">
                                Cards • UPI • NetBanking • Wallets
                            </Text>
                        </div>
                    </Group>

                    <Badge color="green" variant="light">
                        {t`Secure`}
                    </Badge>
                </Group>

                <Divider />

                {/* Order Summary */}
                {razorpayData && (
                    <Stack gap={6}>
                        <Group justify="space-between">
                            <Text size="sm" c="dimmed">
                                {t`Order ID`}
                            </Text>
                            <Text size="sm" ff="monospace">
                                {order?.short_id}
                            </Text>
                        </Group>

                        <Group justify="space-between">
                            <Text size="sm" c="dimmed">
                                {t`Amount`}
                            </Text>
                            <Text size="sm" fw={500}>
                                {new Intl.NumberFormat('en-IN', {
                                    style: 'currency',
                                    currency: razorpayData.currency,
                                }).format(razorpayData.amount / 100)}
                            </Text>
                        </Group>
                    </Stack>
                )}
            </Stack>
        </Card>

        {/* Footer Hint */}
        <Text size="xs" c="dimmed" ta="center" mt="md">
            {t`Payments are processed securely by Razorpay using industry-standard encryption.`}
        </Text>
    </>
);
};