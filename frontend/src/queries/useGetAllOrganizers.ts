import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllOrganizersParams} from "../api/admin.client";

export const GET_ALL_ORGANIZERS_QUERY_KEY = 'getAllOrganizers';

export const useGetAllOrganizers = (params: GetAllOrganizersParams = {}) => {
    return useQuery({
        queryKey: [GET_ALL_ORGANIZERS_QUERY_KEY, params],
        queryFn: async () => await adminClient.getAllOrganizers(params),
    });
};
