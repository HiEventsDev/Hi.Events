import {useForm} from "@mantine/form";
import {GenericModalProps, IdParam, OutgoingTransactionMessage} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {Box, Button, Checkbox, FocusTrap, Group, Radio, Stack, Text, TextInput} from "@mantine/core";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t} from "@lingui/macro";
import {showSuccess, showError} from "../../../utilites/notifications.tsx";
import {useResendTransactionMessage} from "../../../mutations/useResendTransactionMessage.ts";
import {useResolveTransactionMessage} from "../../../mutations/useResolveTransactionMessage.ts";

interface ResolveDeliveryIssueModalProps extends GenericModalProps {
    eventId: IdParam;
    message: OutgoingTransactionMessage;
}

export const ResolveDeliveryIssueModal = ({onClose, eventId, message}: ResolveDeliveryIssueModalProps) => {
    const resendMutation = useResendTransactionMessage();
    const resolveMutation = useResolveTransactionMessage();
    const formErrorHandler = useFormErrorResponseHandler();

    const form = useForm({
        initialValues: {
            email: message.recipient,
            resend: false,
            resolveAction: 'none' as 'none' | 'auto' | 'now',
        },
    });

    const emailChanged = form.values.email.toLowerCase() !== message.recipient.toLowerCase();
    const isPending = resendMutation.isPending || resolveMutation.isPending;

    const handleEmailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        form.getInputProps('email').onChange(e);
        const newEmail = e.currentTarget.value;
        const changed = newEmail.toLowerCase() !== message.recipient.toLowerCase();
        if (changed && !form.values.resend) {
            form.setFieldValue('resend', true);
            if (form.values.resolveAction === 'none') {
                form.setFieldValue('resolveAction', 'auto');
            }
        }
    };

    const getButtonLabel = () => {
        const resend = form.values.resend;
        const resolve = form.values.resolveAction === 'now';
        if (resend && resolve) return t`Resolve & Resend`;
        if (resend) return t`Resend`;
        if (resolve) return t`Resolve`;
        return t`Save`;
    };

    const handleSubmit = (values: { email: string, resend: boolean, resolveAction: string }) => {
        if (values.resend) {
            resendMutation.mutate({
                eventId,
                messageId: message.id!,
                email: emailChanged ? values.email : undefined,
            }, {
                onSuccess: () => {
                    if (values.resolveAction === 'now') {
                        resolveMutation.mutate({eventId, messageId: message.id!}, {
                            onSuccess: () => {
                                onClose();
                                showSuccess(t`Email has been resent and issue marked as resolved.`);
                            },
                            onError: () => {
                                onClose();
                                showSuccess(t`Email has been resent but failed to mark as resolved.`);
                            },
                        });
                    } else {
                        onClose();
                        showSuccess(t`Email has been resent. It will auto-resolve when delivery is confirmed.`);
                    }
                },
                onError: (error) => {
                    formErrorHandler(form, error);
                    showError(t`Failed to resend email. Please try again.`);
                },
            });
        } else if (values.resolveAction === 'now') {
            resolveMutation.mutate({
                eventId,
                messageId: message.id!,
            }, {
                onSuccess: () => {
                    onClose();
                    showSuccess(t`Issue marked as resolved.`);
                },
                onError: () => {
                    showError(t`Failed to resolve issue. Please try again.`);
                },
            });
        } else {
            onClose();
        }
    };

    return (
        <Modal heading={t`Resolve Delivery Issue`} onClose={onClose} opened>
            <FocusTrap active>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <TextInput
                    required
                    type="email"
                    label={t`Recipient Email`}
                    description={t`Updating the email will also update the associated order or attendee record.`}
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

                <Box
                    mt="lg"
                    p="sm"
                    style={{
                        border: '1px solid var(--mantine-color-default-border)',
                        borderRadius: 'var(--mantine-radius-sm)',
                        position: 'relative',
                    }}
                >
                    <Text
                        size="sm"
                        fw={500}
                        style={{
                            position: 'absolute',
                            top: -10,
                            left: 12,
                            backgroundColor: 'var(--mantine-color-body)',
                            padding: '0 6px',
                            lineHeight: 1,
                        }}
                    >
                        {t`Resolution`}
                    </Text>
                    <Radio.Group {...form.getInputProps('resolveAction')}>
                        <Stack gap="xs" mt={4}>
                            <Radio value="none" label={t`No change`} disabled={isPending}/>
                            {form.values.resend && (
                                <Radio value="auto" label={t`Auto-resolve after email success`} disabled={isPending}/>
                            )}
                            <Radio value="now" label={t`Resolve now`} disabled={isPending}/>
                        </Stack>
                    </Radio.Group>
                </Box>

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
                        {getButtonLabel()}
                    </Button>
                </Group>
            </form>
            </FocusTrap>
        </Modal>
    );
};
