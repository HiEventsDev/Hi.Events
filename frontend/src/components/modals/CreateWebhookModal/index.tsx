import {GenericModalProps, IdParam} from "../../../types";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {WebhookForm} from "../../forms/WebhookForm";
import {useForm} from "@mantine/form";
import {Button} from "@mantine/core";
import {useCreateWebhook} from "../../../mutations/useCreateWebhook";
import {showSuccess} from "../../../utilites/notifications";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {WebhookRequest} from "../../../api/webhook.client.ts";

export const CreateWebhookModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm<WebhookRequest>({
        initialValues: {
            url: '',
            event_types: [],
            status: 'ENABLED'
        },
        validate: {
            url: (value) => {
                if (!value) return t`URL is required`;
                try {
                    new URL(value);
                    return null;
                } catch {
                    return t`Please enter a valid URL`;
                }
            },
            event_types: (value) => value.length === 0 ? t`At least one event type must be selected` : null,
        }
    });

    const createMutation = useCreateWebhook();

    const handleSubmit = (requestData: WebhookRequest) => {
        console.log(eventId, requestData);
        createMutation.mutate({
            eventId: eventId as IdParam,
            webhook: requestData
        }, {
            onSuccess: () => {
                showSuccess(t`Webhook created successfully`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        });
    }

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Create Webhook`}
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <WebhookForm form={form}/>
                <Button
                    type="submit"
                    fullWidth
                    mt="xl"
                    loading={createMutation.isPending}
                >
                    {t`Create Webhook`}
                </Button>
            </form>
        </Modal>
    );
}
