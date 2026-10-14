import {useEffect, useState} from "react";
import {Alert, Badge, Button, Group, LoadingOverlay, Stack, Switch, Text, TextInput, Title, Tooltip} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useDebouncedValue} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {IconAlertCircle, IconDeviceFloppy, IconSend} from "@tabler/icons-react";
import {AdminLifecycleEmail, LifecycleEmailContent, LifecycleEmailPreview} from "../../../../api/admin.client";
import {Editor} from "../../../common/Editor";
import {LiquidTokenMenu} from "../../../common/Editor/Controls/LiquidTokenControl";
import {useUpdateLifecycleEmail} from "../../../../mutations/useUpdateLifecycleEmail";
import {usePreviewLifecycleEmail} from "../../../../mutations/usePreviewLifecycleEmail";
import {useSendTestLifecycleEmail} from "../../../../mutations/useSendTestLifecycleEmail";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler";
import {showError, showSuccess} from "../../../../utilites/notifications";
import {relativeDate} from "../../../../utilites/dates";
import {getLifecycleEmailLabel} from "./lifecycleEmailLabels";
import classes from "./LifecycleEmails.module.scss";

interface LifecycleEmailEditorProps {
    email: AdminLifecycleEmail;
    sendingConfigured: boolean;
    onDirtyChange: (isDirty: boolean) => void;
}

interface FormValues extends LifecycleEmailContent {
    enabled: boolean;
}

const firstErrorMessage = (error: any): string => {
    const errors = error?.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors)[0];
        return Array.isArray(first) ? String(first[0]) : String(first);
    }
    return error?.response?.data?.message ?? t`The preview could not be rendered`;
};

