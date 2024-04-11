import {useEffect, useState} from "react";
import {PaymentElement, useElements, useStripe} from "@stripe/react-stripe-js";
import {useParams} from "react-router-dom";
import * as stripeJs from "@stripe/stripe-js";
import {Alert, Skeleton} from "@mantine/core";
import {t} from "@lingui/macro";
import classes from './StripeCheckoutForm.module.scss';
import {LoadingMask} from "../../common/LoadingMask";
import {useGetOrderPublic} from "../../../queries/useGetOrderPublic.ts";
import {useGetEventPublic} from "../../../queries/useGetEventPublic.ts";
import {CheckoutContent} from "../../layouts/Checkout/CheckoutContent";
import {CheckoutFooter} from "../../layouts/Checkout/CheckoutFooter";
import {Event} from "../../../types.ts";
import {eventCheckoutPath, eventHomepagePath} from "../../../utilites/urlHelper.ts";
import {HomepageInfoMessage} from "../../common/HomepageInfoMessage";

export default function StripeCheckoutForm() {
    const {eventId, orderShortId} = useParams();
    const stripe = useStripe();
    const elements = useElements();
    const [message, setMessage] = useState<string | undefined>('');
    const [isLoading, setIsLoading] = useState(false);
    const {data: order, isFetched: isOrderFetched} = useGetOrderPublic(eventId, orderShortId);
    const {data: event, isFetched: isEventFetched} = useGetEventPublic(eventId);

    useEffect(() => {
        if (!stripe) {
            return;
        }

        const clientSecret = new URLSearchParams(window?.location.search).get(
            "payment_intent_client_secret"
        );

        if (!clientSecret) {
            return;
        }

        stripe.retrievePaymentIntent(clientSecret).then(({paymentIntent}) => {
            switch (paymentIntent?.status) {
                case "succeeded":
                    setMessage(t`Payment succeeded!`);
                    break;
                case "processing":
                    setMessage(t`Your payment is processing.`);
                    break;
                case "requires_payment_method":
                    setMessage(t`Your payment was not successful, please try again.`);
                    break;
                default:
                    setMessage(t`Something went wrong.`);
                    break;
            }
        });
    }, [stripe]);

    if (order?.payment_status === 'PAYMENT_RECEIVED') {
        return (
            <HomepageInfoMessage
                message={t`This order has already been paid.`}
                linkText={t`View order details`}
                link={eventCheckoutPath(eventId, orderShortId, 'summary')}
            />
        );
    }

    if (order?.payment_status !== 'AWAITING_PAYMENT' && order?.payment_status !== 'PAYMENT_FAILED') {
        return (
            <HomepageInfoMessage
                message={t`This order page is no longer available.`}
                linkText={t`View order details`}
                link={eventHomepagePath(event as Event)}
            />
        );
    }

    const handleSubmit = async (e: any) => {
        e.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        setIsLoading(true);

        const {error} = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: window?.location.origin + `/checkout/${eventId}/${orderShortId}/payment_return`
            },
        });

        if (error.type === "card_error" || error.type === "validation_error") {
            setMessage(error.message);
        } else {
            setMessage(t`An unexpected error occurred.`);
        }

        setIsLoading(false);
    };

    const paymentElementOptions: stripeJs.StripePaymentElementOptions = {
        layout: {
            type: "accordion",
            defaultCollapsed: false,
            radios: true,
        },
    }

    if (!isOrderFetched || !isEventFetched) {
        return (
            <CheckoutContent>
                <Skeleton height={300} mb={20}/>
            </CheckoutContent>
        )
    }

    return (
        <form id="payment-form" onSubmit={handleSubmit}>
            <CheckoutContent>
                <h2>
                    {t`Payment`}
                </h2>
                {(order?.payment_status === 'PAYMENT_FAILED' || window?.location.search.includes('payment_failed')) && (
                    <Alert mb={20} color={'red'}>{t`Your payment was unsuccessful. Please try again.`}</Alert>
                )}

                {message !== '' && <Alert mb={20}>{message}</Alert>}
                <LoadingMask/>
                <PaymentElement className={classes.stripeForElement} id="payment-element"
                                options={paymentElementOptions} onReady={() => setIsLoading(false)}/>

                <div className={classes.stripeLogo}>
                    <img
                        src={'https://cdn.brandfolder.io/KGT2DTA4/at/g65qkq94m43qc3c9fqnhh3m/Powered_by_Stripe_-_black.svg'}
                        alt={t`Powered by Stripe`} width={'100px'} height={'auto'}/>
                </div>
            </CheckoutContent>
            <CheckoutFooter
                event={event as Event}
                order={order}
                isLoading={isLoading}
                buttonText={t`Complete Payment`}
            />
        </form>
    );
}
