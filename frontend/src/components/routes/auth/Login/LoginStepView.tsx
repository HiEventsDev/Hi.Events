import {SECURITY_SETTINGS_PATH, setPreviousUrl} from "../../../../api/client.ts";
import {LoginSteps} from "../../../../hooks/useLoginSteps.ts";
import {AccountChooser} from "./AccountChooser.tsx";
import {RecoveryCodeNotice} from "./RecoveryCodeNotice.tsx";
import {TwoFactorChallenge} from "./TwoFactorChallenge.tsx";

interface LoginStepViewProps {
    steps: LoginSteps;
    onBack: () => void;
}

export const LoginStepView = ({steps, onBack}: LoginStepViewProps) => {
    if (steps.step === 'recovery-notice') {
        return (
            <RecoveryCodeNotice
                remaining={steps.recoveryCodesRemaining}
                onManageCodes={() => {
                    setPreviousUrl(SECURITY_SETTINGS_PATH);
                    steps.complete();
                }}
                onContinue={steps.complete}
            />
        );
    }

    if (steps.step === 'two-factor') {
        return (
            <TwoFactorChallenge
                isSubmitting={steps.isVerifying}
                error={steps.twoFactorError}
                onClearError={() => steps.setTwoFactorError(null)}
                onSubmit={steps.verifyTwoFactor}
                onBack={onBack}
            />
        );
    }

    if (steps.step === 'choose-account') {
        return (
            <AccountChooser
                accounts={steps.accounts}
                pendingAccountId={steps.pendingAccountId}
                onChoose={steps.chooseAccount}
                onBack={onBack}
            />
        );
    }

    return null;
};
