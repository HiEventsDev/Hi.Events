import {useRef} from "react";
import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Editor} from "../../../../../components/common/Editor";
import {LiquidTokenMenu} from "../../../../../components/common/Editor/Controls/LiquidTokenControl";
import {Modal, ModalFooter} from "../../../../../components/common/Modal";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../../types.ts";
import {showSuccess} from "../../../../../utilites/notifications.tsx";
import {useCreateCrmCampaignTemplate, useUpdateCrmCampaignTemplate} from "../../../mutations/useCrmCampaignTemplateMutations.ts";
import {CrmCampaignStarter, CrmCampaignTemplate} from "../../../types.ts";
import {hasMessageText} from "../../../utilites/messageBody.ts";
import {campaignTokens, insertAtCursor} from "../../../utilites/tokens.ts";
import {InsertTokenMenu} from "../../InsertTokenMenu";

export const TemplateModal = ({organizerId, template, starter, onClose}: {
    organizerId: IdParam,
    template?: CrmCampaignTemplate,
    starter?: CrmCampaignStarter,
    onClose: () => void,
}) => {
    const createTemplate = useCreateCrmCampaignTemplate();
    const updateTemplate = useUpdateCrmCampaignTemplate();
    const errorHandler = useFormErrorResponseHandler();
    const subjectInput = useRef<HTMLInputElement>(null);
    const preheaderInput = useRef<HTMLInputElement>(null);
    const tokens = campaignTokens(false);
    const source = template ?? starter;
    const form = useForm({
        initialValues: {
            name: source?.name ?? '',
            subject: source?.subject ?? '',
            preheader: source?.preheader ?? '',
            body: source?.body ?? '',
        },
        validate: {
            name: value => value.trim() ? null : t`Give this template a name`,
            body: value => hasMessageText(value) ? null : t`Write a message`,
        },
    });

    const submit = (values: typeof form.values) => {
        const request = {
            name: values.name.trim(),
            subject: values.subject.trim() || null,
            preheader: values.preheader.trim() || null,
            body: values.body,
        };
        const options = {
            onSuccess: () => {
                showSuccess(template ? t`Template saved` : t`Template created`);
                onClose();
            },
            onError: (error: unknown) => errorHandler(form, error),
        };

        if (template) {
            updateTemplate.mutate({organizerId, templateId: template.id, template: request}, options);
        } else {
            createTemplate.mutate({organizerId, template: request}, options);
        }
    };

    return (
        <Modal opened onClose={onClose} heading={template ? t`Edit template` : t`New template`} size="xl">
            <form onSubmit={form.onSubmit(submit)}>
                <TextInput {...form.getInputProps('name')} label={t`Name`} description={t`Only your team sees this`} maxLength={80} data-testid="template-name"/>
                <TextInput
                    {...form.getInputProps('subject')}
                    label={t`Subject line`}
                    maxLength={200}
                    ref={subjectInput}
                    rightSectionWidth={84}
                    rightSectionPointerEvents="all"
                    rightSection={<InsertTokenMenu tokens={tokens} onInsert={token => form.setFieldValue('subject', insertAtCursor(form.values.subject, token, subjectInput.current))}/>}
                />
                <TextInput
                    {...form.getInputProps('preheader')}
                    label={t`Preview text`}
                    description={t`Shown after the subject in most inboxes`}
                    maxLength={150}
                    ref={preheaderInput}
                    rightSectionWidth={84}
                    rightSectionPointerEvents="all"
                    rightSection={<InsertTokenMenu tokens={tokens} onInsert={token => form.setFieldValue('preheader', insertAtCursor(form.values.preheader, token, preheaderInput.current))}/>}
                />
                <Editor
                    label={t`Message`}
                    value={form.values.body}
                    onChange={value => form.setFieldValue('body', value)}
                    editorType="simple"
                    size="sm"
                    emailContent
                    error={form.errors.body}
                    additionalToolbarControls={<LiquidTokenMenu tokens={tokens}/>}
                />
                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={createTemplate.isPending || updateTemplate.isPending} data-testid="template-save">
                        {template ? t`Save` : t`Create template`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
