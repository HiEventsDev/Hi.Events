import {useQuery} from "@tanstack/react-query";
import {ContactAttributeDefinition, GenericPaginatedResponse, IdParam} from "../types.ts";
import {contactAttributeDefinitionClient} from "../api/contact-attribute-definition.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY = 'getContactAttributeDefinitions';

export const useGetContactAttributeDefinitions = () => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericPaginatedResponse<ContactAttributeDefinition>>({
        queryKey: [GET_CONTACT_ATTRIBUTE_DEFINITIONS_QUERY_KEY, accountId],
        enabled: meQuery.isFetched,
        queryFn: async () => await contactAttributeDefinitionClient.all(accountId),
    });
};
