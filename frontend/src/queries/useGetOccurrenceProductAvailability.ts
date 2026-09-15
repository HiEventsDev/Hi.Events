import {useQuery} from "@tanstack/react-query";
import {IdParam, OccurrenceProductAvailability} from "../types.ts";
import {AxiosError} from "axios";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const GET_OCCURRENCE_PRODUCT_AVAILABILITY_QUERY_KEY = 'getOccurrenceProductAvailability';

export const useGetOccurrenceProductAvailability = (eventId: IdParam, occurrenceId: IdParam) => {
    return useQuery<OccurrenceProductAvailability[], AxiosError>({
        queryKey: [GET_OCCURRENCE_PRODUCT_AVAILABILITY_QUERY_KEY, eventId, occurrenceId],
        queryFn: async () => {
            const {data} = await eventOccurrenceClient.getProductAvailability(eventId, occurrenceId);
            return data;
        },
        enabled: !!occurrenceId,
    });
};
