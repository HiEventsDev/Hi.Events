import {useQuery} from "@tanstack/react-query";
import {widgetClient, WidgetOwner} from "../api/widget.client.ts";

export const GET_WIDGETS_QUERY_KEY = 'getWidgets';

export const widgetOwnerKey = (owner: WidgetOwner) => 'eventId' in owner
    ? ['event', String(owner.eventId)]
    : ['organizer', String(owner.organizerId)];

export const useGetWidgets = (owner: WidgetOwner) => useQuery({
    queryKey: [GET_WIDGETS_QUERY_KEY, ...widgetOwnerKey(owner)],
    queryFn: async () => (await widgetClient.all(owner)).data,
});
