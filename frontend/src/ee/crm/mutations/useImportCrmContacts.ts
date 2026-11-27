import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys, audienceMembershipKeys} from "../queries/crmQueryKeys.ts";

export const useImportCrmContacts = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, file, consentAttested, tagName}: {
            organizerId: IdParam,
            file: File,
            consentAttested: boolean,
            tagName: string,
        }) => crmClient.importContacts(organizerId, file, consentAttested, tagName),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.tags(variables.organizerId), crmKeys.imports(variables.organizerId), ...audienceMembershipKeys(variables.organizerId)]),
    });
};
