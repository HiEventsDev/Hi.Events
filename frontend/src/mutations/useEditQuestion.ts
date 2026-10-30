import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam, QuestionRequestData} from "../types.ts";
import {questionClient} from "../api/question.client.ts";
import {GET_EVENT_QUESTIONS_QUERY_KEY} from "../queries/useGetEventQuestions.ts";
import {GET_QUESTION_QUERY_KEY} from "../queries/useGetQuestion.ts";

export const useEditQuestion = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, questionId, questionData}: {
            eventId: IdParam,
            questionId: IdParam,
            questionData: QuestionRequestData,
        }) => questionClient.update(eventId, questionId, questionData),

        onSuccess: (_, variables) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUESTIONS_QUERY_KEY, variables.eventId]}),
            queryClient.invalidateQueries({queryKey: [GET_QUESTION_QUERY_KEY, variables.eventId]}),
        ]),
    });
}
