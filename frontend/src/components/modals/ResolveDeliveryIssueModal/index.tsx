import {useForm} from "@mantine/form";
import {GenericModalProps, IdParam, DeliveryIssue} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Button, Checkbox, FocusTrap, Group, TextInput} from "@mantine/core";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t} from "@lingui/macro";
import {showSuccess, showError} from "../../../utilites/notifications.tsx";
import {useResendTransactionMessage} from "../../../mutations/useResendTransactionMessage.ts";
import {useResendOutgoingMessage} from "../../../mutations/useResendOutgoingMessage.ts";

interface ResolveDeliveryIssueModalProps extends GenericModalProps {
    eventId: IdParam;
    message: DeliveryIssue;
}

export const ResolveDeliveryIssueModal = ({onClose, eventId, message}: ResolveDeliveryIssueModalProps) => {
    const isTransaction = message.source_type === 'transaction';
    const resendTransactionMutation = useResendTransactionMessage();
    const resendAnnouncementMutation = useResendOutgoingMessage();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm({
        initialValues: {
            email: message.recipient,
            resend: true,
        },
    });

    const emailChanged = form.values.email.toLowerCase() !== message.recipient.toLowerCase();
    const isPending = isTransaction ? resendTransactionMutation.isPending : resendAnnouncementMutation.isPending;

    const handleEmailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        form.getInputProps('email').onChange(e);
        const newEmail = e.currentTarget.value;
        const changed = newEmail.toLowerCase() !== message.recipient.toLowerCase();
        if (changed && !form.values.resend) {
            form.setFieldValue('resend', true);
        }
    };

    const handleSubmit = (values: { email: string, resend: boolean }) => {
        if (!values.resend) {
            onClose();
            return;
        }

        const mutate = isTransaction
            ? resendTransactionMutation.mutate
            : resendAnnouncementMutation.mutate;

        mutate({
            eventId,
            messageId: message.id!,
            email: emailChanged ? values.email : undefined,
        }, {
            onSuccess: () => {
                showSuccess(t`Email has been resent. The issue will be auto-resolved.`);
                onClose();
            },
            onError: (error: any) => {
                formErrorHandler(form, error);
                showError(t`Failed to resend email. Please try again.`);
            },
        });
    };

    return (
        <Modal heading={t`Resolve Delivery Issue`} onClose={onClose} opened>
            <FocusTrap active>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <TextInput
                    required
                    type="email"
                    label={t`Recipient Email`}
                    description={isTransaction
                        ? t`Updating the email will also update the associated order or attendee record.`
                        : t`Updating the email will resend the announcement to the new address.`
                    }
                    disabled={isPending}
                    data-autofocus
                    {...form.getInputProps('email')}
                    onChange={handleEmailChange}
                />

                <Checkbox
                    mt="md"
                    label={t`Resend this email`}
                    disabled={isPending}
                    {...form.getInputProps('resend', {type: 'checkbox'})}
                />

                <Group mt="xl" grow>
                    <Button
                        variant="default"
                        onClick={onClose}
                        disabled={isPending}
                    >
                        {t`Cancel`}
                    </Button>
                    <Button
                        loading={isPending}
                        type="submit"
                    >
                        {form.values.resend ? t`Resend` : t`Close`}
                    </Button>
                </Group>
            </form>
            </FocusTrap>
        </Modal>
    );
};
