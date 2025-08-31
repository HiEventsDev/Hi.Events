import {Alert, Divider, LoadingOverlay, Stack, Text, TypographyStylesProvider} from '@mantine/core';
import {IconAlertCircle, IconEye} from '@tabler/icons-react';
import {Trans} from '@lingui/macro';
import classes from './EmailTemplateEditor.module.scss';

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
                        <TypographyStylesProvider>
                            <div
                                dangerouslySetInnerHTML={{
                                    __html: previewData.body
                                }}
                            />
                        </TypographyStylesProvider>
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
