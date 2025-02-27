import {GenericModalProps, IdParam} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {WebhookForm} from "../../forms/WebhookForm";
import {useForm} from "@mantine/form";
import {Alert, Button, Center, Loader} from "@mantine/core";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetWebhook} from "../../../queries/useGetWebhook.ts";
import {useEditWebhook} from "../../../mutations/useEditWebhook.ts";
import {useEffect} from "react";
import {WebhookRequest} from "../../../api/webhook.client.ts";

interface EditWebhookModalProps {
    webhookId: IdParam;
}

export const EditWebhookModal = ({
                                     onClose,
                                     webhookId
                                 }: GenericModalProps & EditWebhookModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: webhook, error: webhookError, isLoading: webhookLoading} = useGetWebhook(eventId, webhookId);
    const form = useForm<WebhookRequest>({
        initialValues: {
            url: '',
            event_types: [],
            status: 'ENABLED',
        }
    });
    const editMutation = useEditWebhook();

    const handleSubmit = (requestData: WebhookRequest) => {
        editMutation.mutate(
            {
                eventId: eventId,
                webhookData: requestData,
                webhookId: webhookId,
            },
            {
                onSuccess: () => {
                    showSuccess(t`Successfully updated Webhook`);
                    onClose();
                },
                onError: (error) => {
                    console.log(error);
                    errorHandler(form, error);
                },
            }
        );
    };

    useEffect(() => {
        if (webhook) {
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
                    <Loader/>
                </Center>
            )}

            {!!webhookError && (
                <Alert color={'red'}>
                    {t`Failed to load Webhook`}
                </Alert>
            )}

            {webhook && (
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <WebhookForm form={form}/>
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
