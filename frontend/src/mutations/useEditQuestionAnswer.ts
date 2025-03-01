import {useMutation} from "@tanstack/react-query";
import {IdParam} from '../types';
import {questionClient} from "../api/question.client.ts";

export const useEditQuestionAnswer = () => {
    return useMutation({
        mutationFn: ({eventId, questionId, answerId, answer}: {
            eventId: IdParam,
            questionId: IdParam,
            answerId: IdParam,
            answer: string | string[]
        }) => questionClient.updateAnswerQuestion(eventId, questionId, answerId, answer),
        onSuccess: (_, variables) => {
            return;
        }
    });
}
