import {useEffect, useMemo, useRef, useState} from "react";
import classes from './OrganizerHomepageDesigner.module.scss';
import {useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../mutations/useUpdateOrganizerSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam, OrganizerSettings, ColorTheme} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, Collapse, ColorInput, Group, Text, UnstyledButton, Accordion, Stack} from "@mantine/core";
import {IconCheck, IconChevronDown, IconChevronUp, IconColorPicker, IconHelp, IconPhoto, IconPalette} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {LoadingMask} from "../../../common/LoadingMask";
import {CustomSelect} from "../../../common/CustomSelect";
import {GET_ORGANIZER_QUERY_KEY, useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {organizerPreviewPath} from "../../../../utilites/urlHelper.ts";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_ORGANIZER_PUBLIC_QUERY_KEY} from "../../../../queries/useGetOrganizerPublic.ts";
import {useGetColorThemes} from "../../../../queries/useGetColorThemes.ts";

const OrganizerHomepageDesigner = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const organizerQuery = useGetOrganizer(organizerId);
    const colorThemesQuery = useGetColorThemes();
    const updateMutation = useUpdateOrganizerSettings();

    const organizerData = organizerQuery.data;

    const iframeRef = useRef<HTMLIFrameElement>(null);
    const lastSentSettings = useRef<Partial<OrganizerSettings> | null>(null);

    const [iframeSrc, setIframeSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);
    const [selectedTheme, setSelectedTheme] = useState<string | null>(null);
    const [colorInputsExpanded, setColorInputsExpanded] = useState(false);
    const [accordionValue, setAccordionValue] = useState<string[]>(['images']);
    const [lastCoverId, setLastCoverId] = useState<IdParam | null>(null);
    const [lastLogoId, setLastLogoId] = useState<IdParam | null>(null);

    const existingLogo = organizerData?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const existingCover = organizerData?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    const form = useForm({
        initialValues: {
            homepage_background_color: '#fafafa',
            homepage_content_background_color: '#ffffffbf',
            homepage_primary_color: '#171717',
            homepage_primary_text_color: '#171717',
            homepage_secondary_color: '#737373',
            homepage_secondary_text_color: '#525252',
            homepage_background_type: 'COLOR' as 'COLOR' | 'MIRROR_COVER_IMAGE',
        }
    });

    const formErrorHandle = useFormErrorResponseHandler();

    const detectedTheme = useMemo(() => {
        if (!colorThemesQuery.data) return 'Custom';
        
        const currentColors = form.values;

        // Check if colors match any theme
        const matchingTheme = colorThemesQuery.data.find(theme =>
            theme.homepage_background_color === currentColors.homepage_background_color &&
            theme.homepage_content_background_color === currentColors.homepage_content_background_color &&
            theme.homepage_primary_color === currentColors.homepage_primary_color &&
            theme.homepage_primary_text_color === currentColors.homepage_primary_text_color &&
            theme.homepage_secondary_color === currentColors.homepage_secondary_color &&
            theme.homepage_secondary_text_color === currentColors.homepage_secondary_text_color
        );

        return matchingTheme?.name || 'Custom';
    }, [form.values, colorThemesQuery.data]);

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
        form.setValues({
            homepage_background_color: theme.homepage_background_color,
            homepage_content_background_color: theme.homepage_content_background_color,
            homepage_primary_color: theme.homepage_primary_color,
            homepage_primary_text_color: theme.homepage_primary_text_color,
            homepage_secondary_color: theme.homepage_secondary_color,
            homepage_secondary_text_color: theme.homepage_secondary_text_color,
        });
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
                            <Accordion.Control icon={<IconPhoto size={20} />}>
                                <Text fw={500}>{t`Images`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <Stack gap="lg">
                                    <div>
                                        <Group justify={'space-between'} mb="xs">
                                            <Text fw={500} size="sm">{t`Cover Image`}</Text>
                                            <Tooltip
                                                label={t`We recommend dimensions of 1950px by 650px, a ratio of 3:1, and a maximum file size of 5MB`}>
                                                <IconHelp size={16} style={{ color: 'var(--mantine-color-gray-6)' }}/>
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
                                                <IconHelp size={16} style={{ color: 'var(--mantine-color-gray-6)' }}/>
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
                            <Accordion.Control icon={<IconPalette size={20} />}>
                                <Text fw={500}>{t`Theme & Colors`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <Stack gap="lg">

                                    <div>
                                        <Text fw={500} size="sm" mb="xs">{t`Color Presets`}</Text>
                                        <Text size="xs" c="dimmed" mb="md">{t`Choose from predefined color schemes`}</Text>
                                        
                                        <div className={classes.themePresets}>
                                            <Group gap={12} wrap="wrap">
                                                {colorThemesQuery.isLoading && (
                                                    <Text size="sm" c="dimmed">{t`Loading themes...`}</Text>
                                                )}
                                                {colorThemesQuery.data?.map((theme) => (
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
                                                                    background: theme.homepage_background_color,
                                                                }}
                                                            >
                                                                <div
                                                                    className={classes.themeInner}
                                                                    style={{
                                                                        background: theme.homepage_content_background_color,
                                                                    }}
                                                                >
                                                                    <div
                                                                        className={classes.themeDot}
                                                                        style={{
                                                                            background: theme.homepage_primary_color,
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
                                    </div>

                                    <form onSubmit={form.onSubmit(handleSubmit)}>
                                        <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending} className={classes.fieldset}>
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
                                                    form={form}
                                                    label={t`Background Type`}
                                                    name={'homepage_background_type'}
                                                />

                                                {selectedTheme !== 'Custom' && (
                                                    <Button
                                                        variant="light"
                                                        onClick={() => setColorInputsExpanded(!colorInputsExpanded)}
                                                        rightSection={colorInputsExpanded ? <IconChevronUp size={16}/> :
                                                            <IconChevronDown size={16}/>}
                                                        fullWidth
                                                        size="sm"
                                                    >
                                                        {colorInputsExpanded ? t`Hide color settings` : t`Show color settings`}
                                                    </Button>
                                                )}

                                                <Collapse in={colorInputsExpanded}>
                                                    <Stack gap="sm">
                                                        {form.values.homepage_background_type === 'COLOR' && (
                                                            <ColorInput
                                                                format="hexa"
                                                                label={t`Page Background Color`}
                                                                description={t`The background color of the entire page`}
                                                                size="sm"
                                                                {...form.getInputProps('homepage_background_color')}
                                                            />
                                                        )}
                                                        <ColorInput
                                                            format="hexa"
                                                            label={t`Content Background Color`}
                                                            description={t`The background color of content areas (cards, header, etc.)`}
                                                            size="sm"
                                                            {...form.getInputProps('homepage_content_background_color')}
                                                        />
                                                        <ColorInput
                                                            format="hexa"
                                                            label={t`Primary Color`}
                                                            size="sm"
                                                            {...form.getInputProps('homepage_primary_color')}
                                                        />
                                                        <ColorInput
                                                            format="hexa"
                                                            label={t`Primary Text Color`}
                                                            size="sm"
                                                            {...form.getInputProps('homepage_primary_text_color')}
                                                        />
                                                        <ColorInput
                                                            format="hexa"
                                                            label={t`Secondary Color`}
                                                            size="sm"
                                                            {...form.getInputProps('homepage_secondary_color')}
                                                        />
                                                        <ColorInput
                                                            format="hexa"
                                                            label={t`Secondary Text Color`}
                                                            size="sm"
                                                            {...form.getInputProps('homepage_secondary_text_color')}
                                                        />
                                                    </Stack>
                                                </Collapse>
                                            </Stack>
                                        </fieldset>
                                    </form>
                                </Stack>
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
