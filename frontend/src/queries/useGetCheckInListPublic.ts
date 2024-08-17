import {useQuery} from '@tanstack/react-query';
import {publicCheckInClient} from "../api/check-in.client";
import {IdParam} from "../types.ts";

export const GET_CHECK_IN_LIST_PUBLIC_QUERY_KEY = 'getCheckInListPublic';

export const useGetCheckInListPublic = (checkInListShortId: IdParam) => {
    return useQuery(
        [GET_CHECK_IN_LIST_PUBLIC_QUERY_KEY, checkInListShortId],
        async () => {
            const data = await publicCheckInClient.getCheckInList(checkInListShortId);
            return data;
        },
        {
            retry: false,
        }
    );
};
