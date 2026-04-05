import {publicApi} from "./public-client.ts";
import {IdParam} from "../types";

export const certificateClientPublic = {
    download: async (eventId: IdParam, attendeeShortId: string) => {
        const response = await publicApi.get(
            `events/${eventId}/attendees/${attendeeShortId}/certificate`,
            {responseType: 'blob'}
        );
        return response.data;
    },
};
