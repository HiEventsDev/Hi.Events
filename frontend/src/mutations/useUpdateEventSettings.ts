import {useMutation, useQueryClient} from "@tanstack/react-query";
import {EventSettings, IdParam} from "../types.ts";
import {eventsSettingsClient} from "../api/event-settings.client.ts";
import {GET_EVENT_SETTINGS_QUERY_KEY} from "../queries/useGetEventSettings.ts";

export const useUpdateEventSettings = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventSettings, eventId}: {
            eventSettings: Partial<EventSettings>,
            eventId: IdParam,
        }) => eventsSettingsClient.partialUpdate(eventId, eventSettings),

        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: [GET_EVENT_SETTINGS_QUERY_KEY, variables.eventId]
            });
        }
    });
}
