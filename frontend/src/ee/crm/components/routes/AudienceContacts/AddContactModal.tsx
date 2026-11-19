import {t} from "@lingui/macro";
import {Button, Group, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../../types.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCreateCrmContact} from "../../../mutations/useCreateCrmContact.ts";
import {CrmContact} from "../../../types.ts";

const needsRestoreConfirmation = (error: unknown): boolean =>
    !!(error as { response?: { data?: { errors?: Record<string, unknown> } } })?.response?.data?.errors?.restore_deleted;

export const AddContactModal = ({organizerId, onClose, onCreated}: {
    organizerId: IdParam,
    onClose: () => void,
    onCreated: (contact: CrmContact) => void,
}) => {
    const mutation = useCreateCrmContact();
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm({initialValues: {email: '', first_name: '', last_name: ''}});

    const create = (values: typeof form.values, restoreDeleted: boolean) => mutation.mutate({
        organizerId,
        contact: {
            email: values.email.trim(),
            first_name: values.first_name.trim() || null,
            last_name: values.last_name.trim() || null,
            ...(restoreDeleted ? {restore_deleted: true} : {}),
        },
    }, {
        onSuccess: response => {
            showSuccess(restoreDeleted ? t`Contact restored` : t`Contact added`);
            onCreated(response.data);
        },
        onError: error => {
            if (!restoreDeleted && needsRestoreConfirmation(error)) {
                confirmationDialog(
                    t`Restore this person?`,
                    () => create(values, true),
                    {
                        confirm: t`Restore contact`,
                        description: t`This person was deleted from your audience. Re-adding them restores their order history.`,
                    },
                );
                return;
            }
            errorHandler(form, error);
        },
    });

    const handleSubmit = (values: typeof form.values) => create(values, false);

    return (
        <Modal opened onClose={onClose} heading={t`Add a contact`} size="md">
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <TextInput
                    {...form.getInputProps('email')}
                    type="email"
                    label={t`Email address`}
                    placeholder="name@example.com"
                    required
                    data-autofocus
                />
                <Group grow align="flex-start">
                    <TextInput {...form.getInputProps('first_name')} label={t`First name`} maxLength={50}/>
                    <TextInput {...form.getInputProps('last_name')} label={t`Last name`} maxLength={50}/>
                </Group>
                <Text size="sm" c="dimmed">
                    {t`People you add yourself aren't opted in to marketing emails. If they book later, their orders appear on this contact.`}
                </Text>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={mutation.isPending}>
                        {t`Add contact`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
