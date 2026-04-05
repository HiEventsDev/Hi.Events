import {GenericModalProps, IdParam, Webhook} from "../../../types";
import {Modal} from "../../common/Modal";
import {t} from "@lingui/macro";
import {WebhookForm} from "../../forms/WebhookForm";
import {useForm} from "@mantine/form";
import {Button, Alert, CopyButton, ActionIcon, Group, Text, Code} from "@mantine/core";
import {useCreateWebhook} from "../../../mutations/useCreateWebhook";
import {showSuccess} from "../../../utilites/notifications";
import {useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler";
import {WebhookRequest} from "../../../api/webhook.client.ts";
import {useState} from "react";
import {IconCopy, IconCheck, IconAlertTriangle} from "@tabler/icons-react";

export const CreateWebhookModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const [createdSecret, setCreatedSecret] = useState<string | null>(null);

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
                const webhook = (response as any)?.data as Webhook;
                if (webhook?.secret) {
                    setCreatedSecret(webhook.secret);
                } else {
                    showSuccess(t`Webhook created successfully`);
                    onClose();
                }
            },
            onError: (error) => errorHandler(form, error),
        });
    }

    if (createdSecret) {
        return (
            <Modal
                opened
                onClose={onClose}
                heading={t`Webhook Created`}
            >
                <Alert
                    icon={<IconAlertTriangle size={16}/>}
                    title={t`Save your signing secret`}
                    color="orange"
                    mb="md"
                >
                    {t`This secret will not be shown again. Copy it now and store it securely. You'll need it to verify webhook signatures.`}
                </Alert>
                <Text size="sm" fw={500} mb={4}>{t`Signing Secret`}</Text>
                <Group gap="xs" mb="lg">
                    <Code block style={{flex: 1, fontSize: 13}}>{createdSecret}</Code>
                    <CopyButton value={createdSecret}>
                        {({copied, copy}) => (
                            <ActionIcon color={copied ? 'teal' : 'gray'} onClick={copy} variant="subtle">
                                {copied ? <IconCheck size={16}/> : <IconCopy size={16}/>}
                            </ActionIcon>
                        )}
                    </CopyButton>
                </Group>
                <Button fullWidth onClick={onClose}>
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
