import {useQuery} from "@tanstack/react-query";
import {Contact, GenericDataResponse, IdParam} from "../types.ts";
import {contactClient} from "../api/contact.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_CONTACT_QUERY_KEY = 'getContact';

export const useGetContact = (contactId: IdParam) => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericDataResponse<Contact>>({
        queryKey: [GET_CONTACT_QUERY_KEY, accountId, contactId],
        enabled: meQuery.isFetched && !!contactId,
        queryFn: async () => await contactClient.get(accountId, contactId),
    });
};
