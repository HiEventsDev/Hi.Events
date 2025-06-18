import {useEffect, useRef, useState} from "react";
import classes from './HomepageDesigner.module.scss';
import {useParams} from "react-router";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {EventSettings, IdParam} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, ColorInput, Group, TextInput, Accordion, Stack, Text} from "@mantine/core";
import {IconColorPicker, IconHelp, IconPhoto, IconPalette, IconTypography} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {CustomSelect} from "../../../common/CustomSelect";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {eventPreviewPath} from "../../../../utilites/urlHelper.ts";
import {LoadingMask} from "../../../common/LoadingMask";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_EVENT_PUBLIC_QUERY_KEY} from "../../../../queries/useGetEventPublic.ts";

const HomepageDesigner = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const eventImagesQuery = useGetEventImages(eventId);
    const updateMutation = useUpdateEventSettings();

    const iframeRef = useRef<HTMLIFrameElement>(null);
    const lastSentSettings = useRef<Partial<EventSettings> | null>(null);

    const [iframeSrc, setIframeSrc] = useState<string | null>(null);
    const [iframeLoaded, setIframeLoaded] = useState(false);
    const [lastCoverId, setLastCoverId] = useState<IdParam | null>(null);
    const [accordionValue, setAccordionValue] = useState<string[]>(['images', 'colors', 'button']);

    const existingCover = eventImagesQuery.data?.find((image) => image.type === 'EVENT_COVER');

    const form = useForm({
        initialValues: {
            homepage_background_color: '#fff',
            homepage_primary_color: '#444',
            homepage_primary_text_color: '#000000',
            homepage_secondary_color: '#444',
            homepage_secondary_text_color: '#fff',
            homepage_body_background_color: '#fff',
            homepage_background_type: 'COLOR',
            continue_button_text: '',
        }
    });

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            form.setValues({
                homepage_background_color: eventSettingsQuery.data.homepage_background_color || '',
                homepage_primary_color: eventSettingsQuery.data.homepage_primary_color || '',
                homepage_primary_text_color: eventSettingsQuery.data.homepage_primary_text_color || '',
                homepage_secondary_color: eventSettingsQuery.data.homepage_secondary_color || '',
                homepage_secondary_text_color: eventSettingsQuery.data.homepage_secondary_text_color || '',
                homepage_body_background_color: eventSettingsQuery.data.homepage_body_background_color || '',
                homepage_background_type: eventSettingsQuery.data.homepage_background_type || 'COLOR',
                continue_button_text: eventSettingsQuery.data.continue_button_text,
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

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate(
            {eventSettings: values, eventId: eventId},
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
            const settingsToSend = form.values;

            if (JSON.stringify(settingsToSend) !== JSON.stringify(lastSentSettings.current)) {
                iframeRef.current.contentWindow.postMessage(
                    {type: "UPDATE_SETTINGS", settings: settingsToSend},
                    "*"
                );
                lastSentSettings.current = settingsToSend;
            }
        }
    };

    useEffect(() => {
        sendSettingsToIframe();
    }, [iframeLoaded, form.values]);

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
                                <form onSubmit={form.onSubmit(handleSubmit as any)}>
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
                                                form={form}
                                                label={t`Background Type`}
                                                name={'homepage_background_type'}
                                            />

                                            {form.values.homepage_background_type === 'COLOR' && (
                                                <ColorInput
                                                    format="hexa"
                                                    label={t`Page Background Color`}
                                                    description={t`The background color of the entire page`}
                                                    size="sm"
                                                    {...form.getInputProps('homepage_body_background_color')}
                                                />
                                            )}
                                            <ColorInput 
                                                format="hexa"
                                                label={t`Content Background Color`}
                                                description={t`The background color of content areas`}
                                                size="sm"
                                                {...form.getInputProps('homepage_background_color')} 
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
                                    </fieldset>
                                </form>
                            </Accordion.Panel>
                        </Accordion.Item>

                        <Accordion.Item value="button" className={classes.accordionItem}>
                            <Accordion.Control icon={<IconTypography size={20} />}>
                                <Text fw={500}>{t`Button Text`}</Text>
                            </Accordion.Control>
                            <Accordion.Panel>
                                <form onSubmit={form.onSubmit(handleSubmit as any)}>
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
                        onClick={() => form.onSubmit(handleSubmit as any)()}
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
