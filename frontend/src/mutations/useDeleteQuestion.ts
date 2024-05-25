import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {questionClient} from "../api/question.client.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../queries/useGetEventQuestions.ts";

export const useDeleteQuestion = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({eventId, questionId}: {
            eventId: IdParam,
            questionId: IdParam,
        }) => questionClient.delete(eventId, questionId),
        {
            onSuccess: (_, variables) => queryClient.invalidateQueries([GET_EVENT_QUESTIONS_QUERY_KEY, variables.eventId]),
        }
    )
}
