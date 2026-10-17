import {t} from "@lingui/macro";
import {Modal} from "../../../common/Modal";
import {TwoFactorSetupFlow} from "../../../common/TwoFactor/TwoFactorSetupFlow.tsx";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {resumeUrl} from "../../../../hooks/useResumeAction.ts";
import {SECURITY_SETTINGS_PATH} from "../../../../api/client.ts";

interface EnableTwoFactorModalProps {
    email?: string;
    onClose: () => void;
}

export const EnableTwoFactorModal = ({email, onClose}: EnableTwoFactorModalProps) => {
    return (
        <Modal opened onClose={onClose} heading={t`Set up two-factor authentication`} size={560}>
            <TwoFactorSetupFlow
                email={email}
                resumeUrl={resumeUrl(SECURITY_SETTINGS_PATH, 'enable-2fa')}
                onComplete={() => {
                    showSuccess(t`Two-factor authentication is on`);
                    onClose();
                }}
            />
        </Modal>
    );
};
