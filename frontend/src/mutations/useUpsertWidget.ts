import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {UpsertWidgetRequest, widgetClient, WidgetOwner} from "../api/widget.client.ts";
import {GET_WIDGETS_QUERY_KEY, widgetOwnerKey} from "../queries/useGetWidgets.ts";
import {GET_WIDGET_PUBLIC_QUERY_KEY} from "../queries/useGetWidgetPublic.ts";

export const useUpsertWidget = (owner: WidgetOwner) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({widgetId, widget}: { widgetId?: IdParam; widget: UpsertWidgetRequest }) => widgetId
            ? widgetClient.update(owner, widgetId, widget)
            : widgetClient.create(owner, widget),
        onSuccess: (response) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_WIDGETS_QUERY_KEY, ...widgetOwnerKey(owner)]}),
            queryClient.invalidateQueries({queryKey: [GET_WIDGET_PUBLIC_QUERY_KEY, response.data.short_id]}),
        ]),
    });
};
