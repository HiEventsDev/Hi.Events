import  {useEffect, useState} from "react";
import {PaymentElement, useElements, useStripe} from "@stripe/react-stripe-js";
import {useParams} from "react-router-dom";
import * as stripeJs from "@stripe/stripe-js";
import {Alert, Button} from "@mantine/core";
import {t} from "@lingui/macro";
import classes from './StripeCheckoutForm.module.scss';
import {LoadingMask} from "../../common/LoadingMask";

export default function StripeCheckoutForm() {
    const {eventId, orderShortId} = useParams();
    const stripe = useStripe();
    const elements = useElements();
    const [message, setMessage] = useState<string | undefined>('');
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        if (!stripe) {
            return;
        }

        const clientSecret = new URLSearchParams(window.location.search).get(
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

    const handleSubmit = async (e: any) => {
        e.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        setIsLoading(true);

        const {error} = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: window.location.origin + `/checkout/${eventId}/${orderShortId}/payment_return`
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

    return (
        <>
            <form id="payment-form" onSubmit={handleSubmit}>
                {message !== '' && <Alert mb={20}>{message}</Alert>}
                <LoadingMask />
                <PaymentElement className={classes.stripeForElement} id="payment-element"  options={paymentElementOptions} onReady={() => setIsLoading(false)}/>
                <Button type={'submit'} mt={20} fullWidth disabled={isLoading || !stripe || !elements} id="submit">
                    {t`Complete Payment`}
                </Button>
            </form>
            <div className={classes.stripeLogo}>
                <img
                    src={'https://cdn.brandfolder.io/KGT2DTA4/at/g65qkq94m43qc3c9fqnhh3m/Powered_by_Stripe_-_black.svg'}
                    alt={t`Powered by Stripe`} width={'100px'} height={'auto'}/>
            </div>
        </>
    );
}
