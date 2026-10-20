import {Alert, Divider, LoadingOverlay, Stack, Text} from '@mantine/core';
import {IconAlertCircle, IconEye} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';
import classes from './EmailTemplateEditor.module.scss';

const PREVIEW_STYLES = 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.6;color:#1a1a1a}h1,h2,h3{margin:1.5rem 0 .5rem}h1:first-child,h2:first-child,h3:first-child,p:first-child{margin-top:0}a{color:#213850}img{max-width:100%}';

interface EmailTemplatePreviewPaneProps {
    subject: string;
    previewData?: { subject: string; body: string } | null;
    isLoading?: boolean;
    error?: string | null;
}

export const EmailTemplatePreviewPane = ({
                                             subject,
                                             previewData,
                                             isLoading = false,
                                             error
                                         }: EmailTemplatePreviewPaneProps) => {
    const hasContent = previewData?.subject && previewData?.body;

    return (
        <Stack gap="md">
            <div className={classes.templatePreview}>
                <div className={classes.previewHeader}>
                    <Stack gap="xs">
                        <Text size="sm" fw={500} c="dimmed">
                            <Trans>Email Preview</Trans>
                        </Text>
                        <Text size="lg" fw={600}>
                            {previewData?.subject || subject || <Trans>Subject will appear here</Trans>}
                        </Text>
                    </Stack>
                </div>

                <div className={classes.previewContent} style={{position: 'relative'}}>
                    <LoadingOverlay visible={isLoading}/>

                    {error && (
                        <Alert color="red" icon={<IconAlertCircle size={16}/>}>
                            {error}
                        </Alert>
                    )}

                    {!error && hasContent && (
                        <iframe
                            className={classes.previewFrame}
                            title={t`Email preview`}
                            sandbox=""
                            srcDoc={`<!doctype html><html><head><meta charset="utf-8"><style>${PREVIEW_STYLES}</style></head><body>${previewData.body}</body></html>`}
                        />
                    )}

                    {!error && !hasContent && !isLoading && (
                        <Stack gap="md" align="center" py="xl">
                            <IconEye size={48} color="var(--mantine-color-gray-5)"/>
                            <Text c="dimmed" ta="center">
                                <Trans>Enter a subject and body to see the preview</Trans>
                            </Text>
                        </Stack>
                    )}
                </div>
            </div>

            {previewData && (
                <>
                    <Divider/>
                    <Text size="xs" c="dimmed" fs="italic">
                        <Trans>This preview shows how your email will look with sample data. Actual emails will use real
                            values.</Trans>
                    </Text>
                </>
            )}
        </Stack>
    );
};
