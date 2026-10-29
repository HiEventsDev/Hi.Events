import {Button, TextInput, UnstyledButton} from "@mantine/core";
import {t} from "@lingui/macro";
import {FormEvent, useState} from "react";
import {Modal, ModalFooter} from "../../../common/Modal";
import {OneTimeCodeInput} from "../../../common/OneTimeCodeInput";
import {useDisableTwoFactor} from "../../../../mutations/useTwoFactorMutations.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {resumeUrl} from "../../../../hooks/useResumeAction.ts";
import {SECURITY_SETTINGS_PATH} from "../../../../api/client.ts";
import {ConfirmIdentityField, useIdentityConfirmation} from "../../../common/ConfirmIdentity";
import classes from "./Security.module.scss";

interface DisableTwoFactorModalProps {
    onClose: () => void;
}

export const DisableTwoFactorModal = ({onClose}: DisableTwoFactorModalProps) => {
    const identity = useIdentityConfirmation();
    const [code, setCode] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const disable = useDisableTwoFactor();

    const canSubmit = identity.confirmation !== null && (useRecoveryCode ? recoveryCode.length >= 10 : code.length === 6);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        if (!canSubmit) {
            return;
        }

        const confirmation = identity.confirmation ?? {};

        disable.mutate(useRecoveryCode ? {...confirmation, recovery_code: recoveryCode} : {...confirmation, code}, {
            onSuccess: () => {
                showSuccess(t`Two-factor authentication is off`);
                onClose();
            },
            onError: (error: any) => {
                const data = error?.response?.data;
                setCode('');
                identity.setError(data?.errors?.password?.[0] ?? null);
                setErrors({
                    code: data?.errors?.code?.[0] ?? data?.errors?.recovery_code?.[0]
                        ?? (data?.errors?.password ? undefined : data?.message ?? t`Something went wrong. Please try again.`),
                });
            },
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Turn off two-factor authentication`} size={480}>
            <form className={classes.modalBody} onSubmit={submit}>
                <p className={classes.modalText}>
                    {identity.hasPassword
                    ? t`Your account will only be protected by your password. Your recovery codes and trusted devices will be removed.`
                    : t`Your recovery codes and trusted devices will be removed.`}
                </p>
                <ConfirmIdentityField
                    identity={identity}
                    resumeUrl={resumeUrl(SECURITY_SETTINGS_PATH, 'disable-2fa')}
                    passwordTestId="two-factor-disable-password"
                />
                {useRecoveryCode ? (
                    <TextInput
                        label={t`Recovery code`}
                        placeholder="xxxxx-xxxxx"
                        value={recoveryCode}
                        onChange={(event) => {
                            setRecoveryCode(event.currentTarget.value);
                            setErrors(current => ({...current, code: undefined}));
                        }}
                        error={errors.code}
                        autoComplete="off"
                        spellCheck={false}
                    />
                ) : (
                    <div>
                        <div className={classes.fieldLabel}>{t`Authentication code`}</div>
                        <OneTimeCodeInput
                            label={t`Authentication code`}
                            value={code}
                            onChange={(value) => {
                                setCode(value);
                                setErrors(current => ({...current, code: undefined}));
                            }}
                            error={errors.code}
                            disabled={disable.isPending}
                            dataTestId="two-factor-disable-code"
                        />
                    </div>
                )}
                <UnstyledButton
                    className={classes.inlineLink}
                    onClick={() => {
                        setUseRecoveryCode(current => !current);
                        setErrors({});
                    }}
                >
                    {useRecoveryCode ? t`Use your authenticator app instead` : t`Use a recovery code instead`}
                </UnstyledButton>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        type="submit"
                        color="red"
                        loading={disable.isPending}
                        disabled={!canSubmit}
                        data-testid="two-factor-disable-submit"
                    >
                        {t`Turn off two-factor authentication`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
