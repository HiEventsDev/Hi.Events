import {useQuery} from "@tanstack/react-query";
import {emailTemplateClient} from "../api/email-template.client";
import {IdParam, EmailTemplateType} from "../types";

export const GET_EMAIL_TEMPLATES_QUERY_KEY = 'getEmailTemplates';

export const useGetEmailTemplatesForOrganizer = (
    organizerId: IdParam,
    params?: { template_type?: EmailTemplateType, include_inactive?: boolean },
    enabled = true
) => {
    return useQuery({
        queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'organizer', organizerId, params],
        queryFn: () => emailTemplateClient.getByOrganizer(organizerId, params),
        enabled: !!organizerId && enabled,
    });
};

export const useGetEmailTemplatesForEvent = (
    eventId: IdParam,
    params?: { template_type?: EmailTemplateType, include_inactive?: boolean },
    enabled = true
) => {
    return useQuery({
        queryKey: [GET_EMAIL_TEMPLATES_QUERY_KEY, 'event', eventId, params],
        queryFn: () => emailTemplateClient.getByEvent(eventId, params),
        enabled: !!eventId && enabled,
    });
};