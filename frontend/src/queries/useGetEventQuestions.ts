import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {questionClient} from "../api/question.client.ts";

export const GET_EVENT_QUESTIONS_QUERY_KEY = 'getEventQuestions';

export const useGetEventQuestions = (eventId: IdParam) => {
    return useQuery(
        [GET_EVENT_QUESTIONS_QUERY_KEY, eventId],
        async () => {
            const {data} = await questionClient.all(eventId);
            return data;
        }
    )
};