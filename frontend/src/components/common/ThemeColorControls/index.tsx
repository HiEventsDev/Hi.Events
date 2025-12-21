import {ColorInput, SegmentedControl, Stack, Text, Group, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {HomepageThemeSettings} from "../../../types.ts";
import {detectMode, validateThemeSettings, hasContrastIssues} from "../../../utilites/themeUtils.ts";
import {IconSun, IconMoon, IconEyeCheck, IconEyeExclamation} from "@tabler/icons-react";
import {useEffect, useMemo} from "react";

interface ThemeColorControlsProps {
    values: Partial<HomepageThemeSettings>;
    onChange: (values: Partial<HomepageThemeSettings>) => void;
    showBackgroundType?: boolean;
    disabled?: boolean;
}

export const ThemeColorControls = ({
    values,
    onChange,
    disabled = false,
}: ThemeColorControlsProps) => {
    const handleAccentChange = (accent: string) => {
        onChange({...values, accent});
    };

    const handleBackgroundChange = (background: string) => {
        const newMode = detectMode(background);
        onChange({...values, background, mode: newMode});
    };

    const handleModeChange = (mode: string) => {
        onChange({...values, mode: mode as 'light' | 'dark'});
    };

    useEffect(() => {
        if (values.background && !values.mode) {
            const detectedMode = detectMode(values.background);
            onChange({...values, mode: detectedMode});
        }
    }, [values.background]);

    const currentMode = values.mode || 'light';

    const hasIssues = useMemo(() => {
        const validated = validateThemeSettings(values);
        return hasContrastIssues(validated);
    }, [values.accent, values.background, values.mode]);

    return (
        <Stack gap="md">
            <ColorInput
                format="hexa"
                label={t`Accent Color`}
                description={t`The primary brand color used for buttons and highlights`}
                size="sm"
                value={values.accent || '#8b5cf6'}
                onChange={handleAccentChange}
                disabled={disabled}
            />

            <ColorInput
                format="hexa"
                label={t`Background Color`}
                description={t`The background color of the page. When using cover image, this is applied as an overlay.`}
                size="sm"
                value={values.background || '#f5f3ff'}
                onChange={handleBackgroundChange}
                disabled={disabled}
            />

            <div>
                <Text size="sm" fw={500} mb={4}>{t`Color Mode`}</Text>
                <Text size="xs" c="dimmed" mb={8}>
                    {t`Automatically detected based on background color, but can be overridden`}
                </Text>
                <SegmentedControl
                    fullWidth
                    value={currentMode}
                    onChange={handleModeChange}
                    disabled={disabled}
                    data={[
                        {
                            label: (
                                <Group gap={6} justify="center">
                                    <IconSun size={16}/>
                                    <span>{t`Light`}</span>
                                </Group>
                            ),
                            value: 'light',
                        },
                        {
                            label: (
                                <Group gap={6} justify="center">
                                    <IconMoon size={16}/>
                                    <span>{t`Dark`}</span>
                                </Group>
                            ),
                            value: 'dark',
                        },
                    ]}
                />
            </div>

            <Group gap={6} style={{ minHeight: 20 }}>
                {hasIssues ? (
                    <Tooltip label={t`This color combination may be hard to read for some users`} multiline w={220}>
                        <Group gap={6} style={{ cursor: 'help' }}>
                            <IconEyeExclamation size={30} color="var(--mantine-color-yellow-6)" />
                            <Text size="xs" c="yellow.7">{t`Text may be hard to read`}</Text>
                        </Group>
                    </Tooltip>
                ) : (
                    <Group gap={6}>
                        <IconEyeCheck size={30} color="var(--mantine-color-teal-6)" />
                        <Text size="xs" c="dimmed">{t`Good readability`}</Text>
                    </Group>
                )}
            </Group>
        </Stack>
    );
};

export default ThemeColorControls;
