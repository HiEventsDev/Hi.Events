import {Button, PasswordInput} from "@mantine/core";
import {Modal, ModalFooter} from "../../../common/Modal";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {useUpdateMe} from "../../../../mutations/useUpdateMe.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../../utilites/notifications.tsx";

interface ChangePasswordModalProps {
    onClose: () => void;
}

export const ChangePasswordModal = ({onClose}: ChangePasswordModalProps) => {
    const updateMutation = useUpdateMe();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm({
        initialValues: {
            current_password: '',
            password: '',
            password_confirmation: '',
        },
    });

    const handleSubmit = (values: typeof form.values) => {
        updateMutation.mutate({userData: values}, {
            onSuccess: () => {
                showSuccess(t`Password changed`);
                onClose();
            },
            onError: (error: any) => errorHandler(form, error),
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Change password`} size={440}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <PasswordInput
                    {...form.getInputProps('current_password')}
                    label={t`Current password`}
                    autoComplete="current-password"
                    data-autofocus
                    required
                />
                <PasswordInput
                    {...form.getInputProps('password')}
                    label={t`New password`}
                    description={t`At least 8 characters.`}
                    autoComplete="new-password"
                    required
                />
                <PasswordInput
                    {...form.getInputProps('password_confirmation')}
                    label={t`Confirm new password`}
                    autoComplete="new-password"
                    required
                />
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={updateMutation.isPending}>
                        {t`Change password`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
