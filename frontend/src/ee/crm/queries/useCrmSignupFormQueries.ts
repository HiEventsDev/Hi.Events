import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {crmKeys, crmPublicKeys} from "./crmQueryKeys.ts";

export const useGetCrmSignupForms = (organizerId: IdParam | undefined, enabled = true) => useQuery({
    queryKey: crmKeys.signupForms(organizerId),
    queryFn: async () => (await crmClient.signupForms(organizerId)).data,
    enabled: !!organizerId && enabled,
});

export const getSignupFormPublicQuery = (publicId: string | undefined) => ({
    queryKey: crmPublicKeys.signupForm(publicId),
    queryFn: async () => (await crmClient.signupFormPublic(publicId as string)).data,
    retry: false,
    staleTime: 60_000,
});

export const useGetSignupFormPublic = (publicId: string | undefined, enabled = true) => useQuery({
    ...getSignupFormPublicQuery(publicId),
    enabled: !!publicId && enabled,
});

export const getOrganizerSignupFormQuery = (organizerId: IdParam) => ({
    queryKey: crmPublicKeys.organizerSignupForm(organizerId),
    queryFn: async () => (await crmClient.organizerSignupForm(organizerId)).data,
    retry: false,
    staleTime: 60_000,
});

export const useGetOrganizerSignupForm = (organizerId: IdParam | undefined) => useQuery({
    ...getOrganizerSignupFormQuery(organizerId),
    enabled: !!organizerId,
});

export const getSubscriptionConfirmationQuery = (token: string | undefined) => ({
    queryKey: crmPublicKeys.subscriptionConfirmation(token),
    queryFn: async () => (await crmClient.subscriptionConfirmation(token as string)).data,
    retry: false,
});

export const useGetSubscriptionConfirmation = (token: string | undefined, enabled = true) => useQuery({
    ...getSubscriptionConfirmationQuery(token),
    enabled: !!token && enabled,
});
