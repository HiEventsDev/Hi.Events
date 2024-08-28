import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {taxAndFeeClient} from "../api/tax-and-fee.client.ts";
import {GET_TAXES_AND_FEES_QUERY_KEY} from "../queries/useGetTaxesAndFees.ts";

export const useDeleteTaxOrFee = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({accountId, taxAndFeeId}: {
            accountId: IdParam,
            taxAndFeeId: IdParam,
        }) => taxAndFeeClient.delete(accountId, taxAndFeeId),

        onSuccess: () => queryClient.invalidateQueries({
            queryKey: [GET_TAXES_AND_FEES_QUERY_KEY]
        })
    });
}
