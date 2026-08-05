import {useMutation, useQueryClient} from "@tanstack/react-query";
import {Announcement, announcementClient} from "../api/announcement.client";
import {GET_ACTIVE_ANNOUNCEMENTS_QUERY_KEY} from "../queries/useGetActiveAnnouncements";
import {IdParam} from "../types";

export const useDismissAnnouncement = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (announcementId: IdParam) => announcementClient.dismiss(announcementId),
        onMutate: async (announcementId: IdParam) => {
            await queryClient.cancelQueries({queryKey: GET_ACTIVE_ANNOUNCEMENTS_QUERY_KEY});
            queryClient.setQueryData<Announcement[]>(
                GET_ACTIVE_ANNOUNCEMENTS_QUERY_KEY,
                (announcements) => announcements?.filter((announcement) => announcement.id !== announcementId),
            );
        },
        onError: () => {
            queryClient.invalidateQueries({queryKey: GET_ACTIVE_ANNOUNCEMENTS_QUERY_KEY});
        },
    });
};
