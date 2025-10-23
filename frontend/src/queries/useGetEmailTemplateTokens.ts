import {useQuery} from "@tanstack/react-query";
import {emailTemplateClient} from "../api/email-template.client";
import {EmailTemplateType} from "../types";

export const GET_EMAIL_TEMPLATE_TOKENS_QUERY_KEY = 'getEmailTemplateTokens';

export const useGetEmailTemplateTokens = (templateType: EmailTemplateType, enabled = true) => {
    return useQuery({
        queryKey: [GET_EMAIL_TEMPLATE_TOKENS_QUERY_KEY, templateType],
        queryFn: () => emailTemplateClient.getAvailableTokens(templateType),
        enabled: !!templateType && enabled,
        staleTime: 1000 * 60 * 30, // Tokens don't change often, cache for 30 minutes
    });
};