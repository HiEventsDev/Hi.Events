import {useState} from 'react';
import {ActionIcon, Alert, Badge, Button, Group, LoadingOverlay, Modal, Paper, Stack, Text} from '@mantine/core';
import {IconEdit, IconInfoCircle, IconMail, IconPlus, IconTrash} from '@tabler/icons-react';
import {useParams} from 'react-router';
import {t, Trans} from '@lingui/macro';
import {useDisclosure} from '@mantine/hooks';
import {useForm} from '@mantine/form';
import {useGetEmailTemplatesForEvent} from '../../../../../../queries/useGetEmailTemplates';
import {useGetDefaultEmailTemplates} from '../../../../../../queries/useGetDefaultEmailTemplates';
import {
    useCreateEmailTemplateForEvent,
} from '../../../../../../mutations/useCreateEmailTemplate';
import {usePreviewEmailTemplateForEvent} from '../../../../../../mutations/usePreviewEmailTemplate';
import {EmailTemplateEditor} from '../../../../../common/EmailTemplateEditor';
import {confirmationDialog} from '../../../../../../utilites/confirmationDialog';
import {showSuccess, showError} from '../../../../../../utilites/notifications';
import {useFormErrorResponseHandler} from '../../../../../../hooks/useFormErrorResponseHandler';
import {
    CreateEmailTemplateRequest,
    EmailTemplate,
    EmailTemplateType,
    UpdateEmailTemplateRequest
} from '../../../../../../types';
import {Card} from '../../../../../common/Card';
import {HeadingWithDescription} from '../../../../../common/Card/CardHeading';
import {useUpdateEmailTemplateForEvent} from "../../../../../../mutations/useUpdateEmailTemplate.ts";
import {useDeleteEmailTemplateForEvent} from "../../../../../../mutations/useDeleteEmailTemplate.ts";

export const EmailTemplateSettings = () => {
    const {eventId} = useParams();
    const [editorOpened, {open: openEditor, close: closeEditor}] = useDisclosure(false);
    const [editingTemplate, setEditingTemplate] = useState<EmailTemplate | null>(null);
    const [editingType, setEditingType] = useState<EmailTemplateType>('order_confirmation');
    const [shouldFetchDefaults, setShouldFetchDefaults] = useState(false);
    const handleFormError = useFormErrorResponseHandler();
    
    // Dummy form for error handling
    const form = useForm({
        initialValues: {},
    });

    // Queries
    const {data: templatesData, isLoading} = useGetEmailTemplatesForEvent(eventId!);
    const {data: defaultTemplatesData} = useGetDefaultEmailTemplates(shouldFetchDefaults);

    // Mutations
    const createMutation = useCreateEmailTemplateForEvent();
    const updateMutation = useUpdateEmailTemplateForEvent();
    const deleteMutation = useDeleteEmailTemplateForEvent();
    const previewMutation = usePreviewEmailTemplateForEvent();

    const templates = templatesData?.data || [];
    const orderConfirmationTemplate = templates.find(t => t.template_type === 'order_confirmation');
    const attendeeTicketTemplate = templates.find(t => t.template_type === 'attendee_ticket');

    const handleCreateTemplate = (type: EmailTemplateType) => {
        setEditingTemplate(null);
        setEditingType(type);
        // Enable fetching default templates if not already fetched
        if (!defaultTemplatesData) {
            setShouldFetchDefaults(true);
        }
        openEditor();
    };

    const handleEditTemplate = (template: EmailTemplate) => {
        setEditingTemplate(template);
        setEditingType(template.template_type);
        openEditor();
    };

    const handleDeleteTemplate = (template: EmailTemplate) => {
        confirmationDialog(
            t`Are you sure you want to delete this template? This action cannot be undone and emails will fall back to the organizer or default template.`,
            () => {
                deleteMutation.mutate(
                    {eventId: eventId!, templateId: template.id},
                    {
                        onSuccess: () => {
                            showSuccess(t`Template deleted successfully`);
                        },
                        onError: (error) => {
                            showError(t`Failed to delete template`);
                        },
                    }
                );
            },
            { confirm: t`Delete Template`, cancel: t`Cancel` }
        );
    };

    const handleSaveTemplate = (data: CreateEmailTemplateRequest | UpdateEmailTemplateRequest) => {
        if (editingTemplate) {
            updateMutation.mutate({
                eventId: eventId!, 
                templateId: editingTemplate.id, 
                templateData: data as UpdateEmailTemplateRequest
            }, {
                onSuccess: () => {
                    showSuccess(t`Template saved successfully`);
                    closeEditor();
                },
                onError: (error) => {
                    handleFormError(form, error, t`Failed to save template`);
                },
            });
        } else {
            createMutation.mutate({
                eventId: eventId!, 
                templateData: data as CreateEmailTemplateRequest
            }, {
                onSuccess: () => {
                    showSuccess(t`Template created successfully`);
                    closeEditor();
                },
                onError: (error) => {
                    handleFormError(form, error, t`Failed to create template`);
                },
            });
        }
    };

    const handlePreviewTemplate = (data: { subject: string; body: string; template_type: EmailTemplateType }) => {
        previewMutation.mutate({eventId: eventId!, previewData: data});
    };

    const templateTypeLabels: Record<EmailTemplateType, string> = {
        'order_confirmation': t`Order Confirmation`,
        'attendee_ticket': t`Attendee Ticket`,
    };

    const templateDescriptions: Record<EmailTemplateType, string> = {
        'order_confirmation': t`Sent to customers when they place an order`,
        'attendee_ticket': t`Sent to each attendee with their ticket details`,
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
                        <Badge
                            size="sm"
                            color={template ? 'blue' : 'gray'}
                            variant="light"
                        >
                            {template ? t`Event custom template` : t`Organizer/default template will be used`}
                        </Badge>
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
                                    color={template.is_active ? 'green' : 'red'}
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
                                color="blue"
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

    return (
        <>
            <Card>
                <HeadingWithDescription
                    heading={t`Email Templates`}
                    description={t`Create custom email templates for this event that override the organizer defaults`}
                />

                <Alert icon={<IconInfoCircle size={16}/>} color="blue" mb="lg">
                    <Text size="sm">
                        <Trans>
                            These templates will override the organizer defaults for this event only.
                            If no custom template is set here, the organizer template will be used instead.
                        </Trans>
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
            </Card>

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
                    defaultTemplate={defaultTemplatesData?.[editingType]}
                    onSave={handleSaveTemplate}
                    onPreview={handlePreviewTemplate}
                    onClose={closeEditor}
                    isSaving={createMutation.isPending || updateMutation.isPending}
                    isPreviewLoading={previewMutation.isPending}
                    previewData={previewMutation.data}
                />
            </Modal>
        </>
    );
};

export default EmailTemplateSettings;
