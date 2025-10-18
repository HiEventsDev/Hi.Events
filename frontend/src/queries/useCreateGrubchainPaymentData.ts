import { useQuery } from "@tanstack/react-query";
import { orderClientPublic } from "../api/order.client.ts";
import { IdParam } from "../types.ts";
import { getSessionIdentifier } from "../utilites/sessionIdentifier.ts";

export const useCreateGrubchainPaymentData = async (
  eventId: IdParam,
  orderShortId: IdParam,
) => {
  const { data } = await orderClientPublic.createGrubchainPaymentIntent(
    Number(eventId),
    String(orderShortId),
  );
  console.log(data);
  return { data, isFetched: true, error: {} };
};
