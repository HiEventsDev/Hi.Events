import {useQuery} from "@tanstack/react-query";
import {announcementClient} from "../api/announcement.client";
import {useGetMe} from "./useGetMe";

export const GET_ACTIVE_ANNOUNCEMENTS_QUERY_KEY = ['announcements', 'active'];

export const useGetActiveAnnouncements = (enabled: boolean) => {
    const {data: me} = useGetMe();

    return useQuery({
        queryKey: GET_ACTIVE_ANNOUNCEMENTS_QUERY_KEY,
        queryFn: async () => {
            const {data} = await announcementClient.active();
            return data;
        },
        enabled: enabled && !!me && !me.is_impersonating,
        staleTime: 5 * 60 * 1000,
        refetchOnWindowFocus: false,
        retry: false,
    });
};
