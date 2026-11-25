import {useQuery} from "@tanstack/react-query";
import {organizerSubscriptionClient, SubscriberFilters} from "../api/organizer-subscription.client";
import {IdParam} from "../types";

export const ORGANIZER_SUBSCRIBERS_QUERY_KEY = 'organizerSubscribers';

export const useGetOrganizerSubscribers = (organizerId: IdParam, filters: SubscriberFilters, enabled: boolean) => useQuery({
    queryKey: [ORGANIZER_SUBSCRIBERS_QUERY_KEY, organizerId, filters],
    queryFn: () => organizerSubscriptionClient.list(organizerId, filters),
    enabled,
});
