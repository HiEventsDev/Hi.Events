import { useQuery } from "@tanstack/react-query";
import { IdParam } from "../types.ts";
import { organizerClient } from "../api/organizer.client.ts";

export const GET_ORGANIZER_REPORT_QUERY_KEY = 'getOrganizerReport';

export interface PaginatedReportResponse<T> {
    data: T[];
    pagination: {
        total: number;
        page: number;
        per_page: number;
        last_page: number;
    };
}

export const useGetOrganizerReport = (
    organizerId: IdParam,
    reportType: string,
    startDate?: Date | null,
    endDate?: Date | null,
    currency?: string | null,
    eventId?: IdParam | null,
    page: number = 1,
    perPage: number = 1000
) => {
    return useQuery({
        queryKey: [
            GET_ORGANIZER_REPORT_QUERY_KEY,
            organizerId,
            reportType,
            startDate?.toISOString(),
            endDate?.toISOString(),
            currency,
            eventId,
            page,
            perPage
        ],
        queryFn: async () => {
            const startDateString = startDate?.toISOString();
            const endDateString = endDate?.toISOString();

            const response = await organizerClient.getOrganizerReport(
                organizerId,
                reportType,
                startDateString,
                endDateString,
                currency,
                eventId,
                page,
                perPage
            );

            // Handle both paginated and non-paginated responses
            if (response.data && response.pagination) {
                return response as PaginatedReportResponse<any>;
            }
            // Legacy non-paginated response
            return {
                data: response.data || response,
                pagination: {
                    total: Array.isArray(response.data || response) ? (response.data || response).length : 0,
                    page: 1,
                    per_page: 1000,
                    last_page: 1,
                }
            } as PaginatedReportResponse<any>;
        },
        enabled: !!organizerId && !!reportType && ((!!startDate && !!endDate) || (!startDate && !endDate))
    });
};
