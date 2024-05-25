import {useMutation} from "@tanstack/react-query";
import {IdParam, TaxAndFee} from "../types.ts";
import {useQueryClient} from "@tanstack/react-query";
import {taxAndFeeClient} from "../api/tax-and-fee.client.ts";
import {GET_TAXES_AND_FEES_QUERY_KEY} from "../queries/useGetTaxesAndFees.ts";
import {useGetMe} from "../queries/useGetMe.ts";

export const useUpdateTaxOrFee = () => {
    const queryClient = useQueryClient();
    const {data: me} = useGetMe();

    return useMutation(
        ({taxOrFeeData, taxOrFeeId}: {
            taxOrFeeId: IdParam,
            taxOrFeeData: TaxAndFee
        }) => taxAndFeeClient.update(me?.account_id, taxOrFeeId, taxOrFeeData),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_TAXES_AND_FEES_QUERY_KEY]}),
        }
    )
}