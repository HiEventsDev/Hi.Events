import {useEffect, useRef, useState} from "react";
import classes from './HomepageDesigner.module.scss';
import {useParams} from "react-router";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {EventSettings, HomepageThemeSettings, IdParam} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, Group, TextInput, Accordion, Stack, Text} from "@mantine/core";
import {IconColorPicker, IconHelp, IconPhoto, IconPalette, IconTypography} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {CustomSelect} from "../../../common/CustomSelect";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {eventPreviewPath} from "../../../../utilites/urlHelper.ts";
import {LoadingMask} from "../../../common/LoadingMask";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_EVENT_PUBLIC_QUERY_KEY} from "../../../../queries/useGetEventPublic.ts";
import {ThemeColorControls} from "../../../common/ThemeColorControls";
import {validateThemeSettings} from "../../../../utilites/themeUtils.ts";

interface FormValues {
    homepage_theme_settings: Partial<HomepageThemeSettings>;
    continue_button_text: string;
}

const HomepageDesigner = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const eventImagesQuery = useGetEventImages(eventId);
    const updateMutation = useUpdateEventSettings();

    const iframeRef = useRef<HTMLIFrameElement>(null);
    const lastSentSettings = useRef<string | null>(null);

    const [iframeSrc, setIframeSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);
    const [lastCoverId, setLastCoverId] = useState<IdParam | null>(null);
    const [accordionValue, setAccordionValue] = useState<string[]>(['images', 'colors', 'button']);

    const existingCover = eventImagesQuery.data?.find((image) => image.type === 'EVENT_COVER');

    const form = useForm<FormValues>({
        initialValues: {
            homepage_theme_settings: {
                accent: '#8b5cf6',
                background: '#f5f3ff',
                mode: 'light',
                background_type: 'COLOR',
            },
            continue_button_text: '',
        }
    });

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            const settings = eventSettingsQuery.data;
            const themeSettings = validateThemeSettings(settings.homepage_theme_settings);

            form.setValues({
                homepage_theme_settings: themeSettings,
                continue_button_text: settings.continue_button_text,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    useEffect(() => {
        if (eventSettingsQuery.isFetched && eventImagesQuery.isFetched && !iframeSrc) {
            setIframeSrc(eventPreviewPath(eventId));
        }
    }, [eventSettingsQuery.isFetched, eventImagesQuery.isFetched]);

    useEffect(() => {
        if (existingCover?.id !== lastCoverId && iframeSrc) {
            setLastCoverId(existingCover?.id);
            setIframeSrc(eventPreviewPath(eventId) + `?cover_image_id=${existingCover?.id}`);
            setIframeLoaded(false);
        }
    }, [existingCover?.id]);

    const handleSubmit = (values: FormValues) => {
        const validatedTheme = validateThemeSettings(values.homepage_theme_settings);

        const eventSettings: Partial<EventSettings> = {
            homepage_theme_settings: validatedTheme,
            continue_button_text: values.continue_button_text,
            // Also update legacy fields for backward compatibility during transition
            homepage_primary_color: validatedTheme.accent,
            homepage_body_background_color: validatedTheme.background,
            homepage_background_type: validatedTheme.background_type,
        };

        updateMutation.mutate(
            {eventSettings, eventId: eventId},
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

    const handleImageChange = () => {
        queryClient.invalidateQueries({
            queryKey: [GET_EVENT_IMAGES_QUERY_KEY, eventId]
        });
        queryClient.invalidateQueries({
            queryKey: [GET_EVENT_PUBLIC_QUERY_KEY, eventId]
        });
    };

    const sendSettingsToIframe = () => {
        if (iframeRef.current?.contentWindow && iframeLoaded) {
            const themeSettings = validateThemeSettings(form.values.homepage_theme_settings);

            const settingsToSend = {
                homepage_theme_settings: themeSettings,
                continue_button_text: form.values.continue_button_text,
            };

            const settingsJson = JSON.stringify(settingsToSend);
            if (settingsJson !== lastSentSettings.current) {
                iframeRef.current.contentWindow.postMessage(
                    {type: "UPDATE_SETTINGS", settings: settingsToSend},
                    "*"
                );
                lastSentSettings.current = settingsJson;
            }
        }
    };

    useEffect(() => {
        sendSettingsToIframe();
    }, [iframeLoaded, form.values]);

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
                        <Text c="dimmed" size="sm">{t`Customize your event page appearance`}</Text>
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
                                            imageType="EVENT_COVER"
                                            entityId={eventId}
                                            onUploadSuccess={handleImageChange}
                                            onDeleteSuccess={handleImageChange}
                                            existingImageData={{
                                                url: existingCover?.url,
                                                id: existingCover?.id,
                                            }}
                                            helpText={t`Cover image will be displayed at the top of your event page`}
                                            displayMode="compact"
                                        />
                                    </div>
                                </Stack>
                            </Accordion.Panel>
                        </Accordion.Item>

                        <Accordion.Item value="colors" className={classes.accordionItem}>
                            <Accordion.Control icon={<IconPalette size={20} />}>
                                <Text fw={500}>{t`Theme & Colors`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <form onSubmit={form.onSubmit(handleSubmit)}>
                                    <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending} className={classes.fieldset}>
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
                                                disabled={eventSettingsQuery.isLoading || updateMutation.isPending}
                                            />
                                        </Stack>
                                    </fieldset>
                                </form>
                            </Accordion.Panel>
                        </Accordion.Item>

                        <Accordion.Item value="button" className={classes.accordionItem}>
                            <Accordion.Control icon={<IconTypography size={20} />}>
                                <Text fw={500}>{t`Button Text`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <form onSubmit={form.onSubmit(handleSubmit)}>
                                    <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending} className={classes.fieldset}>
                                        <Stack gap="md">
                                            <TextInput
                                                label={t`Continue Button Text`}
                                                description={t`Customize the text shown on the continue button`}
                                                placeholder={t`e.g., Get Tickets, Register Now`}
                                                size="sm"
                                                {...form.getInputProps('continue_button_text')}
                                            />
                                        </Stack>
                                    </fieldset>
                                </form>
                            </Accordion.Panel>
                        </Accordion.Item>
                    </Accordion>

                    <Button
                        loading={updateMutation.isPending}
                        type="submit"
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
                            title="Event Preview"
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

export default HomepageDesigner;
