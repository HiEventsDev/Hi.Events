import {Button, Modal, PasswordInput, TextInput, UnstyledButton} from "@mantine/core";
import {t} from "@lingui/macro";
import {FormEvent, useState} from "react";
import {OneTimeCodeInput} from "../../../../common/OneTimeCodeInput";
import {useDisableTwoFactor} from "../../../../../mutations/useTwoFactorMutations.ts";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import classes from "./Security.module.scss";

interface DisableTwoFactorModalProps {
    onClose: () => void;
}

export const DisableTwoFactorModal = ({onClose}: DisableTwoFactorModalProps) => {
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const disable = useDisableTwoFactor();

    const canSubmit = password.length > 0 && (useRecoveryCode ? recoveryCode.length >= 10 : code.length === 6);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        if (!canSubmit) {
            return;
        }

        disable.mutate(useRecoveryCode ? {password, recovery_code: recoveryCode} : {password, code}, {
            onSuccess: () => {
                showSuccess(t`Two-factor authentication is off`);
                onClose();
            },
            onError: (error: any) => {
                const data = error?.response?.data;
                setCode('');
                setErrors({
                    password: data?.errors?.password?.[0],
                    code: data?.errors?.code?.[0] ?? data?.errors?.recovery_code?.[0]
                        ?? (data?.errors?.password ? undefined : data?.message ?? t`Something went wrong. Please try again.`),
                });
            },
        });
    };

    return (
        <Modal
            opened
            onClose={onClose}
            title={t`Turn off two-factor authentication`}
            size={480}
            radius="lg"
            overlayProps={{opacity: 0.55, blur: 3}}
            classNames={{title: classes.modalTitle}}
        >
            <form className={classes.modalBody} onSubmit={submit}>
                <p className={classes.modalText}>
                    {t`Your account will only be protected by your password. Your recovery codes and trusted devices will be removed.`}
                </p>
                <PasswordInput
                    label={t`Current password`}
                    value={password}
                    onChange={(event) => {
                        setPassword(event.currentTarget.value);
                        setErrors(current => ({...current, password: undefined}));
                    }}
                    error={errors.password}
                    autoComplete="current-password"
                    required
                    data-testid="two-factor-disable-password"
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
                <Button
                    type="submit"
                    color="red"
                    fullWidth
                    loading={disable.isPending}
                    disabled={!canSubmit}
                    data-testid="two-factor-disable-submit"
                >
                    {t`Turn off two-factor authentication`}
                </Button>
            </form>
        </Modal>
    );
};
