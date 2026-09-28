import {useEffect, useState} from "react";
import {publicCashlessClient, CashlessPurchaseItem} from "../../../api/cashless-public.client.ts";
import {CashlessQuote} from "../../../types.ts";

interface QuoteInput {
    items?: CashlessPurchaseItem[];
    topupAmount?: number;
}

const QUOTE_DEBOUNCE_MS = 250;

export const useCashlessQuote = (
    salesPointShortId: string,
    sessionToken: string | null,
    {items, topupAmount}: QuoteInput,
) => {
    const [quote, setQuote] = useState<CashlessQuote | null>(null);
    const payloadKey = JSON.stringify({items, topupAmount});

    useEffect(() => {
        const hasBasket = (items?.length ?? 0) > 0;
        const hasTopup = (topupAmount ?? 0) > 0;

        if (!hasBasket && !hasTopup) {
            setQuote(null);
            return;
        }

        let cancelled = false;
        const timer = setTimeout(() => {
            publicCashlessClient
                .getQuote(
                    salesPointShortId,
                    hasTopup ? {topup_amount: topupAmount} : {items},
                    sessionToken,
                )
                .then(({data}) => {
                    if (!cancelled) setQuote(data);
                })
                .catch(() => {
                    if (!cancelled) setQuote(null);
                });
        }, QUOTE_DEBOUNCE_MS);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [payloadKey, salesPointShortId, sessionToken]);

    return quote;
};
