import {useQuery} from '@tanstack/react-query';
import {emailTemplateClient} from '../api/email-template.client';

export const GET_DEFAULT_EMAIL_TEMPLATES_QUERY_KEY = 'getDefaultEmailTemplates';

export const useGetDefaultEmailTemplates = (enabled: boolean = false) => {
    return useQuery({
        queryKey: [GET_DEFAULT_EMAIL_TEMPLATES_QUERY_KEY],
        queryFn: () => emailTemplateClient.getDefaultTemplates(),
        enabled,
    });
};