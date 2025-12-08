import {useEffect, useRef, useState} from "react";
import classes from './OrganizerHomepageDesigner.module.scss';
import {useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../mutations/useUpdateOrganizerSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {HomepageThemeSettings, IdParam, OrganizerSettings} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Accordion, Button, Group, Stack, Text} from "@mantine/core";
import {IconColorPicker, IconHelp, IconPalette, IconPhoto} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {LoadingMask} from "../../../common/LoadingMask";
import {CustomSelect} from "../../../common/CustomSelect";
import {GET_ORGANIZER_QUERY_KEY, useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {organizerPreviewPath} from "../../../../utilites/urlHelper.ts";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_ORGANIZER_PUBLIC_QUERY_KEY} from "../../../../queries/useGetOrganizerPublic.ts";
import {ThemeColorControls} from "../../../common/ThemeColorControls";
import {computeThemeVariables, validateThemeSettings} from "../../../../utilites/themeUtils.ts";

interface FormValues {
    homepage_theme_settings: Partial<HomepageThemeSettings>;
}

const OrganizerHomepageDesigner = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const organizerQuery = useGetOrganizer(organizerId);
    const updateMutation = useUpdateOrganizerSettings();

    const organizerData = organizerQuery.data;

    const iframeRef = useRef<HTMLIFrameElement>(null);
    const lastSentSettings = useRef<string | null>(null);

    const [iframeSrc, setIframeSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);
    const [accordionValue, setAccordionValue] = useState<string[]>(['images', 'theme']);
    const [lastCoverId, setLastCoverId] = useState<IdParam | null>(null);
    const [lastLogoId, setLastLogoId] = useState<IdParam | null>(null);

    const existingLogo = organizerData?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const existingCover = organizerData?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    const form = useForm<FormValues>({
        initialValues: {
            homepage_theme_settings: {
                accent: '#8b5cf6',
                background: '#f5f3ff',
                mode: 'light',
                background_type: 'COLOR',
            },
        }
    });

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            const settings = organizerSettingsQuery.data;
            const themeSettings = validateThemeSettings(settings.homepage_theme_settings);

            form.setValues({
                homepage_theme_settings: themeSettings,
            });
        }
    }, [organizerSettingsQuery.isFetched, organizerSettingsQuery.data]);

    useEffect(() => {
        if (organizerSettingsQuery.isFetched && organizerQuery.isFetched && !iframeSrc) {
            setIframeSrc(organizerPreviewPath(organizerId));
        }
    }, [organizerSettingsQuery.isFetched, organizerQuery.isFetched, organizerId]);

    const handleSubmit = (values: FormValues) => {
        const validatedTheme = validateThemeSettings(values.homepage_theme_settings);

        const organizerSettings: Partial<OrganizerSettings> = {
            homepage_theme_settings: validatedTheme,
        };

        updateMutation.mutate(
            {
                organizerSettings,
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
            const themeSettings = validateThemeSettings(form.values.homepage_theme_settings);
            const cssVars = computeThemeVariables(themeSettings);

            const settingsToSend = {
                homepage_theme_settings: themeSettings,
                logoUrl: existingLogo?.url,
                coverUrl: existingCover?.url,
                // Include legacy fields for backward compatibility with preview
                homepage_background_color: themeSettings.background,
                homepage_content_background_color: cssVars['--theme-surface'],
                homepage_primary_color: themeSettings.accent,
                homepage_primary_text_color: cssVars['--theme-text-primary'],
                homepage_secondary_color: cssVars['--theme-text-secondary'],
                homepage_secondary_text_color: cssVars['--theme-text-tertiary'],
                homepage_background_type: themeSettings.background_type,
            };

            const settingsJson = JSON.stringify(settingsToSend);
            if (settingsJson !== lastSentSettings.current) {
                iframeRef.current.contentWindow.postMessage(
                    {type: "UPDATE_ORGANIZER_SETTINGS", settings: settingsToSend},
                    "*"
                );
                lastSentSettings.current = settingsJson;
            }
        }
    };

    useEffect(() => {
        sendSettingsToIframe();
    }, [iframeLoaded, form.values, existingLogo?.url, existingCover?.url]);

    useEffect(() => {
        if (((existingCover?.id !== lastCoverId) || existingLogo?.id !== lastLogoId) && iframeSrc) {
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

    const handleThemeChange = (themeSettings: Partial<HomepageThemeSettings>) => {
        form.setFieldValue('homepage_theme_settings', themeSettings);
    };

    const handleBackgroundTypeChange = (backgroundType: string | string[]) => {
        const value = Array.isArray(backgroundType) ? backgroundType[0] : backgroundType;
        form.setFieldValue('homepage_theme_settings', {
            ...form.values.homepage_theme_settings,
            background_type: value as 'COLOR' | 'MIRROR_COVER_IMAGE',
        });
    };

    return (
        <div className={classes.container}>
            <div className={classes.sidebar}>
                <div className={classes.sticky}>
                    <div className={classes.header}>
                        <h2>{t`Homepage Design`}</h2>
                        <Text c="dimmed" size="sm">{t`Customize your organizer page appearance`}</Text>
                    </div>

                    <Accordion
                        multiple
                        value={accordionValue}
                        onChange={setAccordionValue}
                        variant="contained"
                        className={classes.accordion}
                    >
                        <Accordion.Item value="images" className={classes.accordionItem}>
                            <Accordion.Control icon={<IconPhoto size={20}/>}>
                                <Text fw={500}>{t`Images`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <Stack gap="lg">
                                    <div>
                                        <Group justify={'space-between'} mb="xs">
                                            <Text fw={500} size="sm">{t`Cover Image`}</Text>
                                            <Tooltip
                                                label={t`We recommend dimensions of 1950px by 650px, a ratio of 3:1, and a maximum file size of 5MB`}>
                                                <IconHelp size={16} style={{color: 'var(--mantine-color-gray-6)'}}/>
                                            </Tooltip>
                                        </Group>
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

                                    <div>
                                        <Group justify={'space-between'} mb="xs">
                                            <Text fw={500} size="sm">{t`Logo`}</Text>
                                            <Tooltip label={t`We recommend dimensions of 400px by 400px, and a maximum file size of 5MB`}>
                                                <IconHelp size={16} style={{color: 'var(--mantine-color-gray-6)'}}/>
                                            </Tooltip>
                                        </Group>
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
                                </Stack>
                            </Accordion.Panel>
                        </Accordion.Item>

                        <Accordion.Item value="theme" className={classes.accordionItem}>
                            <Accordion.Control icon={<IconPalette size={20}/>}>
                                <Text fw={500}>{t`Theme & Colors`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <form onSubmit={form.onSubmit(handleSubmit)}>
                                    <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}
                                              className={classes.fieldset}>
                                        <Stack gap="md">
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
                                                label={t`Background Type`}
                                                name={'homepage_theme_settings.background_type'}
                                                value={form.values.homepage_theme_settings.background_type || 'COLOR'}
                                                onChange={handleBackgroundTypeChange}
                                            />

                                            <ThemeColorControls
                                                values={form.values.homepage_theme_settings}
                                                onChange={handleThemeChange}
                                                disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}
                                            />
                                        </Stack>
                                    </fieldset>
                                </form>
                            </Accordion.Panel>
                        </Accordion.Item>
                    </Accordion>

                    <Button
                        loading={updateMutation.isPending}
                        type={'submit'}
                        fullWidth
                        mt="md"
                        onClick={() => form.onSubmit(handleSubmit)()}
                    >
                        {t`Save Changes`}
                    </Button>
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
