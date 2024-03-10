import {usePollGetOrderPublic} from "../../../../queries/usePollGetOrderPublic.ts";
import {useNavigate, useParams} from "react-router-dom";
import {useEffect, useState} from "react";
import classes from './PaymentReturn.module.scss';
import {t} from "@lingui/macro";
import {useGetOrderStripePaymentIntentPublic} from "../../../../queries/useGetOrderStripePaymentIntentPublic.ts";

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
            }, 30000); //todo - this should be a env variable

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
            navigate(`/checkout/${eventId}/${orderShortId}/summary`);
        } else {
            // At this point we've tried multiple times to confirm the payment and failed.
            // This could be due to a network error on our end, or a problem with the payment provider (Stripe).
            // This should be a rare occurrence, but we should handle it gracefully.
            setCannotConfirmPayment(true);
        }
    }, [paymentIntentQuery.isFetched]);

    if (order?.payment_status === 'PAYMENT_FAILED' || window.location.search.includes('failed')) {
        navigate(`/checkout/${eventId}/${orderShortId}/payment?payment_failed=true`);
        return null;
    }

    if (order?.status === 'COMPLETED') {
        navigate(`/checkout/${eventId}/${orderShortId}/summary`);
        return null;
    }

    return (
        <div className={classes.container}>
            {!cannotConfirmPayment && (
                <>
                    {(!shouldPoll && paymentIntentQuery.isFetched) && t`We could not process your payment. Please try again or contact support.`}
                    {(!shouldPoll && !paymentIntentQuery.isFetched) && t`Almost there! We're just waiting for your payment to be processed. This should only take a few seconds..`}
                    {shouldPoll && t`We're processing your order. Please wait...`}
                </>
            )}

            {cannotConfirmPayment && t`We were unable to confirm your payment. Please try again or contact support.`}
        </div>
    );
}

export default PaymentReturn;