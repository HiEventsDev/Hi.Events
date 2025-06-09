import {useEffect, useMemo, useRef, useState} from "react";
import classes from './OrganizerHomepageDesigner.module.scss';
import {useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../mutations/useUpdateOrganizerSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam, OrganizerSettings} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, Collapse, ColorInput, Group, Text, UnstyledButton} from "@mantine/core";
import {IconCheck, IconChevronDown, IconChevronUp, IconColorPicker, IconHelp, IconPhoto} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {LoadingMask} from "../../../common/LoadingMask";
import {CustomSelect} from "../../../common/CustomSelect";
import {GET_ORGANIZER_QUERY_KEY, useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {organizerPreviewPath} from "../../../../utilites/urlHelper.ts";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_ORGANIZER_PUBLIC_QUERY_KEY} from "../../../../queries/useGetOrganizerPublic.ts";

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
            homepage_background_color: '#2c0838',
            homepage_content_background_color: '#32174f',
            homepage_primary_color: '#c7a2db',
            homepage_primary_text_color: '#ffffff',
            homepage_secondary_color: '#c7a2db',
            homepage_secondary_text_color: '#ffffff',
        }
    },
    {
        name: t`Ocean`,
        colors: {
            homepage_background_color: '#e0f2fe',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#0ea5e9',
            homepage_primary_text_color: '#075985',
            homepage_secondary_color: '#0891b2',
            homepage_secondary_text_color: '#e9f6ff',
        }
    },
    {
        name: t`Forest`,
        colors: {
            homepage_background_color: '#91b89e',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#91b89e',
            homepage_primary_text_color: '#14532d',
            homepage_secondary_color: '#16a34a',
            homepage_secondary_text_color: '#eefff3',
        }
    },
    {
        name: t`Sunset`,
        colors: {
            homepage_background_color: '#fef3c7',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#f97316',
            homepage_primary_text_color: '#7c2d12',
            homepage_secondary_color: '#ea580c',
            homepage_secondary_text_color: '#fad9cd',
        }
    },
    {
        name: t`Midnight`,
        colors: {
            homepage_background_color: '#020617',
            homepage_content_background_color: '#0f172a',
            homepage_primary_color: '#818cf8',
            homepage_primary_text_color: '#e2e8f0',
            homepage_secondary_color: '#94a3b8',
            homepage_secondary_text_color: '#ffffff',
        }
    },
    {
        name: t`Royal`,
        colors: {
            homepage_background_color: '#f3e8ff',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#a855f7',
            homepage_primary_text_color: '#581c87',
            homepage_secondary_color: '#9333ea',
            homepage_secondary_text_color: '#f6eeff',
        }
    },
    {
        name: t`Coral`,
        colors: {
            homepage_background_color: '#ffe4e6',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#f87171',
            homepage_primary_text_color: '#991b1b',
            homepage_secondary_color: '#ef4444',
            homepage_secondary_text_color: '#ffd4d4',
        }
    },
    {
        name: t`Arctic`,
        colors: {
            homepage_background_color: '#ccfbf1',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#14b8a6',
            homepage_primary_text_color: '#134e4a',
            homepage_secondary_color: '#0d9488',
            homepage_secondary_text_color: '#ffffff',
        }
    },
    {
        name: t`Noir`,
        colors: {
            homepage_background_color: '#09090b',
            homepage_content_background_color: '#18181b',
            homepage_primary_color: '#f87171',
            homepage_primary_text_color: '#fafafa',
            homepage_secondary_color: '#a1a1aa',
            homepage_secondary_text_color: '#d4d4d8',
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
    const [lastCoverId, setLastCoverId] = useState<IdParam | null>(null);
    const [lastLogoId, setLastLogoId] = useState<IdParam | null>(null);

    const existingLogo = organizerData?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const existingCover = organizerData?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    const form = useForm({
        initialValues: {
            homepage_background_color: '#fafafa',
            homepage_content_background_color: '#ffffff',
            homepage_primary_color: '#171717',
            homepage_primary_text_color: '#171717',
            homepage_secondary_color: '#737373',
            homepage_secondary_text_color: '#525252',
            homepage_background_type: 'COLOR' as 'COLOR' | 'MIRROR_COVER_IMAGE',
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
                homepage_background_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_background_color || '#fafafa',
                homepage_content_background_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_content_background_color || '#ffffff',
                homepage_primary_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_primary_color || '#171717',
                homepage_primary_text_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_primary_text_color || '#171717',
                homepage_secondary_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_secondary_color || '#737373',
                homepage_secondary_text_color: organizerSettingsQuery.data.homepage_theme_settings?.homepage_secondary_text_color || '#525252',
                homepage_background_type: organizerSettingsQuery.data.homepage_theme_settings?.homepage_background_type || 'COLOR',
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

    useEffect(() => {
        if (((existingCover?.id !== lastCoverId) || existingLogo !== lastLogoId) && iframeSrc) {
            setLastCoverId(existingCover?.id);
            setLastLogoId(existingLogo?.id);
            setIframeSrc(organizerPreviewPath(organizerId) + `?cover_image_id=${existingCover?.id}&logo_image_id=${existingLogo?.id}`);
            setIframeLoaded(false);
        }
    }, [existingCover?.id, existingLogo?.id]);

    const handleImageChange = () => {
        queryClient.invalidateQueries({
            queryKey: [GET_ORGANIZER_PUBLIC_QUERY_KEY, organizerId],
        });
        queryClient.invalidateQueries({
            queryKey: [GET_ORGANIZER_QUERY_KEY, organizerId],
        });
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
                        <Tooltip
                            label={t`We recommend dimensions of 1950px by 650px, a ratio of 3:1, and a maximum file size of 5MB`}>
                            <IconHelp size={20}/>
                        </Tooltip>
                    </Group>
                    <div className={classes.imageUploadWrapper}>
                        <ImageUploadDropzone
                            imageType="ORGANIZER_COVER"
                            entityId={organizerId}
                            onUploadSuccess={handleImageChange}
                            onDeleteSuccess={handleImageChange}
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
                            onDeleteSuccess={handleImageChange}
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
                            <CustomSelect
                                optionList={[
                                    {
                                        icon: <IconColorPicker/>,
                                        label: t`Color`,
                                        value: 'COLOR',
                                        description: t`Choose a color for your background`,
                                    },
                                    {
                                        icon: <IconPhoto/>,
                                        label: t`Use cover image`,
                                        value: 'MIRROR_COVER_IMAGE',
                                        description: t`Use a blurred version of the cover image as the background`,
                                        disabled: !existingCover,
                                    },
                                ]}
                                form={form}
                                label={t`Background Type`}
                                name={'homepage_background_type'}
                            />

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
                                    {form.values.homepage_background_type === 'COLOR' && (
                                        <ColorInput
                                            format="hexa"
                                            mb="md"
                                            label={t`Page Background Color`}
                                            description={t`The background color of the entire page`}
                                            {...form.getInputProps('homepage_background_color')}
                                        />
                                    )}
                                    <ColorInput
                                        format="hexa"
                                        mb="md"
                                        label={t`Content Background Color`}
                                        description={t`The background color of content areas (cards, header, etc.)`}
                                        {...form.getInputProps('homepage_content_background_color')}
                                    />
                                    <ColorInput
                                        format="hexa"
                                        mb="md"
                                        label={t`Primary Color`}
                                        {...form.getInputProps('homepage_primary_color')}
                                    />
                                    <ColorInput
                                        format="hexa"
                                        mb="md"
                                        label={t`Primary Text Color`}
                                        {...form.getInputProps('homepage_primary_text_color')}
                                    />
                                    <ColorInput
                                        mb="md"
                                        format="hexa"
                                        label={t`Secondary Color`}
                                        {...form.getInputProps('homepage_secondary_color')}
                                    />
                                    <ColorInput
                                        format="hexa"
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
