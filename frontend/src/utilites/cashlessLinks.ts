import {IdParam} from "../types.ts";

interface CashlessTransactionsFilter {
    walletId?: IdParam;
    salesPointId?: IdParam;
}

export const cashlessTransactionsPath = (eventId: IdParam, {walletId, salesPointId}: CashlessTransactionsFilter): string => {
    const params = new URLSearchParams();

    if (walletId !== undefined) {
        params.set('filterFields[cashless_wallet_id][eq]', String(walletId));
    }

    if (salesPointId !== undefined) {
        params.set('filterFields[cashless_sales_point_id][eq]', String(salesPointId));
    }

    return `/manage/event/${eventId}/cashless/transactions?${params.toString()}`;
};
