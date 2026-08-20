import {useMutation, useQueryClient} from "@tanstack/react-query";
import {announcementClient, UpsertAnnouncementData} from "../api/announcement.client";
import {GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY} from "../queries/useGetAdminAnnouncements";

export const useCreateAnnouncement = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpsertAnnouncementData) => announcementClient.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY});
        },
    });
};
