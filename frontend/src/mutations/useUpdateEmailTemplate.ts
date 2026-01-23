import {useMutation, useQueryClient} from "@tanstack/react-query";
import {emailTemplateClient} from "../api/email-template.client";
import {UpdateEmailTemplateRequest, IdParam} from "../types";
import {GET_EMAIL_TEMPLATES_QUERY_KEY} from "../queries/useGetEmailTemplates";

export const useUpdateEmailTemplateForOrganizer = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, templateId, templateData}: {
            organizerId: IdParam;
            templateId: IdParam;
            templateData: UpdateEmailTemplateRequest;
        }) => emailTemplateClient.updateForOrganizer(organizerId, templateId, templateData),

        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({
                queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'organizer', variables.organizerId]
            });
        }
    });
};

export const useUpdateEmailTemplateForEvent = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, templateId, templateData}: {
            eventId: IdParam;
            templateId: IdParam;
            templateData: UpdateEmailTemplateRequest;
        }) => emailTemplateClient.updateForEvent(eventId, templateId, templateData),

        onSuccess: (_, variables) => {
            return queryClient.invalidateQueries({
                queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'event', variables.eventId]
            });
        }
    });
};