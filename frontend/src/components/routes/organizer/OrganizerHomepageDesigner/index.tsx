import {useEffect, useMemo, useRef, useState} from "react";
import classes from './OrganizerHomepageDesigner.module.scss';
import {useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../mutations/useUpdateOrganizerSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {OrganizerSettings} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, Collapse, ColorInput, Group, Text, UnstyledButton} from "@mantine/core";
import {IconCheck, IconChevronDown, IconChevronUp, IconHelp} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {LoadingMask} from "../../../common/LoadingMask";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {organizerPreviewPath} from "../../../../utilites/urlHelper.ts";

interface ColorTheme {
    name: string;
    colors: {
        homepage_background_color: string;
        homepage_content_background_color: string;
        homepage_primary_color: string;
        homepage_primary_text_color: string;
        homepage_secondary_color: string;
        homepage_secondary_text_color: string;
    };
}

const colorThemes: ColorTheme[] = [
    {
        name: t`Modern`,
        colors: {
            homepage_background_color: '#fafbfc',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#2563eb',
            homepage_primary_text_color: '#1e293b',
            homepage_secondary_color: '#64748b',
            homepage_secondary_text_color: '#475569',
        }
    },
    {
        name: t`Ocean`,
        colors: {
            homepage_background_color: '#f0f9ff',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#0891b2',
            homepage_primary_text_color: '#164e63',
            homepage_secondary_color: '#06b6d4',
            homepage_secondary_text_color: '#155e75',
        }
    },
    {
        name: t`Forest`,
        colors: {
            homepage_background_color: '#f0fdf4',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#16a34a',
            homepage_primary_text_color: '#14532d',
            homepage_secondary_color: '#22c55e',
            homepage_secondary_text_color: '#166534',
        }
    },
    {
        name: t`Sunset`,
        colors: {
            homepage_background_color: '#fff7ed',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#f97316',
            homepage_primary_text_color: '#7c2d12',
            homepage_secondary_color: '#fb923c',
            homepage_secondary_text_color: '#92400e',
        }
    },
    {
        name: t`Midnight`,
        colors: {
            homepage_background_color: '#0f0f23',
            homepage_content_background_color: '#1a1a2e',
            homepage_primary_color: '#818cf8',
            homepage_primary_text_color: '#e0e7ff',
            homepage_secondary_color: '#6366f1',
            homepage_secondary_text_color: '#c7d2fe',
        }
    },
    {
        name: t`Royal`,
        colors: {
            homepage_background_color: '#faf5ff',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#9333ea',
            homepage_primary_text_color: '#581c87',
            homepage_secondary_color: '#a855f7',
            homepage_secondary_text_color: '#6b21a8',
        }
    },
    {
        name: t`Coral`,
        colors: {
            homepage_background_color: '#fef2f2',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#ef4444',
            homepage_primary_text_color: '#7f1d1d',
            homepage_secondary_color: '#f87171',
            homepage_secondary_text_color: '#991b1b',
        }
    },
    {
        name: t`Arctic`,
        colors: {
            homepage_background_color: '#f0fdfa',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#14b8a6',
            homepage_primary_text_color: '#134e4a',
            homepage_secondary_color: '#2dd4bf',
            homepage_secondary_text_color: '#115e59',
        }
    },
    {
        name: t`Noir`,
        colors: {
            homepage_background_color: '#09090b',
            homepage_content_background_color: '#18181b',
            homepage_primary_color: '#ef4444',
            homepage_primary_text_color: '#fafafa',
            homepage_secondary_color: '#a1a1aa',
            homepage_secondary_text_color: '#e4e4e7',
        }
    }
];

