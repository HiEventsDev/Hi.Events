import {useState} from 'react';
import {useParams} from 'react-router';
import {useGetEmailTemplatesForEvent} from '../../../../../../queries/useGetEmailTemplates';
import {useGetDefaultEmailTemplates} from '../../../../../../queries/useGetDefaultEmailTemplates';
import {
    useCreateEmailTemplateForEvent,
} from '../../../../../../mutations/useCreateEmailTemplate';
import {usePreviewEmailTemplateForEvent} from '../../../../../../mutations/usePreviewEmailTemplate';
import {useUpdateEmailTemplateForEvent} from "../../../../../../mutations/useUpdateEmailTemplate.ts";
import {useDeleteEmailTemplateForEvent} from "../../../../../../mutations/useDeleteEmailTemplate.ts";
import {EmailTemplateSettingsBase} from '../../../../../common/EmailTemplateSettings';
import {EmailTemplateType} from '../../../../../../types';

export const EmailTemplateSettings = () => {
    const {eventId} = useParams();
    const [shouldFetchDefaults, setShouldFetchDefaults] = useState(false);

    // Queries
    const {data: templatesData, isLoading} = useGetEmailTemplatesForEvent(eventId!);
    const {data: defaultTemplatesData} = useGetDefaultEmailTemplates(shouldFetchDefaults);

    // Mutations
    const createMutation = useCreateEmailTemplateForEvent();
    const updateMutation = useUpdateEmailTemplateForEvent();
    const deleteMutation = useDeleteEmailTemplateForEvent();
    const previewMutation = usePreviewEmailTemplateForEvent();

    const templates = templatesData?.data || [];

    const handleCreateTemplate = (type: EmailTemplateType) => {
        // Enable fetching default templates if not already fetched
        if (!defaultTemplatesData) {
            setShouldFetchDefaults(true);
        }
    };

    return (
        <EmailTemplateSettingsBase
            contextId={eventId!}
            contextType="event"
            templates={templates}
            defaultTemplates={defaultTemplatesData}
            isLoading={isLoading}
            createMutation={createMutation}
            updateMutation={updateMutation}
            deleteMutation={deleteMutation}
            previewMutation={previewMutation}
            onCreateTemplate={handleCreateTemplate}
        />
    );
};

export default EmailTemplateSettings;
