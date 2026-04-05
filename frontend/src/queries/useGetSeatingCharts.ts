import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {seatingChartClient} from "../api/seating-chart.client.ts";

export const GET_SEATING_CHARTS_QUERY_KEY = 'getSeatingCharts';

export const useGetSeatingCharts = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_SEATING_CHARTS_QUERY_KEY, eventId],
        queryFn: async () => {
            return await seatingChartClient.all(eventId);
        },
    });
};

export const GET_SEATING_CHART_QUERY_KEY = 'getSeatingChart';

export const useGetSeatingChart = (eventId: IdParam, chartId: IdParam) => {
    return useQuery({
        queryKey: [GET_SEATING_CHART_QUERY_KEY, eventId, chartId],
        queryFn: async () => {
            return await seatingChartClient.get(eventId, chartId);
        },
        enabled: !!chartId,
    });
};
