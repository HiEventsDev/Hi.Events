import {useForm} from "@mantine/form";
import {GenericModalProps, Contact} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button, Group, TextInput} from "@mantine/core";
import {useCreateContact} from "../../../mutations/useCreateContact.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";

export const CreateContactModal = ({onClose}: GenericModalProps) => {
    const createMutation = useCreateContact();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm<Partial<Contact>>({
        initialValues: {
            email: '',
            first_name: '',
            last_name: '',
        },
        validate: {
            email: (value) => (!value ? t`Email is required` : null),
        },
    });

    const handleSubmit = () => {
        form.validate();
        if (form.isValid()) {
            createMutation.mutate({contactData: form.values}, {
                onSuccess: () => {
                    showSuccess(t`Contact created successfully`);
                    form.reset();
                    onClose();
                },
                onError: (error) => formErrorHandler(form, error),
            });
        }
    };

    return (
        <Modal heading={t`Create Contact`} onClose={onClose} opened>
            <TextInput
                label={t`Email`}
                placeholder="email@example.com"
                required
                {...form.getInputProps('email')}
                mb="sm"
            />
            <TextInput
                label={t`First Name`}
                placeholder={t`First Name`}
                {...form.getInputProps('first_name')}
                mb="sm"
            />
            <TextInput
                label={t`Last Name`}
                placeholder={t`Last Name`}
                {...form.getInputProps('last_name')}
                mb="md"
            />
            <Group justify="flex-end" mt="xl" mb="md">
                <Button variant="default" onClick={onClose} disabled={createMutation.isPending}>
                    {t`Cancel`}
                </Button>
                <Button loading={createMutation.isPending} onClick={handleSubmit}>
                    {createMutation.isPending ? t`Working...` : t`Create Contact`}
                </Button>
            </Group>
        </Modal>
    );
};
