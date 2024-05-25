import {useQuery, UseQueryResult} from "@tanstack/react-query";
import {authClient} from "../api/auth.client.ts";
import {GenericDataResponse, User} from "../types.ts";
import {AxiosError} from "axios";

export const GET_INVITATION_QUERY_KEY = 'getInvitation';

export const useGetInvitation = (token: string): UseQueryResult<GenericDataResponse<User>, AxiosError> => {
    return useQuery(
        [GET_INVITATION_QUERY_KEY, token],
        async () => {
            return await authClient.getInvitation(token);
        },
        {
            retry: false,
        }
    );
}