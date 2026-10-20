import {useInfiniteQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {IdParam} from "../types.ts";
import {organizerPublicClient} from "../api/organizer.client.ts";
import {organizerWidgetEventsFilters} from "../utilites/widgetEvents.ts";
import {WidgetSettings} from "../utilites/widgetConfig.ts";

export type OrganizerWidgetEventsStatus = 'upcoming' | 'ended';

export const organizerWidgetEventsQuery = (organizerId: IdParam, config: WidgetSettings, status: OrganizerWidgetEventsStatus) => ({
    queryKey: ['getOrganizerWidgetEvents', organizerId, status, config.limit ?? null, config.event_category ?? null] as const,
    queryFn: ({pageParam}: { pageParam: number }) =>
        organizerPublicClient.getEvents(organizerId, organizerWidgetEventsFilters(config, status, pageParam)),
    initialPageParam: 1,
    getNextPageParam: (lastPage: Awaited<ReturnType<typeof organizerPublicClient.getEvents>>) =>
        lastPage.meta.current_page < lastPage.meta.last_page ? lastPage.meta.current_page + 1 : undefined,
    staleTime: 60 * 1000,
    refetchOnWindowFocus: false,
});

export const useGetOrganizerWidgetEvents = (
    organizerId: IdParam,
    config: WidgetSettings,
    status: OrganizerWidgetEventsStatus,
    enabled: boolean,
) => useInfiniteQuery({
    ...organizerWidgetEventsQuery(organizerId, config, status),
    enabled,
    retry: (failureCount: number, error: AxiosError) => error?.response?.status !== 404 && failureCount < 3,
});
