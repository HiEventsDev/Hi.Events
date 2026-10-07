import {Button, ColorInput, Group, SegmentedControl, Stack, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {HomepageThemeSettings} from "../../../types.ts";
import {detectMode, hasContrastIssues, validateThemeSettings} from "../../../utilites/themeUtils.ts";
import {IconAlertTriangle, IconMoon, IconSun} from "@tabler/icons-react";
import {useEffect, useMemo} from "react";
import classes from "./ThemeColorControls.module.scss";

interface ThemeColorControlsProps {
    values: Partial<HomepageThemeSettings>;
    onChange: (values: Partial<HomepageThemeSettings>) => void;
    hasCoverImage: boolean;
    disabled?: boolean;
}

const ACCENT_SWATCHES = [
    '#8b5cf6',
    '#6366f1',
    '#2563eb',
    '#0891b2',
    '#059669',
    '#ca8a04',
    '#ea580c',
    '#dc2626',
    '#db2777',
    '#111827',
];

const BACKGROUND_SWATCHES = [
    '#ffffff',
    '#f5f3ff',
    '#f0f9ff',
    '#f0fdf4',
    '#fefce8',
    '#fdf2f8',
    '#f4f4f5',
    '#18181b',
    '#0f172a',
    '#000000',
];

const otherMode = (mode: 'light' | 'dark') => mode === 'light' ? 'dark' : 'light';

export const ThemeColorControls = ({
    values,
    onChange,
    hasCoverImage,
    disabled = false,
}: ThemeColorControlsProps) => {
    const handleAccentChange = (accent: string) => {
        onChange({...values, accent});
    };

    const handleBackgroundChange = (background: string) => {
        onChange({...values, background, mode: detectMode(background)});
    };

    const handleModeChange = (mode: string) => {
        onChange({...values, mode: mode as 'light' | 'dark'});
    };

    const handleBackgroundTypeChange = (backgroundType: string) => {
        onChange({...values, background_type: backgroundType as HomepageThemeSettings['background_type']});
    };

    useEffect(() => {
        if (values.background && !values.mode) {
            onChange({...values, mode: detectMode(values.background)});
        }
    }, [values.background]);

    const currentMode = values.mode || 'light';
    const usesCoverImage = values.background_type === 'MIRROR_COVER_IMAGE';

    const contrast = useMemo(() => {
        const validated = validateThemeSettings(values);
        if (!hasContrastIssues(validated)) {
            return {hasIssues: false, fixedBySwitchingMode: false};
        }
        return {
            hasIssues: true,
            fixedBySwitchingMode: !hasContrastIssues({...validated, mode: otherMode(validated.mode)}),
        };
    }, [values.accent, values.background, values.mode]);

    return (
        <Stack gap="md">
            <div>
                <Text size="sm" fw={500} mb={6}>{t`Background`}</Text>
                <SegmentedControl
                    fullWidth
                    size="sm"
                    value={usesCoverImage ? 'MIRROR_COVER_IMAGE' : 'COLOR'}
                    onChange={handleBackgroundTypeChange}
                    disabled={disabled}
                    data={[
                        {label: t`Solid color`, value: 'COLOR'},
                        {label: t`Blurred cover`, value: 'MIRROR_COVER_IMAGE', disabled: !hasCoverImage && !usesCoverImage},
                    ]}
                />
                {!hasCoverImage && (
                    <Text size="xs" c="dimmed" mt={6}>
                        {t`Upload a cover image to use it as the background.`}
                    </Text>
                )}
            </div>

            <ColorInput
                format="hexa"
                label={usesCoverImage ? t`Overlay Color` : t`Background Color`}
                description={usesCoverImage ? t`Tints the blurred cover image behind your page` : undefined}
                size="sm"
                value={values.background || '#f5f3ff'}
                onChange={handleBackgroundChange}
                swatches={BACKGROUND_SWATCHES}
                swatchesPerRow={10}
                disabled={disabled}
            />

            <ColorInput
                format="hexa"
                label={t`Accent Color`}
                description={t`Used for buttons, links and highlights`}
                size="sm"
                value={values.accent || '#8b5cf6'}
                onChange={handleAccentChange}
                swatches={ACCENT_SWATCHES}
                swatchesPerRow={10}
                disabled={disabled}
            />

            <div>
                <Text size="sm" fw={500} mb={2}>{t`Color Mode`}</Text>
                <Text size="xs" c="dimmed" mb={6}>
                    {t`Set automatically from the background color`}
                </Text>
                <SegmentedControl
                    fullWidth
                    size="sm"
                    value={currentMode}
                    onChange={handleModeChange}
                    disabled={disabled}
                    data={[
                        {
                            label: (
                                <Group gap={6} justify="center" wrap="nowrap">
                                    <IconSun size={16}/>
                                    <span>{t`Light`}</span>
                                </Group>
                            ),
                            value: 'light',
                        },
                        {
                            label: (
                                <Group gap={6} justify="center" wrap="nowrap">
                                    <IconMoon size={16}/>
                                    <span>{t`Dark`}</span>
                                </Group>
                            ),
                            value: 'dark',
                        },
                    ]}
                />
            </div>

            {contrast.hasIssues && (
                <div className={classes.contrastWarning}>
                    <IconAlertTriangle size={16} className={classes.contrastIcon}/>
                    <div className={classes.contrastBody}>
                        <Text size="xs" fw={500}>{t`Some text may be hard to read`}</Text>
                        <Text size="xs" c="dimmed">
                            {contrast.fixedBySwitchingMode
                                ? t`Your accent color doesn't stand out enough in this mode.`
                                : t`Try a darker or lighter accent color.`}
                        </Text>
                        {contrast.fixedBySwitchingMode && (
                            <Button
                                size="compact-xs"
                                variant="light"
                                color="yellow"
                                mt={6}
                                disabled={disabled}
                                onClick={() => handleModeChange(otherMode(currentMode))}
                            >
                                {currentMode === 'light' ? t`Switch to dark mode` : t`Switch to light mode`}
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </Stack>
    );
};

export default ThemeColorControls;
