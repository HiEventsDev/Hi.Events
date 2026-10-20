import { useState } from "react";
import { GenericModalProps, IdParam } from "../../../types";
import {Modal, ModalFooter} from "../../common/Modal";
import { t, Trans } from "@lingui/macro";
import { WebhookForm } from "../../forms/WebhookForm";
import { useForm } from "@mantine/form";
import { Button, TextInput } from "@mantine/core";
import { useCreateOrganizerWebhook } from "../../../mutations/useCreateOrganizerWebhook";
import { showSuccess } from "../../../utilites/notifications";
import { useParams } from "react-router";
import { useFormErrorResponseHandler } from "../../../hooks/useFormErrorResponseHandler";
import { OrganizerWebhookRequest } from "../../../api/organizer-webhook.client.ts";
import { CopyButton } from "../../common/CopyButton";
import { Callout } from "../../common/Callout";

export const CreateOrganizerWebhookModal = ({ onClose }: GenericModalProps) => {
    const { organizerId } = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const [secret, setSecret] = useState<string | null>(null);

    const form = useForm<OrganizerWebhookRequest>({
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

    const createMutation = useCreateOrganizerWebhook();

    const handleSubmit = (requestData: OrganizerWebhookRequest) => {
        createMutation.mutate({
            organizerId: organizerId as IdParam,
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
                <Callout variant="tip">
                    <Trans>This is the only time the signing secret will be shown. Please copy it now and store it securely.</Trans>
                </Callout>
                <TextInput
                    value={secret}
                    readOnly
                    rightSection={<CopyButton value={secret} label={t`Copy signing secret`}/>}
                />
                <ModalFooter>
                    <Button onClick={onClose} data-testid="webhook-done-button">
                        {t`Done`}
                    </Button>
                </ModalFooter>
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
                <WebhookForm form={form as any} />
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button
                        type="submit"
                        loading={createMutation.isPending}
                        data-testid="webhook-submit-button"
                    >
                        {t`Create Webhook`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
}
