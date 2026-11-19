import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useDeleteCrmContactNote = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, noteId}: {organizerId: IdParam, contactId: IdParam, noteId: IdParam}) => crmClient.deleteContactNote(organizerId, contactId, noteId),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.contactNotes(variables.organizerId, variables.contactId)]),
    });
};
