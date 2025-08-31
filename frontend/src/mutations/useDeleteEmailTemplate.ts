import {useMutation, useQueryClient} from "@tanstack/react-query";
import {emailTemplateClient} from "../api/email-template.client";
import {IdParam} from "../types";
import {GET_EMAIL_TEMPLATES_QUERY_KEY} from "../queries/useGetEmailTemplates";

export const useDeleteEmailTemplateForOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, templateId}: {
            organizerId: IdParam;
            templateId: IdParam;
        }) => emailTemplateClient.deleteForOrganizer(organizerId, templateId),

        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({
                queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'organizer', variables.organizerId]
            });
        }
    });
};

export const useDeleteEmailTemplateForEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, templateId}: {
            eventId: IdParam;
            templateId: IdParam;
        }) => emailTemplateClient.deleteForEvent(eventId, templateId),

        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({
                queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'event', variables.eventId]
            });
        }
    });
};