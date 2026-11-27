import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmCampaignTemplateRequest} from "../api/crm.client.ts";
import {crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useCreateCrmCampaignTemplate = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, template}: {organizerId: IdParam, template: CrmCampaignTemplateRequest}) => crmClient.createCampaignTemplate(organizerId, template),
        onSuccess: (_, {organizerId}) => invalidateCrm(queryClient, [crmKeys.campaignTemplates(organizerId)]),
    });
};

export const useUpdateCrmCampaignTemplate = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, templateId, template}: {organizerId: IdParam, templateId: IdParam, template: CrmCampaignTemplateRequest}) =>
            crmClient.updateCampaignTemplate(organizerId, templateId, template),
        onSuccess: (_, {organizerId}) => invalidateCrm(queryClient, [crmKeys.campaignTemplates(organizerId)]),
    });
};

export const useDeleteCrmCampaignTemplate = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, templateId}: {organizerId: IdParam, templateId: IdParam}) => crmClient.deleteCampaignTemplate(organizerId, templateId),
        onSuccess: (_, {organizerId}) => invalidateCrm(queryClient, [crmKeys.campaignTemplates(organizerId)]),
    });
};

export const useDuplicateCrmCampaign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaignId}: {organizerId: IdParam, campaignId: IdParam}) => crmClient.duplicateCampaign(organizerId, campaignId),
        onSuccess: ({data}, {organizerId}) => {
            queryClient.setQueryData(crmKeys.campaign(organizerId, data.id), data);
            return invalidateCrm(queryClient, [crmKeys.campaignLists(organizerId)]);
        },
    });
};

export const useEndCrmEarlyAccess = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, campaignId}: {organizerId: IdParam, campaignId: IdParam}) => crmClient.endEarlyAccess(organizerId, campaignId),
        onSuccess: ({data}, {organizerId, campaignId}) => queryClient.setQueryData(crmKeys.campaign(organizerId, campaignId), data),
    });
};
