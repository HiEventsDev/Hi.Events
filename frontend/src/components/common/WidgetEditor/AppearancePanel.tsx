import {ColorInput, NumberInput, SegmentedControl, Slider, Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {NavLink} from "react-router";
import {ThemeFontControl} from "../ThemeFontControl";
import {HomepageThemeSettings} from '../../../types.ts';
import {WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";
import {WidgetColors} from "../../../utilites/widgetTheme.ts";
import {COLOR_REGEX} from "./editorSettings.ts";
import {useAccess} from "../../../hooks/useAccess.ts";
import classes from './WidgetEditor.module.scss';

interface AppearancePanelProps {
    type: WidgetType;
    settings: WidgetSettings;
    update: (patch: Partial<WidgetSettings>) => void;
    isOrganizer: boolean;
    theme?: Partial<HomepageThemeSettings> | null;
    themeColors: WidgetColors;
    homepageDesignerPath: string;
}

export const AppearancePanel = ({type, settings, update, isOrganizer, theme, themeColors, homepageDesignerPath}: AppearancePanelProps) => {
    const {isReadOnly} = useAccess();
    const usesEventTheme = settings.theme === 'event';
    const radius = settings.radius ?? (type === 'BUTTON' ? 10 : 16);

    const setStyle = (style: 'event' | 'custom') => {
        if (style === 'event') {
            update({
                theme: 'event',
                background_color: undefined,
                primary_color: undefined,
                primary_text_color: undefined,
                secondary_color: undefined,
                secondary_text_color: undefined,
                font: undefined,
            });
            return;
        }
        update({
            theme: 'custom',
            background_color: settings.background_color ?? themeColors.background,
            primary_color: settings.primary_color ?? themeColors.primary,
            primary_text_color: settings.primary_text_color ?? themeColors.primaryText,
            secondary_color: settings.secondary_color ?? themeColors.secondary,
            secondary_text_color: settings.secondary_text_color ?? themeColors.secondaryText,
            font: settings.font ?? theme?.font_family ?? undefined,
        });
    };

    return (
        <>
            <SegmentedControl
                fullWidth
                size="sm"
                value={usesEventTheme ? 'event' : 'custom'}
                onChange={(value) => setStyle(value as 'event' | 'custom')}
                aria-label={t`Colours and font`}
                data={[
                    {value: 'event', label: isOrganizer ? t`Match my page` : t`Match event page`},
                    {value: 'custom', label: t`Custom`},
                ]}
                data-testid="widget-style-control"
            />
            {usesEventTheme ? (
                <Text size="xs" c="dimmed">
                    {type === 'BUTTON'
                        ? t`Uses the colours and font from your homepage design. If you change the design, copy the code again to update the button.`
                        : t`Uses the colours and font from your homepage design, and updates when you change them.`}
                    {!isReadOnly && (
                        <>
                            {' '}<NavLink to={homepageDesignerPath} className={classes.inlineLink}>{t`Edit design`}</NavLink>
                        </>
                    )}
                </Text>
            ) : (
                <>
                    <div className={classes.colorGrid}>
                        {([
                            ['secondary_color', t`Buttons`],
                            ['secondary_text_color', t`Button text`],
                            ['background_color', t`Background`],
                            ['primary_text_color', t`Text`],
                            ['primary_color', t`Borders`],
                        ] as const).map(([key, label]) => (
                            <ColorInput
                                key={key}
                                label={label}
                                size="sm"
                                format="hexa"
                                value={settings[key] ?? ''}
                                error={settings[key] && !COLOR_REGEX.test(settings[key] ?? '') ? t`Enter a hex colour, like #ffffff` : undefined}
                                onChange={(value) => update({[key]: value})}
                            />
                        ))}
                    </div>
                    <ThemeFontControl value={settings.font} onChange={(font) => update({font})}/>
                </>
            )}
            <div>
                <div className={classes.sliderHeader}>
                    <Text size="sm" fw={500}>{t`Corner radius`}</Text>
                    <Text size="xs" c="dimmed">{t`${radius}px`}</Text>
                </div>
                <Slider
                    min={0}
                    max={24}
                    step={1}
                    value={radius}
                    onChange={(radius) => update({radius})}
                    label={(value) => `${value}px`}
                    marks={[{value: 0}, {value: 8}, {value: 16}, {value: 24}]}
                    className={classes.slider}
                />
            </div>
            {type !== 'BUTTON' && (
                <NumberInput
                    label={t`Padding`}
                    size="sm"
                    min={0}
                    max={500}
                    rightSection={`px`}
                    allowDecimal={false}
                    value={settings.padding ?? 10}
                    onChange={(value) => update({padding: value === '' ? undefined : Number(value)})}
                    className={classes.narrowInput}
                />
            )}
        </>
    );
};
