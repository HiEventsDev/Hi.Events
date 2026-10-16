import {PublicWidget, widgetClientPublic} from "../api/widget.client.ts";

export const GET_WIDGET_PUBLIC_QUERY_KEY = 'getWidgetPublic';

export const getWidgetPublicQuery = (shortId: string) => ({
    queryKey: [GET_WIDGET_PUBLIC_QUERY_KEY, shortId] as const,
    queryFn: async (): Promise<PublicWidget> => {
        const {data} = await widgetClientPublic.get(shortId);
        return data;
    },
    staleTime: 5 * 60 * 1000,
    retry: false,
    refetchOnWindowFocus: false,
});
