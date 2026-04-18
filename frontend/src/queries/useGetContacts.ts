import {useQuery} from "@tanstack/react-query";
import {Contact, GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {contactClient} from "../api/contact.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_CONTACTS_QUERY_KEY = 'getContacts';

export const useGetContacts = (pagination: QueryFilters) => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericPaginatedResponse<Contact>>({
        queryKey: [GET_CONTACTS_QUERY_KEY, accountId, pagination],
        enabled: meQuery.isFetched,
        queryFn: async () => await contactClient.all(accountId, pagination),
    });
};
