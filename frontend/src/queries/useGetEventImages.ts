import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const GET_EVENT_IMAGES_QUERY_KEY = 'getEventImages';

export const useGetEventImages = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_IMAGES_QUERY_KEY, eventId],

        queryFn: async () => {
            const res = await eventsClient.getEventImages(eventId);
            return res.data;
        }
    });
};