import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {widgetClient, WidgetOwner} from "../api/widget.client.ts";
import {GET_WIDGETS_QUERY_KEY, widgetOwnerKey} from "../queries/useGetWidgets.ts";

export const useDeleteWidget = (owner: WidgetOwner) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (widgetId: IdParam) => widgetClient.delete(owner, widgetId),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_WIDGETS_QUERY_KEY, ...widgetOwnerKey(owner)]}),
    });
};
