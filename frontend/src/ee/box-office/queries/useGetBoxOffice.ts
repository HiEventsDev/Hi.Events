import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {boxOfficeClient} from "../api/box-office.client.ts";

export const GET_BOX_OFFICE_QUERY_KEY = 'getBoxOffice';

export const useGetBoxOffice = (eventId: IdParam, boxOfficeId: IdParam) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_QUERY_KEY, eventId, boxOfficeId],
        queryFn: async () => {
            const {data} = await boxOfficeClient.get(eventId, boxOfficeId);
            return data;
        },
    });
};
