import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerSettingsClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_SETTINGS_QUERY_KEY = 'getOrganizerSettings';

export const useGetOrganizerSettings = (organizerId: IdParam) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_SETTINGS_QUERY_KEY, organizerId],

        queryFn: async () => {
            const res = await organizerSettingsClient.all(organizerId);
            return res.data;
        }
    });
};
