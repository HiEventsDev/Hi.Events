import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Modal, ModalFooter} from "../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {useUpdateCrmContact} from "../../mutations/useUpdateCrmContact.ts";
import {CrmContact} from "../../types.ts";
import classes from "./ContactDrawer.module.scss";

export const EditNameModal = ({organizerId, contact, onClose}: { organizerId: IdParam, contact: CrmContact, onClose: () => void }) => {
    const mutation = useUpdateCrmContact();
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm({
        initialValues: {first_name: contact.first_name ?? '', last_name: contact.last_name ?? ''},
    });

    const handleSubmit = (values: typeof form.values) => mutation.mutate({
        organizerId,
        contactId: contact.id,
        contact: {first_name: values.first_name.trim() || null, last_name: values.last_name.trim() || null},
    }, {
        onSuccess: () => {
            showSuccess(t`Name updated`);
            onClose();
        },
        onError: error => errorHandler(form, error),
    });

    return (
        <Modal opened onClose={onClose} heading={t`Edit name`} size="md">
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <div className={classes.formRow}>
                    <TextInput {...form.getInputProps('first_name')} label={t`First name`} maxLength={50} data-autofocus/>
                    <TextInput {...form.getInputProps('last_name')} label={t`Last name`} maxLength={50}/>
                </div>
                <p className={classes.empty}>
                    {t`New orders won't overwrite a name you set here.`}
                </p>
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={mutation.isPending}>{t`Save`}</Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
