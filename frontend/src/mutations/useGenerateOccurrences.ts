import {useMutation} from "@tanstack/react-query";
import {GenerateOccurrencesRequest, IdParam} from "../types.ts";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";

export const useGenerateOccurrences = () => {
    return useMutation({
        mutationFn: ({eventId, data}: {
            eventId: IdParam,
            data: GenerateOccurrencesRequest,
        }) => eventOccurrenceClient.generate(eventId, data),
    });
};
