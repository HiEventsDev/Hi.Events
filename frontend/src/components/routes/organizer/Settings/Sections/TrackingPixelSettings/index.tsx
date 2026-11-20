import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";
import {Checkbox, Switch, TextInput} from "@mantine/core";
import {
    IconBrandFacebook,
    IconBrandGoogle,
    IconBrandTiktok,
    IconTag,
} from "@tabler/icons-react";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {TrackingPixelConfig} from "../../../../../../types.ts";

interface ProviderDef {
    key: string;
    label: string;
    icon: any;
    placeholder: string;
    description: string;
    pattern: RegExp;
    formatHint: string;
}

const PROVIDERS: ProviderDef[] = [
    {
        key: 'facebook_pixel',
        label: 'Facebook Pixel',
        icon: IconBrandFacebook,
        placeholder: '1234567890',
        description: 'Pixel ID (numeric)',
        pattern: /^\d{9,20}$/,
        formatHint: 'Must be 9-20 digits',
    },
    {
        key: 'google_analytics_4',
        label: 'Google Analytics 4',
        icon: IconBrandGoogle,
        placeholder: 'G-XXXXXXXXXX',
        description: 'Measurement ID',
        pattern: /^G-[a-zA-Z0-9]{6,20}$/,
        formatHint: 'Must start with G- followed by 6-20 characters',
    },
    {
        key: 'google_tag_manager',
        label: 'Google Tag Manager',
        icon: IconTag,
        placeholder: 'GTM-XXXXXXX',
        description: 'Container ID',
        pattern: /^GTM-[a-zA-Z0-9]{4,20}$/,
        formatHint: 'Must start with GTM- followed by 4-20 characters',
    },
    {
        key: 'tiktok_pixel',
        label: 'TikTok Pixel',
        icon: IconBrandTiktok,
        placeholder: 'CXXXXXXXXXX',
        description: 'Pixel ID',
        pattern: /^[a-zA-Z0-9]{6,30}$/,
        formatHint: 'Must be 6-30 alphanumeric characters',
    },
];

function pixelsToFormState(pixels: TrackingPixelConfig[] | undefined): Record<string, { enabled: boolean; pixel_id: string }> {
    const state: Record<string, { enabled: boolean; pixel_id: string }> = {};
    for (const p of PROVIDERS) {
        state[p.key] = {enabled: false, pixel_id: ''};
    }
    if (pixels) {
        for (const pixel of pixels) {
            if (state[pixel.provider]) {
                state[pixel.provider] = {enabled: pixel.enabled, pixel_id: pixel.pixel_id};
            }
        }
    }
    return state;
}

function formStateToPixels(state: Record<string, { enabled: boolean; pixel_id: string }>): TrackingPixelConfig[] {
    return Object.entries(state)
        .filter(([, v]) => v.pixel_id.trim() !== '')
        .map(([provider, v]) => ({
            provider,
            pixel_id: v.pixel_id.trim(),
            enabled: v.enabled,
        }));
}

const GTM_PROVIDER_KEY = 'google_tag_manager';

export const TrackingPixelSettings = () => {
    const {organizerId} = useParams();
    const {data: account} = useGetAccount();
    const isSaasMode = account?.is_saas_mode_enabled;
    const availableProviders = isSaasMode
        ? PROVIDERS.filter(p => p.key !== GTM_PROVIDER_KEY)
        : PROVIDERS;
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const formErrorHandle = useFormErrorResponseHandler();

    const form = useForm<{
        pixels: Record<string, { enabled: boolean; pixel_id: string }>;
        tracking_consent_acknowledged: boolean;
    }>({
        initialValues: {
            pixels: pixelsToFormState(undefined),
            tracking_consent_acknowledged: false,
        },
    });

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            const values = {
                pixels: pixelsToFormState(organizerSettingsQuery.data.tracking_pixels),
                tracking_consent_acknowledged: organizerSettingsQuery.data.tracking_consent_acknowledged || false,
            };
            form.setInitialValues(values);
            form.setValues(values);
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        let hasErrors = false;

        for (const provider of availableProviders) {
            const pixelId = values.pixels[provider.key]?.pixel_id.trim();
            if (pixelId && !provider.pattern.test(pixelId)) {
                form.setFieldError(`pixels.${provider.key}.pixel_id`, provider.formatHint);
                hasErrors = true;
            }
        }

        let trackingPixels = formStateToPixels(values.pixels);
        if (isSaasMode) {
            trackingPixels = trackingPixels.filter(p => p.provider !== GTM_PROVIDER_KEY);
        }

        if (trackingPixels.length > 0 && !values.tracking_consent_acknowledged) {
            form.setFieldError('tracking_consent_acknowledged', t`You must acknowledge your responsibilities before saving`);
            hasErrors = true;
        }

        if (hasErrors) return;

        updateMutation.mutate({
            organizerSettings: {
                tracking_pixels: trackingPixels,
                tracking_consent_acknowledged: values.tracking_consent_acknowledged,
            },
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Tracking Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    };

    const hasAnyPixels = availableProviders.some(p => form.values.pixels[p.key]?.pixel_id.trim() !== '');

    return (
        <SettingsPage
            title={t`Tracking & Analytics`}
            description={t`Add tracking pixels to your public event pages and organizer homepage. A cookie consent banner will be shown to visitors when tracking is active.`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                        {availableProviders.map((provider) => {
                            const Icon = provider.icon;
                            const pixelState = form.values.pixels[provider.key];
                            const hasValue = pixelState?.pixel_id.trim() !== '';
                            const inputId = `organizer-tracking-${provider.key}`;

                            return (
                                <SettingsRow
                                    key={provider.key}
                                    label={provider.label}
                                    description={provider.description}
                                    htmlFor={inputId}
                                >
                                    <TextInput
                                        id={inputId}
                                        {...form.getInputProps(`pixels.${provider.key}.pixel_id`)}
                                        placeholder={provider.placeholder}
                                        leftSection={<Icon size={18}/>}
                                        onChange={(e) => {
                                            form.setFieldValue(`pixels.${provider.key}.pixel_id`, e.currentTarget.value);
                                            if (e.currentTarget.value.trim() !== '' && !pixelState?.enabled) {
                                                form.setFieldValue(`pixels.${provider.key}.enabled`, true);
                                            }
                                        }}
                                    />
                                    {hasValue && (
                                        <Switch
                                            checked={pixelState?.enabled ?? false}
                                            onChange={(e) => form.setFieldValue(
                                                `pixels.${provider.key}.enabled`,
                                                e.currentTarget.checked
                                            )}
                                        />
                                    )}
                                </SettingsRow>
                            );
                        })}

                        {hasAnyPixels && (
                            <SettingsRow
                                label={t`I acknowledge my responsibilities as a data controller`}
                                description={t`By adding tracking pixels, you acknowledge that you and this platform are joint controllers of the data collected. You are responsible for ensuring you have a lawful basis for this processing under applicable privacy laws (GDPR, CCPA, etc.).`}
                                htmlFor="organizer-tracking-consent"
                                inlineControl
                            >
                                <Checkbox
                                    id="organizer-tracking-consent"
                                    {...form.getInputProps('tracking_consent_acknowledged', {type: 'checkbox'})}
                                />
                            </SettingsRow>
                        )}
                    </fieldset>
                    <SettingsSaveFooter
                        isDirty={form.isDirty()}
                        isSaving={updateMutation.isPending}
                        onDiscard={form.reset}
                    />
                </form>
            </SettingsSection>
        </SettingsPage>
    );
};
