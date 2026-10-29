import {SegmentedControl, Select, Switch, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {availableLocales, getLocaleName, SupportedLocales} from "../../../locales.ts";
import {WidgetCheckoutMode, WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";

interface BehaviourPanelProps {
    type: WidgetType;
    settings: WidgetSettings;
    update: (patch: Partial<WidgetSettings>) => void;
}

export const BehaviourPanel = ({type, settings, update}: BehaviourPanelProps) => (
        <>
            {type !== 'LINEUP' && type !== 'SCHEDULE' && (
                <>
                    <div>
                        <Text size="sm" fw={500} mb={6}>{t`Checkout opens in`}</Text>
                        <SegmentedControl
                            fullWidth
                            size="sm"
                            value={settings.checkout ?? 'modal'}
                            onChange={(value) => update({checkout: value as WidgetCheckoutMode})}
                            aria-label={t`Checkout opens in`}
                            data={[
                                {value: 'modal', label: t`Pop-up on your site`},
                                {value: 'new-tab', label: t`New tab`},
                            ]}
                        />
                    </div>
                    <TextInput
                        label={t`Continue button text`}
                        size="sm"
                        placeholder={t`Continue`}
                        maxLength={100}
                        value={settings.continue_button_text ?? ''}
                        onChange={(event) => update({continue_button_text: event.currentTarget.value})}
                    />
                </>
            )}
            <Select
                label={t`Language`}
                description={t`By default the widget follows each visitor's browser language.`}
                size="sm"
                placeholder={t`Visitor's language`}
                clearable
                data={availableLocales.map(locale => ({value: locale, label: getLocaleName(locale as SupportedLocales)}))}
                value={settings.locale ?? null}
                onChange={(value) => update({locale: value ?? undefined})}
            />
            {type !== 'BUTTON' && (
                <Switch
                    label={t`Fit height to content`}
                    description={t`Turn off to set the height yourself: give the widget element a height on your page and the widget fills it.`}
                    checked={settings.autoresize !== false}
                    onChange={(event) => update({autoresize: event.currentTarget.checked})}
                />
            )}
        </>
);
