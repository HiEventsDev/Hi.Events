import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useCreateCrmContactNote = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, note}: {organizerId: IdParam, contactId: IdParam, note: string}) => crmClient.createContactNote(organizerId, contactId, note),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.contactNotes(variables.organizerId, variables.contactId)]),
    });
};
