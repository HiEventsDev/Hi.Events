import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";

export const GET_MESSAGING_TIERS_QUERY_KEY = 'GET_MESSAGING_TIERS';

export const useGetMessagingTiers = () => {
    return useQuery({
        queryKey: [GET_MESSAGING_TIERS_QUERY_KEY],
        queryFn: () => adminClient.getMessagingTiers(),
    });
};
