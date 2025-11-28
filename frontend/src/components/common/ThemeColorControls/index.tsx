import {ColorInput, SegmentedControl, Stack, Text, Group} from "@mantine/core";
import {t} from "@lingui/macro";
import {HomepageThemeSettings} from "../../../types.ts";
import {detectMode} from "../../../utilites/themeUtils.ts";
import {IconSun, IconMoon} from "@tabler/icons-react";
import {useEffect} from "react";

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
                description={t`The background color of the page`}
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
        </Stack>
    );
};

export default ThemeColorControls;
