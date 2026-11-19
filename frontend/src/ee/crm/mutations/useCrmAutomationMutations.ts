import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmAutomationRequest} from "../api/crm.client.ts";
import {crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useCreateCrmAutomation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, automation}: {organizerId: IdParam, automation: CrmAutomationRequest}) => crmClient.createAutomation(organizerId, automation),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.automations(variables.organizerId)]),
    });
};

export const useUpdateCrmAutomation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, automationId, automation}: {organizerId: IdParam, automationId: IdParam, automation: CrmAutomationRequest}) =>
            crmClient.updateAutomation(organizerId, automationId, automation),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.automations(variables.organizerId)]),
    });
};

export const useSetCrmAutomationEnabled = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, automationId, isEnabled}: {organizerId: IdParam, automationId: IdParam, isEnabled: boolean}) =>
            crmClient.setAutomationEnabled(organizerId, automationId, isEnabled),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.automations(variables.organizerId), crmKeys.segments(variables.organizerId)]),
    });
};

export const useDeleteCrmAutomation = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, automationId}: {organizerId: IdParam, automationId: IdParam}) => crmClient.deleteAutomation(organizerId, automationId),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.automations(variables.organizerId), crmKeys.segments(variables.organizerId)]),
    });
};
