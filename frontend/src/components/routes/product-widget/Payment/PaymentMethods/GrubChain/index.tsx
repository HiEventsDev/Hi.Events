import { useParams } from "react-router";
import { useCreateGrubchainPaymentData } from "../../../../../../queries/useCreateGrubchainPaymentData.ts";
import { useEffect, useState } from "react";
import { loadStripe, Stripe } from "@stripe/stripe-js";
import { useGetEventPublic } from "../../../../../../queries/useGetEventPublic.ts";
import { getConfig } from "../../../../../../utilites/config.ts";
import { CheckoutContent } from "../../../../../layouts/Checkout/CheckoutContent";
import { HomepageInfoMessage } from "../../../../../common/HomepageInfoMessage";
import { t } from "@lingui/macro";
import { eventHomepagePath } from "../../../../../../utilites/urlHelper.ts";
import { LoadingMask } from "../../../../../common/LoadingMask";
import { Elements } from "@stripe/react-stripe-js";
import GrubchainCheckoutForm from "../../../../../forms/GrubChainCheckoutForm"
import { Event } from "../../../../../../types.ts";
import { useGetOrderPublic } from "../../../../../../queries/useGetOrderPublic.ts";

interface GrubChainPaymentMethodProps {
  enabled: boolean;
  setSubmitHandler: (submitHandler: () => () => Promise<void>) => void;
}

export const GrubChainPaymentMethod = ({ enabled, setSubmitHandler }: GrubChainPaymentMethodProps) => {
  const { eventId, orderShortId } = useParams();
  const { orderData: order, isFetched: isOrderFetched } = useGetOrderPublic(eventId, orderShortId, ['event']);
  const {
    data: grubchainData,
    isFetched: isGrubchainFetched,
    error: grubchainPaymentIntentError
  } = useCreateGrubchainPaymentData(eventId, orderShortId);
  //const [grubchainPromise, setGrubchainPromise] = useState<Promise<null>>();
  const { data: event } = useGetEventPublic(eventId);

  useEffect(() => {
    console.log(grubchainData, "Grub Data", "isFetched: ", isGrubchainFetched, "error: ", grubchainPaymentIntentError)

    if (!grubchainData?.client_secret) {
      
      console.log("there is nothing in the grubchain data")
      //return;
    }
    console.log(grubchainData, "After Grub Data", "isFetched: ", isGrubchainFetched, "error: ", grubchainPaymentIntentError)

    const grubchainAccount = grubchainData?.business_id;
    const options = grubchainAccount ? {
      grubchainAccount: grubchainAccount
    } : {};

    //setGrubchainPromise(loadStripe(getConfig('GRUBCHAIN_APP_KEY') as string, options));
  }, [grubchainData]);

  if (!enabled) {
    return (
      <CheckoutContent>
        <HomepageInfoMessage
          message={t`Grubchain payments are not enabled for this event.`}
          link={eventHomepagePath(event as Event)}
          linkText={t`Return to event page`}
        />
      </CheckoutContent>
    );
  }

  if (grubchainPaymentIntentError && event) {
    return (
      <CheckoutContent>
        <HomepageInfoMessage
          /* @ts-ignore */
          message={grubchainPaymentIntentError.response?.data?.message || t`Sorry, something has gone wrong. Please restart the checkout process.`}
          link={eventHomepagePath(event)}
          linkText={t`Return to event page`}
        />
      </CheckoutContent>
    );
  }

  if (!isGrubchainFetched) {
    console.log("Grubchain is not fetched")
    // return <LoadingMask />;
  }

  //return (
  //<>
  //{(!grubchainPromise) && <LoadingMask />}

  //{(isGrubchainFetched && grubchainData?.client_secret && grubchainPromise) && (
  //<Elements options={{
  //clientSecret: grubchainData?.client_secret,
  //loader: 'always',
  //}} stripe={grubchainPromise}>
  //<GrubchainCheckoutForm setSubmitHandler={setSubmitHandler} />
  //</Elements>
  //)}
  //</>
  //);

  console.log("Grubchain component is loaded...")
  return (
    <>
      <GrubchainCheckoutForm setSubmitHandler={setSubmitHandler} />
    </>
  )
}
