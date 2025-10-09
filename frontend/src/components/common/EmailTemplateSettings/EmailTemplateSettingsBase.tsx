import {useState} from 'react';
import {ActionIcon, Alert, Badge, Button, Group, LoadingOverlay, Modal, Paper, Stack, Text} from '@mantine/core';
import {IconEdit, IconInfoCircle, IconMail, IconPlus, IconTrash} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';
import {useDisclosure} from '@mantine/hooks';
import {EmailTemplateEditor} from '../EmailTemplateEditor';
import {confirmationDialog} from '../../../utilites/confirmationDialog';
import {showSuccess, showError} from '../../../utilites/notifications';
import {useFormErrorResponseHandler} from '../../../hooks/useFormErrorResponseHandler';
import {
    CreateEmailTemplateRequest,
    EmailTemplate,
    EmailTemplateType,
    UpdateEmailTemplateRequest,
    DefaultEmailTemplate
} from '../../../types';
import {Card} from '../Card';
import {HeadingWithDescription} from '../Card/CardHeading';

interface EmailTemplateSettingsBaseProps {
    // Context 
    contextId: string | number;
    contextType: 'event' | 'organizer';
    
    // Data
    templates: EmailTemplate[];
    defaultTemplates?: Record<EmailTemplateType, DefaultEmailTemplate>;
    isLoading: boolean;
    
    // Mutations
    createMutation: {
        mutate: (params: any, options?: any) => void;
        isPending: boolean;
    };
    updateMutation: {
        mutate: (params: any, options?: any) => void;
        isPending: boolean;
    };
    deleteMutation: {
        mutate: (params: any, options?: any) => void;
        isPending: boolean;
    };
    previewMutation: {
        mutate: (params: any) => void;
        isPending: boolean;
        data?: any;
    };
    
    // Callbacks
    onCreateTemplate?: (type: EmailTemplateType) => void;
    onSaveSuccess?: () => void;
    onDeleteSuccess?: () => void;
    onError?: (error: any, message: string) => void;
}

