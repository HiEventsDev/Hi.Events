import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {IdParam} from "../../../../../types.ts";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCreateCrmCampaignTemplate} from "../../../mutations/useCrmCampaignTemplateMutations.ts";
import {hasMessageText} from "../../../utilites/messageBody.ts";
import {Notice} from "../../Notice";

export const SaveTemplateModal = ({organizerId, content, onClose}: {
    organizerId: IdParam,
    content: {subject: string, preheader: string, body: string},
    onClose: () => void,
}) => {
    const createTemplate = useCreateCrmCampaignTemplate();
    const [name, setName] = useState(content.subject.slice(0, 80));
    const hasMessage = hasMessageText(content.body);
    const saveError = createTemplate.isError ? firstApiError(createTemplate.error, t`We couldn't save this template. Please try again.`) : null;
    const isNameError = !!(createTemplate.error as {response?: {data?: {errors?: {name?: unknown}}}} | null)?.response?.data?.errors?.name;

    const save = () => createTemplate.mutate({
        organizerId,
        template: {name: name.trim(), subject: content.subject || null, preheader: content.preheader || null, body: content.body},
    }, {
        onSuccess: () => {
            showSuccess(t`Template saved`);
            onClose();
        },
    });

    return (
        <Modal opened onClose={onClose} heading={t`Save as template`} size="md">
            {saveError && !isNameError && (
                <Notice tone="danger">{saveError}</Notice>
            )}
            {!hasMessage && (
                <Notice>
                    {t`Write a message first. A template can't be saved without one.`}
                </Notice>
            )}
            <TextInput
                label={t`Template name`}
                description={t`Saves the subject, preview text and message. The recipients and event aren't included.`}
                value={name}
                onChange={event => setName(event.currentTarget.value)}
                maxLength={80}
                error={isNameError ? saveError : undefined}
                data-autofocus
                data-testid="template-name"
            />
            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button onClick={save} disabled={!name.trim() || !hasMessage} loading={createTemplate.isPending} data-testid="template-save">
                    {t`Save template`}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
