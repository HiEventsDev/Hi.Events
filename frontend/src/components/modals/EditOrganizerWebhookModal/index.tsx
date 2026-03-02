import { GenericModalProps, IdParam } from "../../../types.ts";
import { Modal } from "../../common/Modal";
import { t } from "@lingui/macro";
import { WebhookForm } from "../../forms/WebhookForm";
import { useForm } from "@mantine/form";
import { Alert, Button, Center, Loader } from "@mantine/core";
import { showSuccess } from "../../../utilites/notifications.tsx";
import { useParams } from "react-router";
import { useFormErrorResponseHandler } from "../../../hooks/useFormErrorResponseHandler.tsx";
import { useGetOrganizerWebhook } from "../../../queries/useGetOrganizerWebhook.ts";
import { useEditOrganizerWebhook } from "../../../mutations/useEditOrganizerWebhook.ts";
import { useEffect } from "react";
import { OrganizerWebhookRequest } from "../../../api/organizer-webhook.client.ts";

interface EditWebhookModalProps {
    webhookId: IdParam;
}

export const EditOrganizerWebhookModal = ({
    onClose,
    webhookId
}: GenericModalProps & EditWebhookModalProps) => {
    const { organizerId } = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const { data: webhook, error: webhookError, isLoading: webhookLoading } = useGetOrganizerWebhook(organizerId as IdParam, webhookId);
    const form = useForm<OrganizerWebhookRequest>({
        initialValues: {
            url: '',
            event_types: [],
            status: 'ENABLED',
        }
    });
    const editMutation = useEditOrganizerWebhook();

    const handleSubmit = (requestData: OrganizerWebhookRequest) => {
        editMutation.mutate(
            {
                organizerId: organizerId as IdParam,
                webhook: requestData,
                webhookId: webhookId,
            },
            {
                onSuccess: () => {
                    showSuccess(t`Successfully updated Webhook`);
                    onClose();
                },
                onError: (error) => {
                    errorHandler(form, error);
                },
            }
        );
    };

    useEffect(() => {
        if (webhook && webhook.data && webhook.data.data) {
            form.setValues({
                url: webhook.data.data.url,
                event_types: webhook.data.data.event_types,
                status: webhook.data.data.status,
            });
        }
    }, [webhook]);

    return (
        <Modal opened onClose={onClose} heading={t`Edit Webhook`}>
            {webhookLoading && (
                <Center>
                    <Loader />
                </Center>
            )}

            {!!webhookError && (
                <Alert color={'red'}>
                    {t`Failed to load Webhook`}
                </Alert>
            )}

            {webhook && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <WebhookForm form={form as any} />
                    <Button
                        type={'submit'}
                        fullWidth
                        loading={editMutation.isPending}
                    >
                        {t`Edit Webhook`}
                    </Button>
                </form>
            )}
        </Modal>
    );
};
