import {useQuery} from "@tanstack/react-query";
import {announcementClient, GetAdminAnnouncementsParams} from "../api/announcement.client";

export const GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY = ['admin', 'announcements'];

export const useGetAdminAnnouncements = (params: GetAdminAnnouncementsParams = {}) => {
    return useQuery({
        queryKey: [...GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY, params],
        queryFn: () => announcementClient.all(params),
    });
};
