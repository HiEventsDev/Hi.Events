import {useQuery, UseQueryResult} from "@tanstack/react-query";
import {authClient} from "../api/auth.client.ts";
import {GenericDataResponse, User} from "../types.ts";
import {AxiosError} from "axios";

export const GET_INVITATION_QUERY_KEY = 'getInvitation';

export const useGetInvitation = (token: string): UseQueryResult<GenericDataResponse<User>, AxiosError> => {
    return useQuery({
        queryKey: [GET_INVITATION_QUERY_KEY, token],

        queryFn: async () => {
            return await authClient.getInvitation(token);
        },

        retry: false
    });
}