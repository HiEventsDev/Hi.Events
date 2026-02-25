import { GenericModalProps, IdParam } from "../../../types";
import { Modal } from "../../common/Modal";
import { t } from "@lingui/macro";
import { WebhookForm } from "../../forms/WebhookForm";
import { useForm } from "@mantine/form";
import { Button } from "@mantine/core";
import { useCreateOrganizerWebhook } from "../../../mutations/useCreateOrganizerWebhook";
import { showSuccess } from "../../../utilites/notifications";
import { useParams } from "react-router";
import { useFormErrorResponseHandler } from "../../../hooks/useFormErrorResponseHandler";
import { OrganizerWebhookRequest } from "../../../api/organizer-webhook.client.ts";

export const CreateOrganizerWebhookModal = ({ onClose }: GenericModalProps) => {
    const { organizerId } = useParams();
    const errorHandler = useFormErrorResponseHandler();

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
                <WebhookForm form={form as any} />
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
