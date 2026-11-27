import {t} from "@lingui/macro";
import {Button, PinInput, Text} from "@mantine/core";
import {useEffect, useRef, useState} from "react";
import {Modal, ModalFooter} from "../Modal";
import {useConfirmSelfServiceCode, useRequestSelfServiceCode} from "../../../mutations/useSelfServiceVerification.ts";
import {SelfServiceLink, storeSelfServiceSession} from "../../../utilites/selfServiceSession.ts";
import classes from "./VerifyEmailModal.module.scss";

const CODE_LENGTH = 6;
const RESEND_COOLDOWN_SECONDS = 30;

interface VerifyEmailModalProps {
    link: SelfServiceLink;
    onClose: () => void;
    onVerified: () => void;
}

const errorMessage = (error: any, field: string, fallback: string): string => {
    if (error?.response?.status === 429) {
        return t`Too many attempts. Please wait a few minutes and try again.`;
    }

    return error?.response?.data?.errors?.[field]?.[0] ?? error?.response?.data?.message ?? fallback;
};

export const VerifyEmailModal = ({link, onClose, onVerified}: VerifyEmailModalProps) => {
    const [maskedEmail, setMaskedEmail] = useState<string | null>(null);
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [cooldown, setCooldown] = useState(RESEND_COOLDOWN_SECONDS);
    const [failedAttempts, setFailedAttempts] = useState(0);
    const hasRequestedCode = useRef(false);
    const requestCodeMutation = useRequestSelfServiceCode();
    const confirmCodeMutation = useConfirmSelfServiceCode();

    const sendCode = () => {
        setError(null);
        setCooldown(RESEND_COOLDOWN_SECONDS);
        requestCodeMutation.mutate(link, {
            onSuccess: ({email}) => setMaskedEmail(email),
            onError: (requestError) => setError(errorMessage(requestError, 'email', t`We could not send a code. Please try again.`)),
        });
    };

    useEffect(() => {
        if (hasRequestedCode.current) {
            return;
        }
        hasRequestedCode.current = true;
        sendCode();
    }, []);

    useEffect(() => {
        if (cooldown <= 0) {
            return;
        }
        const timer = window.setTimeout(() => setCooldown((seconds) => seconds - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [cooldown]);

    const confirm = (value: string) => {
        setError(null);
        confirmCodeMutation.mutate({link, code: value}, {
            onSuccess: (session) => {
                storeSelfServiceSession(link, session);
                onVerified();
            },
            onError: (confirmError) => {
                setCode('');
                setFailedAttempts((attempts) => attempts + 1);
                setError(errorMessage(confirmError, 'code', t`That code is not correct. Check the email and try again.`));
            },
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Confirm it's you`} size="md">
            <Text size="sm">
                {maskedEmail
                    ? t`We emailed a ${CODE_LENGTH}-digit code to ${maskedEmail}. Enter it below to continue.`
                    : t`We are emailing you a ${CODE_LENGTH}-digit code. Enter it below to continue.`}
            </Text>

            <div className={classes.code}>
                <PinInput
                    key={failedAttempts}
                    length={CODE_LENGTH}
                    type="number"
                    oneTimeCode
                    autoFocus
                    size="lg"
                    value={code}
                    onChange={setCode}
                    onComplete={confirm}
                    disabled={confirmCodeMutation.isPending}
                    error={!!error}
                    ariaLabel={t`Verification code`}
                />
            </div>

            {error && <Text size="sm" c="red" role="alert" className={classes.error}>{error}</Text>}

            <ModalFooter
                start={(
                    <Button
                        variant="subtle"
                        onClick={sendCode}
                        disabled={cooldown > 0}
                        data-testid="verify-email-resend-button"
                    >
                        {cooldown > 0 ? t`Send a new code in ${cooldown}s` : t`Send a new code`}
                    </Button>
                )}
            >
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button
                    onClick={() => confirm(code)}
                    loading={confirmCodeMutation.isPending}
                    disabled={code.length !== CODE_LENGTH}
                    data-testid="verify-email-confirm-button"
                >
                    {t`Continue`}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
