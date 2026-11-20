import {useState} from 'react';
import {t} from '@lingui/macro';
import {useGetEmailTemplatesForOrganizer} from '../../../../../../queries/useGetEmailTemplates';
import {useGetDefaultEmailTemplates} from '../../../../../../queries/useGetDefaultEmailTemplates';
import {useCreateEmailTemplateForOrganizer,} from '../../../../../../mutations/useCreateEmailTemplate';
import {usePreviewEmailTemplateForOrganizer} from '../../../../../../mutations/usePreviewEmailTemplate';
import {useDeleteEmailTemplateForOrganizer} from "../../../../../../mutations/useDeleteEmailTemplate.ts";
import {useUpdateEmailTemplateForOrganizer} from "../../../../../../mutations/useUpdateEmailTemplate.ts";
import {EmailTemplateSettingsBase} from '../../../../../common/EmailTemplateSettings';
import {SettingsPage} from '../../../../../common/SettingsPage';

interface EmailTemplateSettingsProps {
    organizerId: string | number;
}

const EmailTemplateSettings = ({organizerId}: EmailTemplateSettingsProps) => {
    const [shouldFetchDefaults, setShouldFetchDefaults] = useState(false);

    const {data: templatesData, isLoading} = useGetEmailTemplatesForOrganizer(organizerId, {include_inactive: true});
    const {data: defaultTemplatesData} = useGetDefaultEmailTemplates(shouldFetchDefaults);

    const createMutation = useCreateEmailTemplateForOrganizer();
    const updateMutation = useUpdateEmailTemplateForOrganizer();
    const deleteMutation = useDeleteEmailTemplateForOrganizer();
    const previewMutation = usePreviewEmailTemplateForOrganizer();

    const templates = templatesData?.data || [];

    const handleCreateTemplate = () => {
        if (!defaultTemplatesData) {
            setShouldFetchDefaults(true);
        }
    };

    return (
        <SettingsPage
            title={t`Email Templates`}
            description={t`Customize the emails sent to your customers using Liquid templating. These templates will be used as defaults for all events in your organization.`}
        >
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
        </SettingsPage>
    );
};

export default EmailTemplateSettings;
