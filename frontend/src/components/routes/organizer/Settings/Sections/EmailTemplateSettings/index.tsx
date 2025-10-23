import {useState} from 'react';
import {useGetEmailTemplatesForOrganizer} from '../../../../../../queries/useGetEmailTemplates';
import {useGetDefaultEmailTemplates} from '../../../../../../queries/useGetDefaultEmailTemplates';
import {useCreateEmailTemplateForOrganizer,} from '../../../../../../mutations/useCreateEmailTemplate';
import {usePreviewEmailTemplateForOrganizer} from '../../../../../../mutations/usePreviewEmailTemplate';
import {useDeleteEmailTemplateForOrganizer} from "../../../../../../mutations/useDeleteEmailTemplate.ts";
import {useUpdateEmailTemplateForOrganizer} from "../../../../../../mutations/useUpdateEmailTemplate.ts";
import {EmailTemplateSettingsBase} from '../../../../../common/EmailTemplateSettings';
import {EmailTemplateType} from '../../../../../../types';

interface EmailTemplateSettingsProps {
    organizerId: string | number;
}

const EmailTemplateSettings = ({organizerId}: EmailTemplateSettingsProps) => {
    const [shouldFetchDefaults, setShouldFetchDefaults] = useState(false);

    // Queries
    const {data: templatesData, isLoading} = useGetEmailTemplatesForOrganizer(organizerId, {include_inactive: true});
    const {data: defaultTemplatesData} = useGetDefaultEmailTemplates(shouldFetchDefaults);

    // Mutations
    const createMutation = useCreateEmailTemplateForOrganizer();
    const updateMutation = useUpdateEmailTemplateForOrganizer();
    const deleteMutation = useDeleteEmailTemplateForOrganizer();
    const previewMutation = usePreviewEmailTemplateForOrganizer();

    const templates = templatesData?.data || [];

    const handleCreateTemplate = (type: EmailTemplateType) => {
        // Enable fetching default templates if not already fetched
        if (!defaultTemplatesData) {
            setShouldFetchDefaults(true);
        }
    };

    return (
        <EmailTemplateSettingsBase
            contextId={organizerId}
            contextType="organizer"
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
