import {useQuery} from "@tanstack/react-query";
import {publicApi} from "../api/public-client.ts";
import {ColorTheme} from "../types.ts";

export const GET_COLOR_THEMES_QUERY_KEY = 'GET_COLOR_THEMES';

export const useGetColorThemes = () => {
    return useQuery<ColorTheme[]>({
        queryKey: [GET_COLOR_THEMES_QUERY_KEY],
        queryFn: async () => {
            const response = await publicApi.get(`/color-themes`);
            return response.data.data;
        },
        staleTime: 1000 * 60 * 60,
    });
};
