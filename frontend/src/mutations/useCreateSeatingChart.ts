import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {seatingChartClient, SeatingChartRequest} from "../api/seating-chart.client.ts";
import {GET_SEATING_CHARTS_QUERY_KEY} from "../queries/useGetSeatingCharts.ts";

export const useCreateSeatingChart = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, data}: {
            eventId: IdParam;
            data: SeatingChartRequest;
        }) => seatingChartClient.create(eventId, data),
        onSuccess: (_, variables) => queryClient
            .invalidateQueries({queryKey: [GET_SEATING_CHARTS_QUERY_KEY, variables.eventId]}),
    });
};
