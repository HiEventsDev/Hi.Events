import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, QuestionRequestData} from "../types.ts";
import {questionClient} from "../api/question.client.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../queries/useGetEventQuestions.ts";

export const useCreateQuestion = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, questionData}: {
            eventId: IdParam,
            questionData: QuestionRequestData,
        }) => questionClient.create(eventId, questionData),

        onSuccess: (_, variables) => queryClient.invalidateQueries({
            queryKey: [GET_EVENT_QUESTIONS_QUERY_KEY, variables.eventId],
        }),
    });
}
