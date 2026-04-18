import {publicApi} from "./public-client.ts";

export interface ContactLookupResult {
    found: boolean;
    first_name: string | null;
    last_name: string | null;
    question_answers: Record<string, unknown>;
}

export const contactClientPublic = {
    lookupByEmail: async (eventId: number, email: string): Promise<ContactLookupResult> => {
        const response = await publicApi.post<ContactLookupResult>(
            `events/${eventId}/contact-lookup`,
            {email},
        );
        return response.data;
    },
};
