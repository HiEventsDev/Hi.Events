import {Button, Modal} from "@mantine/core";
import {t} from "@lingui/macro";
import {useState} from "react";
import {OneTimeCodeInput} from "../../../../common/OneTimeCodeInput";
import {RecoveryCodesDisplay} from "../../../../common/TwoFactor/RecoveryCodesDisplay.tsx";
import {useRegenerateRecoveryCodes} from "../../../../../mutations/useTwoFactorMutations.ts";
import classes from "./Security.module.scss";

interface RegenerateRecoveryCodesModalProps {
    email?: string;
    onClose: () => void;
}

export const RegenerateRecoveryCodesModal = ({email, onClose}: RegenerateRecoveryCodesModalProps) => {
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [codes, setCodes] = useState<string[] | null>(null);
    const [acknowledged, setAcknowledged] = useState(false);
    const regenerate = useRegenerateRecoveryCodes();

    const submit = (value: string = code) => {
        if (value.length !== 6 || regenerate.isPending) {
            return;
        }

        regenerate.mutate(value, {
            onSuccess: setCodes,
            onError: (requestError: any) => {
                setCode('');
                setError(requestError?.response?.data?.errors?.code?.[0]
                    ?? requestError?.response?.data?.message
                    ?? t`Something went wrong. Please try again.`);
            },
        });
    };

    return (
        <Modal
            opened
            onClose={onClose}
            title={codes ? t`Your new recovery codes` : t`Generate new recovery codes`}
            size={520}
            radius="lg"
            closeOnClickOutside={false}
            overlayProps={{opacity: 0.55, blur: 3}}
            classNames={{title: classes.modalTitle}}
        >
            {codes ? (
                <div className={classes.modalBody}>
                    <RecoveryCodesDisplay
                        codes={codes}
                        email={email}
                        acknowledged={acknowledged}
                        onAcknowledgedChange={setAcknowledged}
                    />
                    <Button fullWidth disabled={!acknowledged} onClick={onClose} data-testid="two-factor-regenerate-done">
                        {t`Done`}
                    </Button>
                </div>
            ) : (
                <div className={classes.modalBody}>
                    <p className={classes.modalText}>
                        {t`Your current recovery codes will stop working straight away. Enter a code from your authenticator app to confirm.`}
                    </p>
                    <OneTimeCodeInput
                        label={t`Authentication code`}
                        value={code}
                        onChange={(value) => {
                            setCode(value);
                            setError(null);
                        }}
                        onComplete={submit}
                        error={error}
                        disabled={regenerate.isPending}
                        autoFocus
                        dataTestId="two-factor-regenerate-code"
                    />
                    <Button
                        fullWidth
                        loading={regenerate.isPending}
                        disabled={code.length !== 6}
                        onClick={() => submit()}
                        data-testid="two-factor-regenerate-submit"
                    >
                        {t`Generate new codes`}
                    </Button>
                </div>
            )}
        </Modal>
    );
};
