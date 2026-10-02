import {useQuery} from "@tanstack/react-query";
import {licenceAdminClient} from "../api/licence.client.ts";

export const GET_ADMIN_LICENCE_QUERY_KEY = ['admin', 'licence'];

export const useGetAdminLicence = () => {
    return useQuery({
        queryKey: GET_ADMIN_LICENCE_QUERY_KEY,
        queryFn: async () => (await licenceAdminClient.getLicence()).data,
    });
};
