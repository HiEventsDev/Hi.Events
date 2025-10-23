import { Stack, TextInput, Paper, Text } from '@mantine/core';
import { Trans, t } from '@lingui/macro';
import React from "react";

interface CTAConfigurationProps {
    label: string;
    onLabelChange: (label: string) => void;
    error?: string | React.ReactNode;
}

export const CTAConfiguration = ({
    label,
    onLabelChange,
    error
}: CTAConfigurationProps) => {
    return (
        <Paper p="md" withBorder>
            <Stack gap="sm">
                <Text fw={500} size="sm">
                    <Trans>Call-to-Action Button</Trans>
                </Text>
                <Text size="xs" c="dimmed" mb="sm">
                    <Trans>Every email template must include a call-to-action button that links to the appropriate page</Trans>
                </Text>
                
                <TextInput
                    label={<Trans>Button Label</Trans>}
                    placeholder={t`View Order`}
                    value={label}
                    onChange={(event) => onLabelChange(event.currentTarget.value)}
                    error={error}
                    required
                />
            </Stack>
        </Paper>
    );
};
