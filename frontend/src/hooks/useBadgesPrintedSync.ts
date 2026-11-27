import {useEffect} from "react";
import {QueryClient, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {isSsr} from "../utilites/helpers.ts";
import {GET_BADGES_QUERY_KEY} from "../queries/useGetBadges.ts";
import {GET_BADGE_STATS_QUERY_KEY} from "../queries/useGetBadgeStats.ts";
import {GET_ATTENDEES_QUERY_KEY} from "../queries/useGetAttendees.ts";
import {GET_ATTENDEE_QUERY_KEY} from "../queries/useGetAttendee.ts";

const CHANNEL_NAME = 'hi-events-badges-printed';

const canBroadcast = () => !isSsr() && typeof BroadcastChannel !== 'undefined';

export const invalidateBadgeQueries = (queryClient: QueryClient, eventId: IdParam) => Promise.all([
    queryClient.invalidateQueries({queryKey: [GET_BADGES_QUERY_KEY, eventId]}),
    queryClient.invalidateQueries({queryKey: [GET_BADGE_STATS_QUERY_KEY, eventId]}),
    queryClient.invalidateQueries({queryKey: [GET_ATTENDEES_QUERY_KEY]}),
    queryClient.invalidateQueries({queryKey: [GET_ATTENDEE_QUERY_KEY]}),
]);

export const announceBadgesPrinted = (eventId: IdParam) => {
    if (!canBroadcast()) {
        return;
    }
    const channel = new BroadcastChannel(CHANNEL_NAME);
    channel.postMessage({eventId: String(eventId)});
    channel.close();
};

export const useBadgesPrintedSync = (eventId: IdParam) => {
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!canBroadcast() || !eventId) {
            return;
        }
        const channel = new BroadcastChannel(CHANNEL_NAME);
        channel.onmessage = (message: MessageEvent<{ eventId?: string }>) => {
            if (message.data?.eventId === String(eventId)) {
                invalidateBadgeQueries(queryClient, eventId);
            }
        };
        return () => channel.close();
    }, [eventId, queryClient]);
};
