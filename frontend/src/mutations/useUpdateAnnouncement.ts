import {useMutation, useQueryClient} from "@tanstack/react-query";
import {announcementClient, UpsertAnnouncementData} from "../api/announcement.client";
import {GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY} from "../queries/useGetAdminAnnouncements";
import {IdParam} from "../types";

export const useUpdateAnnouncement = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({announcementId, data}: { announcementId: IdParam; data: UpsertAnnouncementData }) =>
            announcementClient.update(announcementId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY});
        },
    });
};
