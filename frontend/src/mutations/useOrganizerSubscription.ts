import {useMutation, useQueryClient} from "@tanstack/react-query";
import {organizerSubscriptionClient, SubscriberFilters} from "../api/organizer-subscription.client";
import {ORGANIZER_SUBSCRIBERS_QUERY_KEY} from "../queries/useGetOrganizerSubscribers";
import {IdParam} from "../types";

export const useSubscribeToOrganizer = (organizerId: IdParam) => useMutation({
    mutationFn: ({email, locale}: {email: string; locale: string}) => organizerSubscriptionClient.subscribe(organizerId, email, locale),
});

export const useOrganizerSubscriptionToken = (organizerId: IdParam, token: string, confirm: boolean) => useMutation({
    mutationFn: () => confirm ? organizerSubscriptionClient.confirm(organizerId, token) : organizerSubscriptionClient.unsubscribe(organizerId, token),
});

export const useUnsubscribeOrganizerSubscribers = (organizerId: IdParam) => {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (ids: number[]) => organizerSubscriptionClient.unsubscribeMany(organizerId, ids),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [ORGANIZER_SUBSCRIBERS_QUERY_KEY, organizerId]}),
    });
};

export const useExportOrganizerSubscribers = (organizerId: IdParam) => useMutation({
    mutationFn: (filters: SubscriberFilters) => organizerSubscriptionClient.export(organizerId, filters),
});
