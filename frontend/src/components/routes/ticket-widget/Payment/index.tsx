import {useGetOrderPublic} from "../../../../queries/useGetOrderPublic.ts";
import {NavLink, useParams} from "react-router-dom";
import {loadStripe, Stripe} from "@stripe/stripe-js";
import {Elements} from "@stripe/react-stripe-js";
import StripeCheckoutForm from "../../../forms/StripeCheckoutForm";
import {useCreateStripePaymentIntent} from "../../../../queries/useCreateStripePaymentIntent.ts";
import {Alert} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {Center} from "../../../common/Center";
import classes from './Payment.module.scss';
import {useEffect, useState} from "react";
import {LoadingMask} from "../../../common/LoadingMask";

export const Payment = () => {
    const {eventId, orderShortId, eventSlug} = useParams();
    const {data: order, isFetched: isOrderFetched} = useGetOrderPublic(eventId, orderShortId);
    const {data: stripeData, isFetched: isStripeFetched} = useCreateStripePaymentIntent(eventId, orderShortId);
    const [stripePromise, setStripePromise] = useState<Promise<Stripe | null>>();

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

    if (!isOrderFetched || !isStripeFetched) {
        return <></>;
    }

    if (order?.payment_status === 'PAYMENT_RECEIVED') {
        return (
            <Center>
                <Trans>
                    This order has already been paid. <NavLink
                    to={`/checkout/${eventId}/${orderShortId}/summary`}>
                    View order details
                </NavLink>
                </Trans>
            </Center>
        );
    }

    if (order?.payment_status !== 'AWAITING_PAYMENT' && order?.payment_status !== 'PAYMENT_FAILED') {
        return (
            <Center>
                <Trans>
                    This page has expired. <NavLink to={`/checkout/${eventId}/${orderShortId}/summary`}>
                    View order details
                </NavLink>
                </Trans>
            </Center>

        );
    }

    return (
        <div className={classes.container}>
            <h1>{t`Payment`}</h1>

            {(order?.payment_status === 'PAYMENT_FAILED' || window.location.search.includes('payment_failed')) && (
                <Alert mb={20} color={'red'}>{t`Your payment was unsuccessful. Please try again.`}</Alert>
            )}

            {(!isStripeFetched || !stripePromise) && <LoadingMask/>}

            {(isStripeFetched && stripeData?.client_secret && stripePromise) && (
                <Elements options={{
                    clientSecret: stripeData?.client_secret,
                    loader:'always',
                }} stripe={stripePromise}>
                    <StripeCheckoutForm/>
                </Elements>
            )}
        </div>
    );
}