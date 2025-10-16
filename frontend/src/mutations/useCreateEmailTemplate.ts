import {useMutation, useQueryClient} from "@tanstack/react-query";
import {emailTemplateClient} from "../api/email-template.client";
import {CreateEmailTemplateRequest, IdParam} from "../types";
import {GET_EMAIL_TEMPLATES_QUERY_KEY} from "../queries/useGetEmailTemplates";

export const useCreateEmailTemplateForOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, templateData}: {
            organizerId: IdParam;
            templateData: CreateEmailTemplateRequest;
        }) => emailTemplateClient.createForOrganizer(organizerId, templateData),

        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({
                queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'organizer', variables.organizerId]
            });
        }
    });
};

export const useCreateEmailTemplateForEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, templateData}: {
            eventId: IdParam;
            templateData: CreateEmailTemplateRequest;
        }) => emailTemplateClient.createForEvent(eventId, templateData),

        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({
                queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'event', variables.eventId]
            });
        }
    });
};