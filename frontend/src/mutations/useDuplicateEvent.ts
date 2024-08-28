import {useMutation} from "@tanstack/react-query";
import {EventDuplicatePayload, IdParam} from "../types.ts";
import {eventsClient} from "../api/event.client.ts";

export const useDuplicateEvent = () => {
    return useMutation({
        mutationFn: ({eventId, duplicateData}: {
            eventId: IdParam;
            duplicateData: EventDuplicatePayload;
        }) => eventsClient.duplicate(eventId, duplicateData)
    });
}