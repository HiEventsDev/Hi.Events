import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";

export const useGetUpcomingEvents = (perPage: number = 10) => {
    return useQuery({
        queryKey: ['admin', 'events', 'upcoming', perPage],
        queryFn: () => adminClient.getUpcomingEvents(perPage),
    });
};
