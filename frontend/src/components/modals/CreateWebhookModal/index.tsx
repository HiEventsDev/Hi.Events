import {useState} from "react";
import {GenericModalProps, IdParam} from "../../../types";
import {Modal} from "../../common/Modal";
import {t, Trans} from "@lingui/macro";
import {WebhookForm} from "../../forms/WebhookForm";
import {useForm} from "@mantine/form";
import {Alert, Button, Group, TextInput} from "@mantine/core";
import {useCreateWebhook} from "../../../mutations/useCreateWebhook";
import {showSuccess} from "../../../utilites/notifications";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {WebhookRequest} from "../../../api/webhook.client.ts";
import {CopyButton} from "../../common/CopyButton";
import {IconInfoCircle} from "@tabler/icons-react";

export const CreateWebhookModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const [secret, setSecret] = useState<string | null>(null);

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
        createMutation.mutate({
            eventId: eventId as IdParam,
            webhook: requestData
        }, {
            onSuccess: (response) => {
                showSuccess(t`Webhook created successfully`);
                setSecret(response.data.data.secret);
            },
            onError: (error) => errorHandler(form, error),
        });
    }

    if (secret) {
        return (
            <Modal
                opened
                onClose={onClose}
                heading={t`Webhook Signing Secret`}
            >
                <Alert icon={<IconInfoCircle/>} color="yellow" mb="md">
                    <Trans>This is the only time the signing secret will be shown. Please copy it now and store it securely.</Trans>
                </Alert>
                <TextInput
                    value={secret}
                    readOnly
                    rightSection={<CopyButton value={secret}/>}
                />
                <Button fullWidth mt="xl" onClick={onClose}>
                    {t`Done`}
                </Button>
            </Modal>
        );
    }

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Create Webhook`}
        >
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <WebhookForm form={form} isEventContext/>
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
