import {GenericModalProps, IdParam} from "../../../../../types.ts";
import {Modal} from "../../../../../components/common/Modal";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, TextInput} from "@mantine/core";
import {useRegisterTerminalReader} from "../../../mutations/useRegisterTerminalReader.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {AxiosError} from "axios";

interface AddTerminalReaderModalProps extends GenericModalProps {
    organizerId: IdParam;
}

export const AddTerminalReaderModal = ({onClose, organizerId}: AddTerminalReaderModalProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const registerMutation = useRegisterTerminalReader();
    const form = useForm({
        initialValues: {
            label: '',
            registration_code: '',
        },
    });

    const handleSubmit = (values: { label: string; registration_code: string }) => {
        registerMutation.mutate({organizerId, payload: values}, {
            onSuccess: () => {
                showSuccess(t`Card reader added`);
                onClose();
            },
            onError: (error) => {
                if (error instanceof AxiosError && error.response?.status === 422 && !error.response.data?.errors) {
                    showError(error.response.data?.message);
                    return;
                }
                errorHandler(form, error);
            },
        });
    };

    return (
        <Modal opened onClose={onClose} heading={t`Add card reader`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <TextInput
                    {...form.getInputProps('label')}
                    label={t`Label`}
                    placeholder={t`Front door reader`}
                    required
                />
                <TextInput
                    {...form.getInputProps('registration_code')}
                    mt="sm"
                    label={t`Pairing code`}
                    description={t`On the reader, swipe right from the left edge, enter the admin PIN 07139, then choose Generate pairing code.`}
                    placeholder={t`e.g. sepia-cerulean-orynx`}
                    required
                    autoComplete="off"
                />
                <Button
                    type="submit"
                    fullWidth
                    mt="md"
                    loading={registerMutation.isPending}
                    data-testid="terminal-reader-submit-button"
                >
                    {t`Add reader`}
                </Button>
            </form>
        </Modal>
    );
};
