import {useParams} from "react-router";
import {useCreateStripePaymentIntent} from "../../../../../../queries/useCreateStripePaymentIntent.ts";
import {useEffect, useState} from "react";
import {loadStripe, Stripe} from "@stripe/stripe-js";
import {useGetEventPublic} from "../../../../../../queries/useGetEventPublic.ts";
import {CheckoutContent} from "../../../../../layouts/Checkout/CheckoutContent";
import {HomepageInfoMessage} from "../../../../../common/HomepageInfoMessage";
import {t} from "@lingui/macro";
import {eventHomepagePath} from "../../../../../../utilites/urlHelper.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";
import {Elements} from "@stripe/react-stripe-js";
import StripeCheckoutForm from "../../../../../forms/StripeCheckoutForm";
import {Event} from "../../../../../../types.ts";

interface StripePaymentMethodProps {
    enabled: boolean;
    setSubmitHandler: (submitHandler: () => () => Promise<void>) => void;
}

export const StripePaymentMethod = ({enabled, setSubmitHandler}: StripePaymentMethodProps) => {
    const {eventId, orderShortId} = useParams();
    const {
        data: stripeData,
        isFetched: isStripeFetched,
        error: stripePaymentIntentError
    } = useCreateStripePaymentIntent(eventId, orderShortId);
    const [stripePromise, setStripePromise] = useState<Promise<Stripe | null>>();
    const {data: event} = useGetEventPublic(eventId);

    useEffect(() => {
        if (!stripeData?.client_secret || !stripeData?.public_key) {
            return;
        }

        const stripeAccount = stripeData?.account_id;
        const options = stripeAccount ? {
            stripeAccount: stripeAccount
        } : {};

        setStripePromise(loadStripe(stripeData.public_key, options));
    }, [stripeData]);

    if (!enabled) {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    message={t`Stripe payments are not enabled for this event.`}
                    link={eventHomepagePath(event as Event)}
                    linkText={t`Return to event page`}
                />
            </CheckoutContent>
        );
    }

    if (stripePaymentIntentError && event) {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    /* @ts-ignore */
                    message={stripePaymentIntentError.response?.data?.message || t`Sorry, something has gone wrong. Please restart the checkout process.`}
                    link={eventHomepagePath(event)}
                    linkText={t`Return to event page`}
                />
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
                    <StripeCheckoutForm setSubmitHandler={setSubmitHandler} />
                </Elements>
            )}
        </>
    );
}
