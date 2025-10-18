import { useEffect, useState } from "react";
import { InputGroup } from "../../common/InputGroup";
import { TextInput } from "@mantine/core";
import { useParams } from "react-router";
import * as stripeJs from "@stripe/stripe-js";
import { t } from "@lingui/macro";
import { LoadingMask } from "../../common/LoadingMask";
import { useGetOrderPublic } from "../../../queries/useGetOrderPublic.ts";
import { Card } from "../../common/Card";
import { CheckoutContent } from "../../layouts/Checkout/CheckoutContent";
import { HomepageInfoMessage } from "../../common/HomepageInfoMessage";
import { eventCheckoutPath, eventHomepagePath } from "../../../utilites/urlHelper.ts";
import { Event } from "../../../types.ts";

export default function GrubChainCheckoutForm({ setSubmitHandler }: {
  setSubmitHandler: (submitHandler: () => () => Promise<void>) => void
}) {
  const { eventId, orderShortId } = useParams();
  //const stripe = useStripe();
  //const elements = useElements();
  const [message, setMessage] = useState<string | undefined>('');
  const { data: order, isFetched: isOrderFetched } = useGetOrderPublic(eventId, orderShortId, ['event']);
  const event = order?.event;

  const handleSubmit = async () => {
    //if (!stripe || !elements) {
    //return;
    //}

    //const { error } = await stripe.confirmPayment({
    //elements,
    //confirmParams: {
    //return_url: window?.location.origin + `/checkout/${eventId}/${orderShortId}/payment_return`
    //},
    //});

    //if (error?.type === "card_error" || error?.type === "validation_error") {
    //setMessage(error.message);
    //} else {
    //setMessage(t`An unexpected error occurred.`);
    //}
  };

  useEffect(() => {
    //if (!stripe) {
    //return;
    //}

    //const clientSecret = new URLSearchParams(window?.location.search).get(
    //"payment_intent_client_secret"
    //);

    //if (!clientSecret) {
    //return;
    //}

    //stripe.retrievePaymentIntent(clientSecret).then(({ paymentIntent }) => {
    //switch (paymentIntent?.status) {
    //case "succeeded":
    //setMessage(t`Payment succeeded!`);
    //break;
    //case "processing":
    //setMessage(t`Your payment is processing.`);
    //break;
    //case "requires_payment_method":
    //setMessage(t`Your payment was not successful, please try again.`);
    //break;
    //default:
    //setMessage(t`Something went wrong.`);
    //break;
    //}
    //});
  }, []);

  //useEffect(() => {
  //if (setSubmitHandler) {
  //setSubmitHandler(() => handleSubmit);
  //}

  //}, [setSubmitHandler, stripe, elements]);

  //if (!isOrderFetched || !order?.payment_status) {
  //return (
  //<CheckoutContent>
  //<Skeleton height={300} mb={20} />
  //</CheckoutContent>
  //);
  //}

  //if (order?.payment_status === 'PAYMENT_RECEIVED') {
  //return (
  //<HomepageInfoMessage
  //message={t`This order has already been paid.`}
  //linkText={t`View order details`}
  //link={eventCheckoutPath(eventId, orderShortId, 'summary')}
  ///>
  //);
  //}

  //if (order?.payment_status !== 'AWAITING_PAYMENT' && order?.payment_status !== 'PAYMENT_FAILED') {
  //return (
  //<HomepageInfoMessage
  //message={t`This order page is no longer available.`}
  //linkText={t`View order details`}
  //link={eventHomepagePath(event as Event)}
  ///>
  //);
  //}

  const paymentElementOptions: stripeJs.StripePaymentElementOptions = {
    layout: {
      type: "accordion",
      defaultCollapsed: false,
      radios: true,
      spacedAccordionItems: true,
    },
  };

  //{
  //(order?.payment_status === 'PAYMENT_FAILED' || window?.location.search.includes('payment_failed')) && (
  //<Alert mb={20} color={'red'}>{t`Your payment was unsuccessful. Please try again.`}</Alert>
  //)
  //}
  //<PaymentElement
  //className={classes.stripeForElement}
  //id="payment-element"
  //options={paymentElementOptions}
  ///>

  return (
    <form id="payment-form">
      <h2>
        {t`Payment`}
      </h2>

      <LoadingMask />
      <Card>
        <InputGroup>
          <TextInput
            withAsterisk
            label={t`Card Number`}
            placeholder={t`Card Number`}
          />
          <TextInput
            withAsterisk
            label={t`Expiry Date`}
            placeholder={t`Expiry Date`}
          />
        </InputGroup>

        <TextInput
          withAsterisk
          label={t`CVV`}
          placeholder={t`CVV`}
        />

      </Card>
    </form >
  );
}
