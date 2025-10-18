import React, { useState } from "react";
import { useNavigate, useParams } from "react-router";
import { useGetEventPublic } from "../../../../queries/useGetEventPublic.ts";
import { CheckoutContent } from "../../../layouts/Checkout/CheckoutContent";
import { StripePaymentMethod } from "./PaymentMethods/Stripe";
import { GrubChainPaymentMethod } from "./PaymentMethods/GrubChain";
import { OfflinePaymentMethod } from "./PaymentMethods/Offline";
import { Event, Order } from "../../../../types.ts";
import { CheckoutFooter } from "../../../layouts/Checkout/CheckoutFooter";
import { Group } from "@mantine/core";
import { formatCurrency } from "../../../../utilites/currency.ts";
import { t } from "@lingui/macro";
import { useGetOrderPublic } from "../../../../queries/useGetOrderPublic.ts";
import {
  useTransitionOrderToOfflinePaymentPublic
} from "../../../../mutations/useTransitionOrderToOfflinePaymentPublic.ts";
import { Card } from "../../../common/Card";
import { showError } from "../../../../utilites/notifications.tsx";

const Payment = () => {
  const navigate = useNavigate();
  const { eventId, orderShortId } = useParams();
  const { data: event, isFetched: isEventFetched } = useGetEventPublic(eventId);
  const { data: order, isFetched: isOrderFetched } = useGetOrderPublic(eventId, orderShortId, ['event']);
  const isLoading = !isOrderFetched;
  const [isPaymentLoading, setIsPaymentLoading] = useState(false);
  const [activePaymentMethod, setActivePaymentMethod] = useState<'GRUBCHAIN' | 'STRIPE' | 'OFFLINE' | null>(null);
  const [submitHandler, setSubmitHandler] = useState<(() => Promise<void>) | null>(null);
  const transitionOrderToOfflinePaymentMutation = useTransitionOrderToOfflinePaymentPublic();

  const isStripeEnabled = event?.settings?.payment_providers?.includes('STRIPE');
  const isGrubchainEnabled = event?.settings?.payment_providers?.includes('GRUBCHAIN');
  const isOfflineEnabled = event?.settings?.payment_providers?.includes('OFFLINE');

  React.useEffect(() => {
    // Automatically set the first available payment method
    if (isGrubchainEnabled) {
      setActivePaymentMethod('GRUBCHAIN');
    } else if (isStripeEnabled) {
      setActivePaymentMethod('STRIPE');
    } else if (isOfflineEnabled) {
      setActivePaymentMethod('OFFLINE');
    } else {
      setActivePaymentMethod(null); // No methods available
    }
  }, [isStripeEnabled, isGrubchainEnabled, isOfflineEnabled]);

  const handleParentSubmit = () => {
    if (submitHandler) {
      setIsPaymentLoading(true);
      submitHandler().finally(() => setIsPaymentLoading(false));
    }
  };

  const handleSubmit = async () => {
    if (activePaymentMethod === 'STRIPE') {
      handleParentSubmit();
    } else if (activePaymentMethod === 'GRUBCHAIN') {
      handleParentSubmit();
    } else if (activePaymentMethod === 'OFFLINE') {
      setIsPaymentLoading(true);

      await transitionOrderToOfflinePaymentMutation.mutateAsync({
        eventId,
        orderShortId
      }, {
        onSuccess: () => {
          navigate(`/checkout/${eventId}/${orderShortId}/summary`);
        },
        onError: (error: any) => {
          setIsPaymentLoading(false);
          showError(error.response?.data?.message || t`Offline payment failed. Please try again or contact the event organizer.`);
        }
      });
    }
  };

  if (!isStripeEnabled && !isOfflineEnabled && !isGrubchainEnabled && isOrderFetched && isEventFetched) {
    return (
      <CheckoutContent>
        <Card>
          {t`No payment methods are currently available. Please contact the event organizer for assistance.`}
        </Card>
      </CheckoutContent>
    );
  }

  return (
    <>
      <CheckoutContent>
        {isGrubchainEnabled && (
          <div style={{ display: activePaymentMethod === 'GRUBCHAIN' ? 'block' : 'none' }}>
            <GrubChainPaymentMethod enabled={true} setSubmitHandler={setSubmitHandler} />
          </div>
        )}


      </CheckoutContent>

      <CheckoutFooter
        event={event as Event}
        order={order as Order}
        isLoading={isLoading || isPaymentLoading}
        onClick={handleSubmit}
        buttonContent={order?.is_payment_required ? (
          <Group gap={'10px'}>
            <div style={{ fontWeight: "bold" }}>
              {t`Place Order`}
            </div>
            <div style={{ fontSize: 14 }}>
              {formatCurrency(order.total_gross, order.currency)}
            </div>
            <div style={{ fontSize: 14, fontWeight: 500 }}>
              {order.currency}
            </div>
          </Group>
        ) : t`Complete Payment`}
      />
    </>
  );
}

export default Payment;
