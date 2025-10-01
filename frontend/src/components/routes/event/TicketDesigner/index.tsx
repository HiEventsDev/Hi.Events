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
import {Button, ColorInput, Textarea, Accordion, Stack, Text, Group} from "@mantine/core";
import {IconColorSwatch, IconHelp, IconPrinter} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {LoadingMask} from "../../../common/LoadingMask";
import {TicketPreview} from "./TicketPreview";

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

    const form = useForm<TicketDesignSettings>({
        initialValues: {
            accent_color: '#333333',
            logo_image_id: undefined,
            footer_text: '',
            enabled: true,
        }
    });

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

    if (eventSettingsQuery.isLoading || eventImagesQuery.isLoading) {
        return <LoadingMask/>;
    }

    return (
        <div className={classes.container}>
            <div className={classes.sidebar}>
                <div className={classes.sticky}>
                    <div className={classes.header}>
                        <h2>{t`Ticket Design`}</h2>
                        <Text c="dimmed" size="sm">{t`Customize your ticket appearance`}</Text>
                    </div>

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
