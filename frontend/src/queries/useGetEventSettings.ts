import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsSettingsClient} from "../api/event-settings.client.ts";

export const GET_EVENT_SETTINGS_QUERY_KEY = 'getEventSettings';

export const useGetEventSettings = (eventId: IdParam) => {
    return useQuery(
        [GET_EVENT_SETTINGS_QUERY_KEY, eventId],
        async () => {
            const res = await eventsSettingsClient.all(eventId);
            return res.data;
        }
    )
};