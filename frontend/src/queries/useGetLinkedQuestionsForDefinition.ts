import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {contactAttributeDefinitionClient, LinkedQuestionRow} from "../api/contact-attribute-definition.client.ts";
import {useGetMe} from "./useGetMe.ts";

export const GET_LINKED_QUESTIONS_FOR_DEFINITION_QUERY_KEY = 'getLinkedQuestionsForDefinition';

export const useGetLinkedQuestionsForDefinition = (
    definitionId: IdParam | undefined,
    params: QueryFilters,
    enabled: boolean,
) => {
    const meQuery = useGetMe();
    const accountId = meQuery.data?.account_id as IdParam;

    return useQuery<GenericPaginatedResponse<LinkedQuestionRow>>({
        queryKey: [GET_LINKED_QUESTIONS_FOR_DEFINITION_QUERY_KEY, accountId, definitionId, params],
        enabled: enabled && meQuery.isFetched && definitionId !== undefined,
        queryFn: () => contactAttributeDefinitionClient.linkedQuestions(accountId, definitionId!, params),
    });
};
