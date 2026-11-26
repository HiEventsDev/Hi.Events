import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useStartCrmSync = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId}: {organizerId: IdParam}) => crmClient.startSync(organizerId),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.sync(variables.organizerId)]),
    });
};
