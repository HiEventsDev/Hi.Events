import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllEmailSuppressionsParams} from "../api/admin.client";

export const GET_EMAIL_SUPPRESSIONS_QUERY_KEY = ['admin', 'email-suppressions'];

export const useGetEmailSuppressions = (params: GetAllEmailSuppressionsParams = {}) => {
    return useQuery({
        queryKey: [...GET_EMAIL_SUPPRESSIONS_QUERY_KEY, params],
        queryFn: () => adminClient.getAllEmailSuppressions(params),
    });
};
