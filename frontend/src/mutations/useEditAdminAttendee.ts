import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, EditAdminAttendeeData} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ALL_ADMIN_ATTENDEES_QUERY_KEY} from "../queries/useGetAllAdminAttendees";

export const useEditAdminAttendee = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({attendeeId, data}: {attendeeId: IdParam; data: EditAdminAttendeeData}) =>
            adminClient.editAttendee(attendeeId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: [GET_ALL_ADMIN_ATTENDEES_QUERY_KEY]});
        },
    });
};
