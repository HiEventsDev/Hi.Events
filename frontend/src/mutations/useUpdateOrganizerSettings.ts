import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, OrganizerSettings} from "../types.ts";
import {GET_ORGANIZER_SETTINGS_QUERY_KEY} from "../queries/useGetOrganizerSettings.ts";
import {organizerSettingsClient} from "../api/organizer.client.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";

export const useUpdateOrganizerSettings = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerSettings, organizerId}: {
            organizerSettings: Partial<OrganizerSettings>,
            organizerId: IdParam,
        }) => organizerSettingsClient.partialUpdate(organizerId, organizerSettings),

        onSuccess: (_, variables) => Promise.all([
            queryClient.invalidateQueries({
                queryKey: [GET_ORGANIZER_SETTINGS_QUERY_KEY, variables.organizerId]
            }),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY]}),
        ])
    });
}
