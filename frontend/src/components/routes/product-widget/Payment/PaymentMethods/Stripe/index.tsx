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
import {validateThemeSettings} from "../../../../../../utilites/themeUtils.ts";

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
                    status="warning"
                    message={t`Payments not available`}
                    subtitle={t`Stripe payments are not enabled for this event.`}
                    link={eventHomepagePath(event as Event)}
                    linkText={t`Return to Event`}
                />
            </CheckoutContent>
        );
    }

    if (stripePaymentIntentError && event) {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="error"
                    /* @ts-ignore */
                    message={stripePaymentIntentError.response?.data?.message || t`Something went wrong`}
                    subtitle={t`Please restart the checkout process.`}
                    link={eventHomepagePath(event)}
                    linkText={t`Return to Event`}
                />
            </CheckoutContent>
        );
    }

    if (!isStripeFetched) {
        return <LoadingMask/>;
    }

    const themeSettings = validateThemeSettings(event?.settings?.homepage_theme_settings);
    const stripeTheme = themeSettings.mode === 'dark' ? 'night' : 'stripe';

    return (
        <>
            {(!stripePromise) && <LoadingMask/>}

            {(isStripeFetched && stripeData?.client_secret && stripePromise) && (
                <Elements options={{
                    clientSecret: stripeData?.client_secret,
                    loader: 'always',
                    appearance: {
                        theme: stripeTheme,
                        variables: {
                            colorPrimary: themeSettings.accent,
                        },
                    },
                }} stripe={stripePromise}>
                    <StripeCheckoutForm setSubmitHandler={setSubmitHandler} />
                </Elements>
            )}
        </>
    );
}
