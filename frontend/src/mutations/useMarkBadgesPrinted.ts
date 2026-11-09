import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {badgeClient} from "../api/badge.client.ts";
import {announceBadgesPrinted, invalidateBadgeQueries} from "../hooks/useBadgesPrintedSync.ts";

export const useMarkBadgesPrinted = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, attendeeIds}: { eventId: IdParam, attendeeIds: IdParam[] }) => badgeClient.markPrinted(eventId, attendeeIds),
        onSuccess: (_, variables) => {
            announceBadgesPrinted(variables.eventId);
            return invalidateBadgeQueries(queryClient, variables.eventId);
        },
    });
};
