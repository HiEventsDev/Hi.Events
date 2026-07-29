import {useQuery, useQueryClient} from "@tanstack/react-query";
import {useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {eventOccurrenceClient} from "../api/event-occurrence.client.ts";
import {GET_EVENT_OCCURRENCES_QUERY_KEY} from "../queries/useGetEventOccurrences.ts";
import {GET_EVENT_QUERY_KEY} from "../queries/useGetEvent.ts";
import {showError, showSuccess} from "../utilites/notifications.tsx";
import {IdParam} from "../types.ts";

export const useOccurrenceGenerationPolling = (eventId: IdParam) => {
    const queryClient = useQueryClient();
    const [jobUuid, setJobUuid] = useState<string | null>(null);
    const [totalCount, setTotalCount] = useState<number>(0);
    const handledRef = useRef<string | null>(null);

    const query = useQuery({
        queryKey: ["occurrenceGenerationStatus", eventId, jobUuid],
        queryFn: () => eventOccurrenceClient.getGenerationStatus(eventId, jobUuid as string),
        enabled: !!jobUuid,
        refetchInterval: (query) => {
            const status = query.state.data?.status;
            return status && status !== "IN_PROGRESS" ? false : 2000;
        },
    });

    const status = query.data?.status;

    useEffect(() => {
        if (!jobUuid || handledRef.current === jobUuid) {
            return;
        }

        if (status === "FINISHED") {
            handledRef.current = jobUuid;
            setJobUuid(null);
            showSuccess(t`Schedule created successfully`);
            queryClient.invalidateQueries({queryKey: [GET_EVENT_OCCURRENCES_QUERY_KEY]});
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY, eventId?.toString()]});
        } else if (status === "FAILED" || status === "NOT_FOUND") {
            handledRef.current = jobUuid;
            setJobUuid(null);
            showError(t`Failed to create schedule. Please try again.`);
        }
    }, [status, jobUuid]);

    const start = (newJobUuid: string, count: number) => {
        setTotalCount(count);
        setJobUuid(newJobUuid);
    };

    return {
        start,
        isGenerating: !!jobUuid,
        totalCount,
    };
};
