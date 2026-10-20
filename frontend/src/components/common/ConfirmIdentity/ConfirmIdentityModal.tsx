import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {FormEvent, ReactNode, useEffect, useRef} from "react";
import {IdentityConfirmation} from "../../../types.ts";
import {Modal, ModalFooter} from "../Modal";
import {ConfirmIdentityField, useIdentityConfirmation} from "./index.tsx";
import classes from "./ConfirmIdentity.module.scss";

interface ConfirmIdentityModalProps {
    heading: string;
    description: ReactNode;
    confirmLabel: string;
    resumeUrl: string;
    isSubmitting: boolean;
    danger?: boolean;
    onConfirm: (confirmation: IdentityConfirmation, setError: (message: string | null) => void) => void;
    onClose: () => void;
}

export const ConfirmIdentityModal = ({
                                         heading,
                                         description,
                                         confirmLabel,
                                         resumeUrl,
                                         isSubmitting,
                                         danger = false,
                                         onConfirm,
                                         onClose,
                                     }: ConfirmIdentityModalProps) => {
    const identity = useIdentityConfirmation();
    const formRef = useRef<HTMLFormElement>(null);
    const wasSubmitting = useRef(isSubmitting);

    useEffect(() => {
        const settled = wasSubmitting.current && !isSubmitting;
        wasSubmitting.current = isSubmitting;
        const focusWasLost = !document.activeElement || document.activeElement === document.body;

        if (settled && focusWasLost) {
            formRef.current?.querySelector<HTMLElement>('input:not(:disabled), button:not(:disabled)')?.focus();
        }
    }, [isSubmitting]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (identity.confirmation && !isSubmitting) {
            onConfirm(identity.confirmation, identity.setError);
        }
    };

    return (
        <Modal opened onClose={onClose} heading={heading} size={440}>
            <form ref={formRef} className={classes.modalBody} onSubmit={submit}>
                <p className={classes.modalText}>{description}</p>
                <ConfirmIdentityField identity={identity} resumeUrl={resumeUrl} autoFocus/>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        type="submit"
                        color={danger ? 'red' : undefined}
                        loading={isSubmitting}
                        disabled={!identity.confirmation}
                        data-testid="confirm-identity-submit"
                    >
                        {confirmLabel}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
