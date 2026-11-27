import {t} from "@lingui/macro";
import {SettingsPage} from "../../../../../common/SettingsPage";
import {GeneralEmailSettings} from './GeneralEmailSettings';
import {TemplateSettings} from './TemplateSettings';

export const EmailSettings = () => {
    return (
        <SettingsPage
            title={t`Email & Templates`}
            description={t`Customize the email and notification settings for this event`}
        >
            <GeneralEmailSettings/>
            <TemplateSettings/>
        </SettingsPage>
    );
};

export default EmailSettings;
