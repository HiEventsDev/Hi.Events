import {useParams} from "react-router-dom";
import {loadStripe, Stripe} from "@stripe/stripe-js";
import {Elements} from "@stripe/react-stripe-js";
import StripeCheckoutForm from "../../../forms/StripeCheckoutForm";
import {useCreateStripePaymentIntent} from "../../../../queries/useCreateStripePaymentIntent.ts";
import {useEffect, useState} from "react";
import {LoadingMask} from "../../../common/LoadingMask";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {t} from "@lingui/macro";
import {Anchor} from "@mantine/core";
import {eventHomepagePath} from "../../../../utilites/urlHelper.ts";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";

const Payment = () => {
    const {eventId, orderShortId} = useParams();
    const {
        data: stripeData,
        isFetched: isStripeFetched,
        error: stripePaymentIntentError
    } = useCreateStripePaymentIntent(eventId, orderShortId);
    const [stripePromise, setStripePromise] = useState<Promise<Stripe | null>>();
    const {data: event} = useGetEventPublic(eventId);

    useEffect(() => {
        if (!stripeData?.client_secret) {
            return;
        }

        const stripeAccount = stripeData?.account_id;
        const options = stripeAccount ? {
            stripeAccount: stripeAccount
        } : {};

        setStripePromise(loadStripe(import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY, options));
    }, [stripeData]);

    if (stripePaymentIntentError && event) {
        return (
            <CheckoutContent>
                {/* eslint-disable-next-line @typescript-eslint/ban-ts-comment */}
                {/*@ts-ignore}*/}
                {stripePaymentIntentError.response?.data?.message || t`Sorry, something has gone wrong. Please restart the checkout process.`}
                {' '} <Anchor href={eventHomepagePath(event)}>{t`Return to event page`}</Anchor>
            </CheckoutContent>
        );
    }

    if (!isStripeFetched) {
        return <LoadingMask/>;
    }

    return (
        <>
            {(!stripePromise) && <LoadingMask/>}

            {(isStripeFetched && stripeData?.client_secret && stripePromise) && (
                <Elements options={{
                    clientSecret: stripeData?.client_secret,
                    loader: 'always',
                }} stripe={stripePromise}>
                    <StripeCheckoutForm/>
                </Elements>
            )}
        </>
    );
}

export default Payment;