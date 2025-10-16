import {GeneralEmailSettings} from './GeneralEmailSettings';
import {TemplateSettings} from './TemplateSettings';

export const EmailSettings = () => {
    return (
        <div>
            <GeneralEmailSettings />
            <TemplateSettings />
        </div>
    );
};

export default EmailSettings;
