import {useEffect, useState} from "react";
import classes from './TicketDesigner.module.scss';
import {useParams} from "react-router";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, ColorInput, Textarea, Accordion, Stack, Text, Group, Tabs, Switch} from "@mantine/core";
import {IconColorSwatch, IconHelp, IconPrinter} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {LoadingMask} from "../../../common/LoadingMask";
import {TicketPreview} from "./TicketPreview";
import {QRPlacementCanvas, QRPlacementValue} from "../../../common/QRPlacementCanvas";

interface TicketDesignSettings {
    accent_color: string;
    logo_image_id: IdParam;
    footer_text: string | null;
    enabled: boolean;
}

const TicketDesigner = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const eventImagesQuery = useGetEventImages(eventId);
    const updateMutation = useUpdateEventSettings();

    const [accordionValue, setAccordionValue] = useState<string[]>(['design']);

    const existingLogo = eventImagesQuery.data?.find((image) => image.type === 'TICKET_LOGO');
    const existingTemplateImage = eventImagesQuery.data?.find((image) => image.type === 'TICKET_TEMPLATE');

    const form = useForm<TicketDesignSettings>({
        initialValues: {
            accent_color: '#333333',
            logo_image_id: undefined,
            footer_text: '',
            enabled: true,
        }
    });

    const [customTemplate, setCustomTemplate] = useState<{
        use_custom_template: boolean;
        template_image_id: number | null;
        qr_x: number;
        qr_y: number;
        qr_size: number;
        num_x: number | null;
        num_y: number | null;
    }>({
        use_custom_template: false,
        template_image_id: null,
        qr_x: 0,
        qr_y: 0,
        qr_size: 120,
        num_x: null,
        num_y: null,
    });
    const [customTemplateDirty, setCustomTemplateDirty] = useState(false);

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data?.ticket_design_settings) {
            const settings = eventSettingsQuery.data.ticket_design_settings;
            form.setValues({
                accent_color: settings.accent_color || '#333333',
                logo_image_id: settings.logo_image_id || undefined,
                footer_text: settings.footer_text || '',
                enabled: settings.enabled !== false,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    useEffect(() => {
        if (existingLogo?.id) {
            form.setFieldValue('logo_image_id', existingLogo.id);
        } else {
            form.setFieldValue('logo_image_id', null);
        }
    }, [existingLogo?.id]);

    useEffect(() => {
        const settings = eventSettingsQuery.data?.ticket_design_settings;
        if (eventSettingsQuery.isFetched && settings) {
            setCustomTemplate({
                use_custom_template: settings.use_custom_template ?? false,
                template_image_id: (settings.template_image_id as number | null) ?? null,
                qr_x: settings.qr_x ?? 0,
                qr_y: settings.qr_y ?? 0,
                qr_size: settings.qr_size ?? 120,
                num_x: settings.num_x ?? null,
                num_y: settings.num_y ?? null,
            });
        }
    }, [eventSettingsQuery.isFetched, eventSettingsQuery.data]);

    const handleSubmit = (values: TicketDesignSettings) => {
        updateMutation.mutate(
            {
                eventSettings: {
                    ticket_design_settings: {
                        accent_color: values.accent_color,
                        logo_image_id: values.logo_image_id,
                        footer_text: values.footer_text || undefined,
                        enabled: values.enabled
                    }
                },
                eventId: eventId
            },
            {
                onSuccess: () => {
                    showSuccess(t`Ticket design saved successfully`);
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
    };

    const handleCustomTemplateSubmit = () => {
        updateMutation.mutate(
            {
                eventSettings: {
                    ticket_design_settings: {
                        use_custom_template: customTemplate.use_custom_template,
                        template_image_id: customTemplate.template_image_id,
                        qr_x: customTemplate.qr_x,
                        qr_y: customTemplate.qr_y,
                        qr_size: customTemplate.qr_size,
                        num_x: customTemplate.num_x,
                        num_y: customTemplate.num_y,
                    }
                },
                eventId: eventId
            },
            {
                onSuccess: () => {
                    showSuccess(t`Custom template saved`);
                    setCustomTemplateDirty(false);
                },
                onError: (error) => {
                    formErrorHandle(form, error);
                },
            }
        );
    };

    const handleTemplateUploadSuccess = () => {
        handleImageChange();
    };

    useEffect(() => {
        if (!existingTemplateImage?.url) return;
        if (customTemplate.qr_size > 0 && customTemplate.qr_x !== 0) return;

        const img = new window.Image();
        img.onload = () => {
            const qrSize = Math.round(img.naturalWidth * 0.15);
            const qrX = Math.round((img.naturalWidth - qrSize) / 2);
            const qrY = Math.round((img.naturalHeight - qrSize) / 2);
            setCustomTemplate(prev => ({
                ...prev,
                qr_x: qrX,
                qr_y: qrY,
                qr_size: qrSize,
                num_x: qrX,
                num_y: qrY + qrSize + 8,
            }));
            setCustomTemplateDirty(true);
        };
        img.src = existingTemplateImage.url;
    }, [existingTemplateImage?.url]);

    if (eventSettingsQuery.isLoading || eventImagesQuery.isLoading) {
        return <LoadingMask/>;
    }

    return (
        <div className={classes.container}>
            <div className={classes.sidebar}>
                <div className={classes.sticky}>
                    <div className={classes.header}>
                        <h2>{t`Ticket Design`}</h2>
                        <Text c="dimmed" size="sm">{t`Brand your tickets with a custom logo, colors, and footer message.`}</Text>
                    </div>

                    <Tabs defaultValue="standard">
                        <Tabs.List mb="md">
                            <Tabs.Tab value="standard">{t`Standard Design`}</Tabs.Tab>
                            <Tabs.Tab value="custom">{t`Custom Template`}</Tabs.Tab>
                        </Tabs.List>

                        <Tabs.Panel value="standard">
                            <form onSubmit={form.onSubmit(handleSubmit)}>
                                <fieldset disabled={updateMutation.isPending} className={classes.fieldset}>
                                    <Accordion
                                        multiple
                                        value={accordionValue}
                                        onChange={setAccordionValue}
                                        variant="contained"
                                        className={classes.accordion}
                                    >
                                        <Accordion.Item value="design" className={classes.accordionItem}>
                                            <Accordion.Control icon={<IconColorSwatch size={20} />}>
                                                <Text fw={500}>{t`Design Elements`}</Text>
                                            </Accordion.Control>
                                            <Accordion.Panel>
                                                <Stack gap="lg">
                                                    <div>
                                                        <ColorInput
                                                            format="hexa"
                                                            label={t`Accent Color`}
                                                            description={t`Used for borders, highlights, and QR code styling`}
                                                            size="sm"
                                                            {...form.getInputProps('accent_color')}
                                                        />
                                                    </div>

                                                    <div>
                                                        <Group justify={'space-between'} mb="xs">
                                                            <Text fw={500} size="sm">{t`Logo`}</Text>
                                                            <Tooltip
                                                                label={t`We recommend a square logo with minimum dimensions of 200x200px`}>
                                                                <IconHelp size={16} style={{ color: 'var(--mantine-color-gray-6)' }}/>
                                                            </Tooltip>
                                                        </Group>
                                                        <ImageUploadDropzone
                                                            imageType="TICKET_LOGO"
                                                            entityId={eventId}
                                                            onUploadSuccess={handleImageChange}
                                                            onDeleteSuccess={handleImageChange}
                                                            existingImageData={{
                                                                url: existingLogo?.url,
                                                                id: existingLogo?.id,
                                                            }}
                                                            helpText={t`Logo will be displayed on the ticket`}
                                                            displayMode="compact"
                                                        />
                                                    </div>

                                                    <div>
                                                        <Textarea
                                                            label={t`Footer Text`}
                                                            description={t`Optional text for disclaimers, contact info, or thank you notes (single line only)`}
                                                            placeholder={t`Thank you for attending!`}
                                                            rows={2}
                                                            maxLength={500}
                                                            {...form.getInputProps('footer_text')}
                                                            onKeyDown={(e) => {
                                                                if (e.key === 'Enter') {
                                                                    e.preventDefault();
                                                                }
                                                            }}
                                                            onChange={(e) => {
                                                                const value = e.currentTarget.value.replace(/\n/g, ' ');
                                                                form.setFieldValue('footer_text', value);
                                                            }}
                                                        />
                                                        <Text size="xs" c="dimmed" ta="right" mt={4}>
                                                            {form.values.footer_text?.length || 0} / 500
                                                        </Text>
                                                    </div>
                                                </Stack>
                                            </Accordion.Panel>
                                        </Accordion.Item>
                                    </Accordion>

                                    <Stack gap="sm" mt="xl">
                                        <Button type="submit" fullWidth disabled={!form.isDirty()}>
                                            {t`Save Ticket Design`}
                                        </Button>
                                    </Stack>
                                </fieldset>
                            </form>
                        </Tabs.Panel>

                        <Tabs.Panel value="custom">
                            <Stack gap="lg">
                                <Switch
                                    label={t`Enable Custom Template`}
                                    description={t`When enabled, all tickets for this event use your custom layout`}
                                    checked={customTemplate.use_custom_template}
                                    onChange={(e) => {
                                        setCustomTemplate(prev => ({...prev, use_custom_template: e.currentTarget.checked}));
                                        setCustomTemplateDirty(true);
                                    }}
                                />

                                <div>
                                    <Text fw={500} size="sm" mb="xs">{t`Template Image`}</Text>
                                    <ImageUploadDropzone
                                        imageType="TICKET_TEMPLATE"
                                        entityId={eventId}
                                        onUploadSuccess={handleTemplateUploadSuccess}
                                        onDeleteSuccess={handleImageChange}
                                        existingImageData={{
                                            url: existingTemplateImage?.url,
                                            id: existingTemplateImage?.id,
                                        }}
                                        helpText={t`Upload your ticket background (PNG/JPG, min 400×200px)`}
                                        displayMode="compact"
                                    />
                                </div>

                                {existingTemplateImage?.url && (
                                    <div>
                                        <Text fw={500} size="sm" mb="xs">{t`Position QR Code and Counter`}</Text>
                                        <Text size="xs" c="dimmed" mb="sm">
                                            {t`Drag the yellow box to position the QR code. Drag the green box for the ticket counter.`}
                                        </Text>
                                        <QRPlacementCanvas
                                            templateImageUrl={existingTemplateImage.url}
                                            value={{
                                                qr_x: customTemplate.qr_x,
                                                qr_y: customTemplate.qr_y,
                                                qr_size: customTemplate.qr_size,
                                                num_x: customTemplate.num_x,
                                                num_y: customTemplate.num_y,
                                            }}
                                            onChange={(v: QRPlacementValue) => {
                                                setCustomTemplate(prev => ({...prev, ...v}));
                                                setCustomTemplateDirty(true);
                                            }}
                                        />
                                    </div>
                                )}

                                <Button
                                    fullWidth
                                    onClick={handleCustomTemplateSubmit}
                                    loading={updateMutation.isPending}
                                    disabled={!customTemplateDirty}
                                >
                                    {t`Save Custom Template`}
                                </Button>
                            </Stack>
                        </Tabs.Panel>
                    </Tabs>
                </div>
            </div>

            <div className={classes.preview}>
                <div className={classes.previewHeader}>
                    <h3>{t`Preview`}</h3>
                    <Button 
                        size="xs" 
                        variant="light"
                        leftSection={<IconPrinter size={14} />}
                        onClick={() => window?.open(`/manage/event/${eventId}/ticket-designer/print`, '_blank')}
                    >
                        {t`Print Preview`}
                    </Button>
                </div>
                
                <div className={classes.previewContent}>
                    <TicketPreview
                        settings={form.values}
                        eventId={eventId}
                        logoUrl={existingLogo?.url}
                    />
                </div>
            </div>
        </div>
    );
};

export default TicketDesigner;
