import {useMutation, useQueryClient} from "@tanstack/react-query";
import {announcementClient} from "../api/announcement.client";
import {GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY} from "../queries/useGetAdminAnnouncements";
import {IdParam} from "../types";

export const useDeleteAnnouncement = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (announcementId: IdParam) => announcementClient.delete(announcementId),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ANNOUNCEMENTS_QUERY_KEY});
        },
    });
};
