import {useEffect} from "react";
import {useQuery, useQueryClient} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const GET_BOX_OFFICE_SEAT_MAP_QUERY_KEY = 'getBoxOfficeSeatMap';
export const GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY = 'getBoxOfficeOccupiedSeats';

const POLL_INTERVAL_MS = 15_000;
const SALE_IN_PROGRESS_POLL_INTERVAL_MS = 5_000;

export const useGetBoxOfficeSeatMap = (boxOfficeShortId: IdParam) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_SEAT_MAP_QUERY_KEY, boxOfficeShortId],
        retry: false,
        queryFn: async () => {
            try {
                const {data} = await publicBoxOfficeClient.getSeatMap(boxOfficeShortId);
                return data;
            } catch (error) {
                if ((error as AxiosError).response?.status === 404) {
                    return null;
                }
                throw error;
            }
        },
    });
};

export const useGetBoxOfficeOccupiedSeats = (
    boxOfficeShortId: IdParam,
    occurrenceId: number | null,
    seatMapVersion: number | undefined,
    isSelling: boolean,
) => {
    const queryClient = useQueryClient();
    const query = useQuery({
        queryKey: [GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY, boxOfficeShortId, occurrenceId],
        enabled: seatMapVersion !== undefined && occurrenceId !== null,
        refetchInterval: isSelling ? SALE_IN_PROGRESS_POLL_INTERVAL_MS : POLL_INTERVAL_MS,
        queryFn: async () => {
            const {data, meta} = await publicBoxOfficeClient.getOccupiedSeats(boxOfficeShortId);
            return {seats: data, version: meta?.version};
        },
    });

    const availableVersion = query.data?.version;

    useEffect(() => {
        if (availableVersion !== undefined && seatMapVersion !== undefined && availableVersion !== seatMapVersion) {
            queryClient.invalidateQueries({queryKey: [GET_BOX_OFFICE_SEAT_MAP_QUERY_KEY, boxOfficeShortId]});
        }
    }, [availableVersion, seatMapVersion, boxOfficeShortId, queryClient]);

    return query;
};