const OrganizerHomepageDesigner = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const organizerQuery = useGetOrganizer(organizerId);
    const updateMutation = useUpdateOrganizerSettings();

    const organizerData = organizerQuery.data;

    const iframeRef = useRef<HTMLIFrameElement>(null);
    const lastSentSettings = useRef<Partial<OrganizerSettings> | null>(null);

    const [iframeSrc, setIframeSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);
    const [selectedTheme, setSelectedTheme] = useState<string | null>(null);
    const [colorInputsExpanded, setColorInputsExpanded] = useState(false);

    const existingLogo = organizerData?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const existingCover = organizerData?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    const form = useForm({
        initialValues: {
            homepage_background_color: '#ffffff',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#444444',
            homepage_primary_text_color: '#000000',
            homepage_secondary_color: '#444444',
            homepage_secondary_text_color: '#ffffff',
        }
    });

    const formErrorHandle = useFormErrorResponseHandler();

    // Detect if current colors match any preset theme
    const detectedTheme = useMemo(() => {
        const currentColors = form.values;

        // Check if colors match any theme
        const matchingTheme = colorThemes.find(theme =>
            Object.keys(theme.colors).every(key =>
                currentColors[key as keyof typeof currentColors] === theme.colors[key as keyof typeof theme.colors]
            )
        );

        return matchingTheme?.name || 'Custom';
    }, [form.values]);

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            form.setValues({
                homepage_background_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_background_color || '#ffffff',
                homepage_content_background_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_content_background_color || '#ffffff',
                homepage_primary_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_primary_color || '#444444',
                homepage_primary_text_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_primary_text_color || '#000000',
                homepage_secondary_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_secondary_color || '#444444',
                homepage_secondary_text_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_secondary_text_color || '#ffffff',
            });
        }
    }, [organizerSettingsQuery.isFetched, organizerSettingsQuery.data]);

    // Update selected theme and collapse state when colors change
    useEffect(() => {
        setSelectedTheme(detectedTheme);
        setColorInputsExpanded(detectedTheme === 'Custom');
    }, [detectedTheme]);

    useEffect(() => {
        if (organizerSettingsQuery.isFetched && organizerQuery.isFetched && !iframeSrc) {
            setIframeSrc(organizerPreviewPath(organizerId));
        }
    }, [organizerSettingsQuery.isFetched, organizerQuery.isFetched, organizerId]);

    const handleSubmit = (values: any) => {
        updateMutation.mutate(
            {
                organizerSettings: values,
                organizerId: organizerId
            },
            {
                onSuccess: () => {
                    showSuccess(t`Successfully Updated Homepage Design`);
                },
                onError: (error) => {
                    formErrorHandle(form, error);
                },
            }
        );
    };

    const sendSettingsToIframe = () => {
        if (iframeRef.current?.contentWindow && iframeLoaded) {
            const settingsToSend = {
                ...form.values,
                logoUrl: existingLogo?.url,
                coverUrl: existingCover?.url,
            };

            if (JSON.stringify(settingsToSend) !== JSON.stringify(lastSentSettings.current)) {
                iframeRef.current.contentWindow.postMessage(
                    {type: "UPDATE_ORGANIZER_SETTINGS", settings: settingsToSend},
                    "*"
                );
                lastSentSettings.current = settingsToSend;
            }
        }
    };

    useEffect(() => {
        sendSettingsToIframe();
    }, [iframeLoaded, form.values, existingLogo?.url, existingCover?.url]);

    const handleImageChange = () => {
        organizerQuery.refetch();
    };

    const applyTheme = (theme: ColorTheme) => {
        form.setValues(theme.colors);
        setSelectedTheme(theme.name);
        setColorInputsExpanded(false);
    };

    const handleCustomTheme = () => {
        setSelectedTheme('Custom');
        setColorInputsExpanded(true);
    };

    return (
        <div className={classes.container}>
            <div className={classes.sidebar}>
                <div className={classes.sticky}>
                    <h2>{t`Homepage Design`}</h2>

                    <Group justify={'space-between'}>
                        <h3>{t`Cover Image`}</h3>
                        <Tooltip label={t`We recommend dimensions of 2160px by 1080px, and a maximum file size of 5MB`}>
                            <IconHelp size={20}/>
                        </Tooltip>
                    </Group>
                    <div className={classes.imageUploadWrapper}>
                        <ImageUploadDropzone
                            imageType="ORGANIZER_COVER"
                            entityId={organizerId}
                            onUploadSuccess={handleImageChange}
                            existingImageData={{
                                url: existingCover?.url,
                                id: existingCover?.id,
                            }}
                            helpText={t`Cover image will be displayed at the top of your organizer page`}
                            displayMode="compact"
                        />
                    </div>

                    <Group justify={'space-between'}>
                        <h3>{t`Logo`}</h3>
                        <Tooltip label={t`We recommend dimensions of 400px by 400px, and a maximum file size of 5MB`}>
                            <IconHelp size={20}/>
                        </Tooltip>
                    </Group>
                    <div className={classes.imageUploadWrapper}>
                        <ImageUploadDropzone
                            imageType="ORGANIZER_LOGO"
                            entityId={organizerId}
                            onUploadSuccess={handleImageChange}
                            existingImageData={{
                                url: existingLogo?.url,
                                id: existingLogo?.id,
                            }}
                            helpText={t`Logo will be displayed in the header`}
                            displayMode="compact"
                        />
                    </div>

                    <h3>{t`Color Palette`}</h3>

                    {/* Theme presets - subtle presentation */}
                    <div className={classes.themePresets}>
                        <Group gap={12} wrap="wrap">
                            {colorThemes.map((theme) => (
                                <UnstyledButton
                                    key={theme.name}
                                    onClick={() => applyTheme(theme)}
                                    className={classes.themeButton}
                                    data-selected={selectedTheme === theme.name}
                                >
                                    <div className={classes.themeCircle}>
                                        <div
                                            className={classes.themeOuter}
                                            style={{
                                                background: theme.colors.homepage_background_color,
                                            }}
                                        >
                                            <div
                                                className={classes.themeInner}
                                                style={{
                                                    background: theme.colors.homepage_content_background_color,
                                                }}
                                            >
                                                <div
                                                    className={classes.themeDot}
                                                    style={{
                                                        background: theme.colors.homepage_primary_color,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                        {selectedTheme === theme.name && (
                                            <div className={classes.themeCheckmark}>
                                                <IconCheck size={14} stroke={3}/>
                                            </div>
                                        )}
                                    </div>
                                    <Text size="xs" c="dimmed" mt={6}>{theme.name}</Text>
                                </UnstyledButton>
                            ))}
                            {/* Custom option */}
                            <UnstyledButton
                                onClick={handleCustomTheme}
                                className={classes.themeButton}
                                data-selected={selectedTheme === 'Custom'}
                            >
                                <div className={classes.themeCircle}>
                                    <div
                                        className={classes.themeOuter}
                                        style={{
                                            background: `linear-gradient(135deg, ${form.values.homepage_background_color} 50%, ${form.values.homepage_content_background_color} 50%)`,
                                        }}
                                    >
                                        <div
                                            className={classes.themeInner}
                                            style={{
                                                background: 'transparent',
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                            }}
                                        >
                                            <Text size="xs" fw={600} c={form.values.homepage_primary_color}>?</Text>
                                        </div>
                                    </div>
                                    {selectedTheme === 'Custom' && (
                                        <div className={classes.themeCheckmark}>
                                            <IconCheck size={14} stroke={3}/>
                                        </div>
                                    )}
                                </div>
                                <Text size="xs" c="dimmed" mt={6}>{t`Custom`}</Text>
                            </UnstyledButton>
                        </Group>
                    </div>

                    <form onSubmit={form.onSubmit(handleSubmit)}>
                        <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                            {/* Collapsible toggle for color inputs */}
                            {selectedTheme !== 'Custom' && (
                                <Button
                                    variant="subtle"
                                    onClick={() => setColorInputsExpanded(!colorInputsExpanded)}
                                    rightSection={colorInputsExpanded ? <IconChevronUp size={16}/> :
                                        <IconChevronDown size={16}/>}
                                    fullWidth
                                    mb="sm"
                                >
                                    {colorInputsExpanded ? t`Hide color settings` : t`Show color settings`}
                                </Button>
                            )}

                            <Collapse in={colorInputsExpanded}>
                                <div>
                                    <ColorInput
                                        mb="md"
                                        label={t`Page Background Color`}
                                        description={t`The background color of the entire page`}
                                        {...form.getInputProps('homepage_background_color')}
                                    />
                                    <ColorInput
                                        mb="md"
                                        label={t`Content Background Color`}
                                        description={t`The background color of content areas (cards, header, etc.)`}
                                        {...form.getInputProps('homepage_content_background_color')}
                                    />
                                    <ColorInput
                                        mb="md"
                                        label={t`Primary Color`}
                                        {...form.getInputProps('homepage_primary_color')}
                                    />
                                    <ColorInput
                                        mb="md"
                                        label={t`Primary Text Color`}
                                        {...form.getInputProps('homepage_primary_text_color')}
                                    />
                                    <ColorInput
                                        mb="md"
                                        label={t`Secondary Color`}
                                        {...form.getInputProps('homepage_secondary_color')}
                                    />
                                    <ColorInput
                                        mb="md"
                                        label={t`Secondary Text Color`}
                                        {...form.getInputProps('homepage_secondary_text_color')}
                                    />
                                </div>
                            </Collapse>

                            <Button
                                loading={updateMutation.isPending}
                                type={'submit'}
                                mt="md"
                            >
                                {t`Save Changes`}
                            </Button>
                        </fieldset>
                    </form>
                </div>
            </div>

            <div className={classes.previewContainer}>
                <h2>{t`Homepage Preview`}</h2>
                <div className={classes.iframeContainer}>
                    {iframeSrc ? (
                        <iframe
                            ref={iframeRef}
                            src={iframeSrc}
                            title="Organizer Homepage Preview"
                            onLoad={() => setIframeLoaded(true)}
                        />
                    ) : (
                        <LoadingMask/>
                    )}
                </div>
            </div>
        </div>
    );
};

export default OrganizerHomepageDesigner;
