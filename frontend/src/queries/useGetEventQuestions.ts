import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {questionClient} from "../api/question.client.ts";

export const GET_EVENT_QUESTIONS_QUERY_KEY = 'getEventQuestions';

export const useGetEventQuestions = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_QUESTIONS_QUERY_KEY, eventId],

        queryFn: async () => {
            const {data} = await questionClient.all(eventId);
            return data;
        }
    });
};