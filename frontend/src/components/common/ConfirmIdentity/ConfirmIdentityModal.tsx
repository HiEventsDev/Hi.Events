import {Button} from "@mantine/core";
import {t} from "@lingui/macro";
import {FormEvent, ReactNode} from "react";
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

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (identity.confirmation) {
            onConfirm(identity.confirmation, identity.setError);
        }
    };

    return (
        <Modal opened onClose={onClose} heading={heading} size={440}>
            <form className={classes.modalBody} onSubmit={submit}>
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
