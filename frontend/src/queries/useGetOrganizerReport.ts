import { useQuery } from "@tanstack/react-query";
import { IdParam } from "../types.ts";
import { organizerClient } from "../api/organizer.client.ts";

export const GET_ORGANIZER_REPORT_QUERY_KEY = 'getOrganizerReport';

export const useGetOrganizerReport = (
    organizerId: IdParam,
    reportType: string,
    startDate?: Date | null,
    endDate?: Date | null,
    currency?: string | null
) => {
    return useQuery({
        queryKey: [
            GET_ORGANIZER_REPORT_QUERY_KEY,
            organizerId,
            reportType,
            startDate?.toISOString(),
            endDate?.toISOString(),
            currency
        ],
        queryFn: async () => {
            const startDateString = startDate?.toISOString();
            const endDateString = endDate?.toISOString();

            const {data} = await organizerClient.getOrganizerReport(
                organizerId,
                reportType,
                startDateString,
                endDateString,
                currency
            );
            return data;
        },
        enabled: !!organizerId && !!reportType && ((!!startDate && !!endDate) || (!startDate && !endDate))
    });
};
