import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, SortableItem} from "../types.ts";
import {questionClient} from "../api/question.client.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../queries/useGetEventQuestions.ts";

export const useSortQuestions = () => {
    const queryClient = useQueryClient();
    return useMutation(
        ({sortedQuestionIds, eventId}: {
            eventId: IdParam,
            sortedQuestionIds: SortableItem[],
        }) => questionClient.sortQuestions(eventId, sortedQuestionIds),
        {
            onSuccess: (_, variables) => {
                queryClient.invalidateQueries([GET_EVENT_QUESTIONS_QUERY_KEY, variables.eventId]);
            }
        }
    )
}