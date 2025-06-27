import {useMutation} from "@tanstack/react-query";
import {organizerPublicClient} from "../api/organizer.client.ts";
import {IdParam} from "../types.ts";

export const useContactOrganizer = () => {
    return useMutation({
        mutationFn: ({organizerId, contactData}: {
            organizerId: IdParam;
            contactData: {
                name: string;
                email: string;
                message: string;
            };
        }) => organizerPublicClient.contactOrganizer(organizerId, contactData),
    });
};