import {questionClient} from "../api/question.client.ts";
import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";

export const GET_QUESTION_QUERY_KEY = 'getQuestion';

export const useGetQuestion = (eventId: IdParam, questionId: IdParam) => {
    return useQuery(
        [GET_QUESTION_QUERY_KEY, eventId, questionId],
        async () => {
            const {data} = await questionClient.get(eventId, questionId);
            return data;
        },
    )
}