export const EmailTemplateSettingsBase = ({
    contextId,
    contextType,
    templates,
    defaultTemplates,
    isLoading,
    createMutation,
    updateMutation,
    deleteMutation,
    previewMutation,
    onCreateTemplate,
    onSaveSuccess,
    onDeleteSuccess,
    onError
}: EmailTemplateSettingsBaseProps) => {
    const [editorOpened, {open: openEditor, close: closeEditor}] = useDisclosure(false);
    const [editingTemplate, setEditingTemplate] = useState<EmailTemplate | null>(null);
    const [editingType, setEditingType] = useState<EmailTemplateType>('order_confirmation');
    const handleFormError = useFormErrorResponseHandler();

    const orderConfirmationTemplate = templates.find(t => t.template_type === 'order_confirmation');
    const attendeeTicketTemplate = templates.find(t => t.template_type === 'attendee_ticket');

    const handleCreateTemplate = (type: EmailTemplateType) => {
        setEditingTemplate(null);
        setEditingType(type);
        onCreateTemplate?.(type);
        openEditor();
    };

    const handleEditTemplate = (template: EmailTemplate) => {
        setEditingTemplate(template);
        setEditingType(template.template_type);
        openEditor();
    };

    const handleDeleteTemplate = (template: EmailTemplate) => {
        const fallbackMessage = contextType === 'event' 
            ? t`Are you sure you want to delete this template? This action cannot be undone and emails will fall back to the organizer or default template.`
            : t`Are you sure you want to delete this template? This action cannot be undone and emails will fall back to the default template.`;
            
        confirmationDialog(
            fallbackMessage,
            () => {
                const params = contextType === 'event'
                    ? {eventId: contextId, templateId: template.id}
                    : {organizerId: contextId, templateId: template.id};
                    
                deleteMutation.mutate(params, {
                    onSuccess: () => {
                        showSuccess(t`Template deleted successfully`);
                        onDeleteSuccess?.();
                    },
                    onError: (error: any) => {
                        showError(t`Failed to delete template`);
                        onError?.(error, t`Failed to delete template`);
                    },
                });
            },
            { confirm: t`Delete Template`, cancel: t`Cancel` }
        );
    };

    const handleSaveTemplate = (data: CreateEmailTemplateRequest | UpdateEmailTemplateRequest, editorForm?: any) => {
        if (editingTemplate) {
            const params = contextType === 'event'
                ? {eventId: contextId, templateId: editingTemplate.id, templateData: data as UpdateEmailTemplateRequest}
                : {organizerId: contextId, templateId: editingTemplate.id, templateData: data as UpdateEmailTemplateRequest};
                
            updateMutation.mutate(params, {
                onSuccess: () => {
                    showSuccess(t`Template saved successfully`);
                    closeEditor();
                    onSaveSuccess?.();
                },
                onError: (error: any) => {
                    if (error.response?.data?.errors && editorForm) {
                        const errors = error.response.data.errors;
                        
                        // Check if body field has syntax error
                        if (errors.body) {
                            // Set form error for the body field and show specific message
                            editorForm.setFieldError('body', t`Invalid Liquid syntax. Please correct it and try again.`);
                            showError(t`The template body contains invalid Liquid syntax. Please correct it and try again.`);
                        } else {
                            // Handle other field errors normally
                            handleFormError(editorForm, error, t`Failed to save template`);
                        }
                    } else {
                        showError(t`Failed to save template`);
                    }
                    
                    onError?.(error, t`Failed to save template`);
                },
            });
        } else {
            const params = contextType === 'event'
                ? {eventId: contextId, templateData: data as CreateEmailTemplateRequest}
                : {organizerId: contextId, templateData: data as CreateEmailTemplateRequest};
                
            createMutation.mutate(params, {
                onSuccess: () => {
                    showSuccess(t`Template created successfully`);
                    closeEditor();
                    onSaveSuccess?.();
                },
                onError: (error: any) => {
                    if (error.response?.data?.errors && editorForm) {
                        const errors = error.response.data.errors;
                        
                        // Check if body field has syntax error
                        if (errors.body) {
                            // Set form error for the body field and show specific message
                            editorForm.setFieldError('body', t`Invalid Liquid syntax. Please correct it and try again.`);
                            showError(t`The template body contains invalid Liquid syntax. Please correct it and try again.`);
                        } else {
                            // Handle other field errors normally
                            handleFormError(editorForm, error, t`Failed to save template`);
                        }
                    } else {
                        showError(t`Failed to create template`);
                    }
                    
                    onError?.(error, t`Failed to create template`);
                },
            });
        }
    };

    const handlePreviewTemplate = (data: { subject: string; body: string; template_type: EmailTemplateType; ctaLabel: string }) => {
        const params = contextType === 'event'
            ? {eventId: contextId, previewData: data}
            : {organizerId: contextId, previewData: data};
            
        previewMutation.mutate(params);
    };

    const templateTypeLabels: Record<EmailTemplateType, string> = {
        'order_confirmation': t`Order Confirmation`,
        'attendee_ticket': t`Attendee Ticket`,
    };

    const templateDescriptions: Record<EmailTemplateType, string> = {
        'order_confirmation': t`Sent to customers when they place an order`,
        'attendee_ticket': t`Sent to each attendee with their ticket details`,
    };

    const getTemplateStatusBadge = (template?: EmailTemplate) => {
        if (!template) {
            const fallbackText = contextType === 'event' 
                ? t`Organizer/default template will be used`
                : t`Default template will be used`;
            return (
                <Badge size="sm" variant="light">
                    {fallbackText}
                </Badge>
            );
        }
        
        return (
            <Badge size="sm" variant="light">
                {contextType === 'event' ? t`Event custom template` : t`Custom template`}
            </Badge>
        );
    };

    const TemplateCard = ({
        type,
        template,
        label,
        description
    }: {
        type: EmailTemplateType;
        template?: EmailTemplate;
        label: string;
        description: string;
    }) => (
        <Paper p="md" withBorder>
            <Group justify="space-between" align="flex-start">
                <div style={{flex: 1}}>
                    <Group gap="xs" mb="xs">
                        <IconMail size={16}/>
                        <Text fw={600}>{label}</Text>
                        {getTemplateStatusBadge(template)}
                    </Group>
                    <Text size="sm" c="dimmed" mb="md">
                        {description}
                    </Text>

                    {template && (
                        <Stack gap="xs">
                            <div>
                                <Text size="sm" fw={500}>
                                    <Trans>Subject:</Trans>
                                </Text>
                                <Text size="sm" c="dimmed">
                                    {template.subject}
                                </Text>
                            </div>
                            <div>
                                <Badge
                                    size="xs"
                                    color={template.is_active ? 'teal' : 'red'}
                                    variant="filled"
                                >
                                    {template.is_active ? t`Active` : t`Inactive`}
                                </Badge>
                            </div>
                        </Stack>
                    )}
                </div>

                <Group gap="xs">
                    {template ? (
                        <>
                            <ActionIcon
                                variant="subtle"
                                onClick={() => handleEditTemplate(template)}
                            >
                                <IconEdit size={16}/>
                            </ActionIcon>
                            <ActionIcon
                                variant="subtle"
                                color="red"
                                onClick={() => handleDeleteTemplate(template)}
                                loading={deleteMutation.isPending}
                            >
                                <IconTrash size={16}/>
                            </ActionIcon>
                        </>
                    ) : (
                        <Button
                            size="xs"
                            leftSection={<IconPlus size={16}/>}
                            onClick={() => handleCreateTemplate(type)}
                        >
                            <Trans>Create Custom Template</Trans>
                        </Button>
                    )}
                </Group>
            </Group>
        </Paper>
    );

    const getHeadingDescription = () => {
        if (contextType === 'event') {
            return t`Create custom email templates for this event that override the organizer defaults`;
        }
        return t`Customize the emails sent to your customers using Liquid templating. These templates will be used as defaults for all events in your organization.`;
    };

    const getAlertMessage = () => {
        if (contextType === 'event') {
            return (
                <Trans>
                    These templates will override the organizer defaults for this event only.
                    If no custom template is set here, the organizer template will be used instead.
                </Trans>
            );
        }
        return (
            <Trans>
                These templates will be used as defaults for all events in your organization.
                Individual events can override these templates with their own custom versions.
            </Trans>
        );
    };

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Email Templates`}
                description={getHeadingDescription()}
            />

            <Alert icon={<IconInfoCircle size={16}/>} variant="light" mb="lg">
                <Text size="sm">
                    {getAlertMessage()}
                </Text>
            </Alert>

            <div style={{position: 'relative'}}>
                <LoadingOverlay visible={isLoading}/>

                <Stack gap="md">
                    <TemplateCard
                        type="order_confirmation"
                        template={orderConfirmationTemplate}
                        label={templateTypeLabels.order_confirmation}
                        description={templateDescriptions.order_confirmation}
                    />

                    <TemplateCard
                        type="attendee_ticket"
                        template={attendeeTicketTemplate}
                        label={templateTypeLabels.attendee_ticket}
                        description={templateDescriptions.attendee_ticket}
                    />
                </Stack>
            </div>

            <Modal
                opened={editorOpened}
                onClose={closeEditor}
                size="xl"
                withCloseButton={false}
                padding="xl"
            >
                <EmailTemplateEditor
                    templateType={editingType}
                    template={editingTemplate || undefined}
                    defaultTemplate={defaultTemplates?.[editingType]}
                    onSave={handleSaveTemplate}
                    onPreview={handlePreviewTemplate}
                    onClose={closeEditor}
                    isSaving={createMutation.isPending || updateMutation.isPending}
                    isPreviewLoading={previewMutation.isPending}
                    previewData={previewMutation.data}
                />
            </Modal>
        </Card>
    );
};
