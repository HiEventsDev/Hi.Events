import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmSignupFormRequest, CrmSignupSubmission} from "../api/crm.client.ts";
import {crmKeys, crmPublicKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useCreateCrmSignupForm = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, form}: {organizerId: IdParam, form: CrmSignupFormRequest}) => crmClient.createSignupForm(organizerId, form),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.signupForms(variables.organizerId)]),
    });
};

export const useUpdateCrmSignupForm = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, formId, form}: {organizerId: IdParam, formId: IdParam, form: CrmSignupFormRequest}) => crmClient.updateSignupForm(organizerId, formId, form),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.signupForms(variables.organizerId)]),
    });
};

export const useDeleteCrmSignupForm = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, formId}: {organizerId: IdParam, formId: IdParam}) => crmClient.deleteSignupForm(organizerId, formId),
        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.signupForms(variables.organizerId)]),
    });
};

export const useSubmitSignupForm = () => useMutation({
    mutationFn: ({publicId, submission}: {publicId: string, submission: CrmSignupSubmission}) => crmClient.submitSignupForm(publicId, submission),
});

export const useConfirmSubscription = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (token: string) => crmClient.confirmSubscription(token),
        onSuccess: ({data}, token) => queryClient.setQueryData(crmPublicKeys.subscriptionConfirmation(token), data),
    });
};
