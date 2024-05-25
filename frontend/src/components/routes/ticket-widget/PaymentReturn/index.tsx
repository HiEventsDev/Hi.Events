import {usePollGetOrderPublic} from "../../../../queries/usePollGetOrderPublic.ts";
import {useNavigate, useParams} from "react-router-dom";
import {useEffect, useState} from "react";
import classes from './PaymentReturn.module.scss';
import {t} from "@lingui/macro";
import {useGetOrderStripePaymentIntentPublic} from "../../../../queries/useGetOrderStripePaymentIntentPublic.ts";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {eventCheckoutPath} from "../../../../utilites/urlHelper.ts";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {isSsr} from "../../../../utilites/helpers.ts";

/**
 * This component is responsible for handling the return from the payment provider.
 * Stripe should send a webhook to the backend to update the order status to 'COMPLETED'
 * However, if this fails, we will poll the order status to check if the payment has been processed.
 * This is a rare occurrence, but we should handle it gracefully.
 * It will also make local development easier in times when the webhook is not configured correctly.
 **/
export const PaymentReturn = () => {
    const [shouldPoll, setShouldPoll] = useState(true);
    const {eventId, orderShortId} = useParams();
    const {data: order} = usePollGetOrderPublic(eventId, orderShortId, shouldPoll);
    const navigate = useNavigate();
    const [attemptManualConfirmation, setAttemptManualConfirmation] = useState(false);
    const paymentIntentQuery = useGetOrderStripePaymentIntentPublic(eventId, orderShortId, attemptManualConfirmation);
    const [cannotConfirmPayment, setCannotConfirmPayment] = useState(false);

    useEffect(
        () => {
            const timeout = setTimeout(() => {
                setShouldPoll(false);
                setAttemptManualConfirmation(true);
            }, 10000); //todo - this should be a env variable

            return () => {
                clearTimeout(timeout);
            };
        },
        []
    );

    useEffect(() => {
        if (!paymentIntentQuery.isFetched) {
            return;
        }
        if (paymentIntentQuery.data?.status === 'succeeded') {
            navigate(eventCheckoutPath(eventId, orderShortId, 'summary'));
        } else {
            // At this point we've tried multiple times to confirm the payment and failed.
            // This could be due to a network error on our end, or a problem with the payment provider (Stripe).
            // This should be a rare occurrence, but we should handle it gracefully.
            setCannotConfirmPayment(true);
        }
    }, [paymentIntentQuery.isFetched]);

    useEffect(() => {
        if (isSsr() || !order) {
            return;
        }

        if (order?.status === 'COMPLETED') {
            navigate(eventCheckoutPath(eventId, orderShortId, 'summary'));
        }
        if (order?.payment_status === 'PAYMENT_FAILED' || (typeof window !== 'undefined' && window?.location.search.includes('failed'))) {
            navigate(eventCheckoutPath(eventId, orderShortId, 'payment') + '?payment_failed=true');
        }
    }, [order]);

    return (
        <CheckoutContent>
            <div className={classes.container}>
                {!cannotConfirmPayment && (
                    <HomepageInfoMessage
                        iconType={'processing'}
                        message={(
                            <>
                                {(!shouldPoll && paymentIntentQuery.isFetched) && t`We could not process your payment. Please try again or contact support.`}
                                {(!shouldPoll && !paymentIntentQuery.isFetched) && t`Almost there! We're just waiting for your payment to be processed. This should only take a few seconds..`}
                                {shouldPoll && t`We're processing your order. Please wait...`}
                            </>
                        )}/>
                )}

                {cannotConfirmPayment && t`We were unable to confirm your payment. Please try again or contact support.`}
            </div>
        </CheckoutContent>
    );
}

export default PaymentReturn;