export const LifecycleEmailEditor = ({email, sendingConfigured, onDirtyChange}: LifecycleEmailEditorProps) => {
    const label = getLifecycleEmailLabel(email.nudge_type);
    const updateMutation = useUpdateLifecycleEmail();
    const previewMutation = usePreviewLifecycleEmail();
    const testMutation = useSendTestLifecycleEmail();
    const errorHandler = useFormErrorResponseHandler();
    const [preview, setPreview] = useState<LifecycleEmailPreview | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);

    const form = useForm<FormValues>({
        initialValues: {
            subject: email.subject,
            body: email.body,
            cta_label: email.cta_label,
            enabled: !!email.enabled_at,
        },
        validate: {
            subject: (value) => (!value.trim() ? t`Subject is required` : null),
            body: (value) => (!value.trim() ? t`Body is required` : null),
            cta_label: (value) => (!value.trim() ? t`Link text is required` : null),
        },
    });

    const content: LifecycleEmailContent = {
        subject: form.values.subject,
        body: form.values.body,
        cta_label: form.values.cta_label,
    };
    const [debouncedContent] = useDebouncedValue(JSON.stringify(content), 500);
    const isDirty = form.isDirty();

    useEffect(() => {
        onDirtyChange(isDirty);
    }, [isDirty]);

    useEffect(() => {
        const data: LifecycleEmailContent = JSON.parse(debouncedContent);

        if (!data.subject.trim() || !data.body.trim() || !data.cta_label.trim()) {
            return;
        }

        previewMutation.mutate({nudgeType: email.nudge_type, data}, {
            onSuccess: ({data: rendered}) => {
                setPreview(rendered);
                setPreviewError(null);
            },
            onError: (error) => setPreviewError(firstErrorMessage(error)),
        });
    }, [debouncedContent]);

    const handleSave = (values: FormValues) => {
        if (values.enabled && !email.enabled_at
            && !window.confirm(t`Turn this email on? Organizers who match the trigger from now on will start receiving it.`)) {
            return;
        }

        updateMutation.mutate({nudgeType: email.nudge_type, data: values}, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(values.enabled ? t`Email saved and sending` : t`Email saved`);
            },
            onError: (error) => errorHandler(form, error),
        });
    };

    const handleSendTest = () => {
        if (form.validate().hasErrors) {
            return;
        }

        testMutation.mutate({nudgeType: email.nudge_type, data: content}, {
            onSuccess: () => showSuccess(t`Test email sent to your inbox`),
            onError: (error) => showError(firstErrorMessage(error)),
        });
    };

    return (
        <form onSubmit={form.onSubmit(handleSave)} className={classes.workspace}>
            <div className={classes.workspaceHeader}>
                <Stack gap={4}>
                    <Group gap="xs">
                        <Title order={3}>{label.name}</Title>
                        {email.enabled_at ? (
                            <Badge color="green" variant="light">{t`Live since ${relativeDate(email.enabled_at)}`}</Badge>
                        ) : (
                            <Badge color="gray" variant="light">{t`Off`}</Badge>
                        )}
                    </Group>
                    <Text size="sm" c="dimmed">{label.trigger}</Text>
                </Stack>
                <Switch
                    size="md"
                    label={t`Sending`}
                    data-testid="lifecycle-email-enabled-switch"
                    {...form.getInputProps('enabled', {type: 'checkbox'})}
                />
            </div>

            <div className={classes.panes}>
                <div className={classes.editorPane}>
                    <Stack gap="md">
                        <TextInput
                            label={t`Subject`}
                            required
                            {...form.getInputProps('subject')}
                        />
                        <Editor
                            label={t`Body`}
                            description={t`Insert personalised tokens from the braces button in the toolbar.`}
                            value={form.values.body}
                            onChange={(value: string) => form.setFieldValue('body', value)}
                            error={form.errors.body}
                            editorType="full"
                            additionalToolbarControls={<LiquidTokenMenu tokens={email.tokens}/>}
                        />
                        <TextInput
                            label={t`Link text`}
                            description={t`Links to: ${label.destination}`}
                            required
                            {...form.getInputProps('cta_label')}
                        />
                    </Stack>
                </div>

                <div className={classes.previewPane}>
                    <Stack gap="xs">
                        <Text size="xs" fw={600} tt="uppercase" c="dimmed">{t`Preview with sample data`}</Text>
                        {previewError && (
                            <Alert color="red" variant="light" icon={<IconAlertCircle size={16}/>}>
                                {previewError}
                            </Alert>
                        )}
                        <div className={classes.inbox}>
                            <LoadingOverlay visible={previewMutation.isPending && !preview} overlayProps={{blur: 1}}/>
                            <div className={classes.inboxHeader}>
                                <Text size="xs" c="dimmed">{t`Subject`}</Text>
                                <Text fw={600} lineClamp={2}>{preview?.subject ?? form.values.subject}</Text>
                            </div>
                            <iframe
                                className={classes.inboxFrame}
                                title={t`Email preview`}
                                sandbox=""
                                srcDoc={preview?.html ?? ''}
                            />
                        </div>
                    </Stack>
                </div>
            </div>

            <div className={classes.footer}>
                <Text size="sm" c="dimmed">
                    {t`Each account receives this email at most once.`}
                </Text>
                <Group gap="sm">
                    <Tooltip
                        label={t`Configure the lifecycle mailer to send test emails`}
                        disabled={sendingConfigured}
                    >
                        <Button
                            variant="default"
                            leftSection={<IconSend size={16}/>}
                            onClick={handleSendTest}
                            loading={testMutation.isPending}
                            disabled={!sendingConfigured}
                            data-testid="lifecycle-email-test-button"
                        >
                            {t`Send test to me`}
                        </Button>
                    </Tooltip>
                    <Button
                        type="submit"
                        leftSection={<IconDeviceFloppy size={16}/>}
                        loading={updateMutation.isPending}
                        disabled={!isDirty}
                        data-testid="lifecycle-email-save-button"
                    >
                        {t`Save`}
                    </Button>
                </Group>
            </div>
        </form>
    );
};
