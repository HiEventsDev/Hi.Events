import { useQuery } from "@tanstack/react-query";
import { IdParam } from "../types.ts";
import { eventsClient } from "../api/event.client.ts";

export const GET_EVENT_REPORT_QUERY_KEY = 'getEventReport';

export const useGetEventReport = (
    eventId: IdParam,
    reportType: IdParam,
    startDate?: Date | null,
    endDate?: Date | null
) => {
    return useQuery({
        queryKey: [
            GET_EVENT_REPORT_QUERY_KEY,
            eventId,
            reportType,
            startDate?.toISOString(),
            endDate?.toISOString()
        ],
        queryFn: async () => {
            const startDateString = startDate?.toISOString();
            const endDateString = endDate?.toISOString();

            return await eventsClient.getEventReport(
                eventId,
                reportType,
                startDateString,
                endDateString
            );
        },
        enabled: !!eventId && !!reportType && (!!startDate && !!endDate) || (!startDate && !endDate)
    });
};
