import {useEffect} from "react";
import {useParams} from "react-router";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {EventSettings} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, ColorInput, Select, Text, Textarea, Tooltip} from "@mantine/core";
import {IconCalendar, IconPalette, IconPhoto, IconPrinter, IconTextCaption} from "@tabler/icons-react";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {LoadingMask} from "../../../common/LoadingMask";
import {DesignerSection, DesignerShell} from "../../../common/DesignerShell";
import {TicketDesignSettings, TicketPreview} from "./TicketPreview";

const FOOTER_MAX_LENGTH = 500;

const ACCENT_SWATCHES = [
    '#333333',
    '#8b5cf6',
    '#6366f1',
    '#2563eb',
    '#0891b2',
    '#059669',
    '#ca8a04',
    '#ea580c',
    '#dc2626',
    '#db2777',
];

const formValuesFromSettings = (settings: EventSettings['ticket_design_settings']): TicketDesignSettings => ({
    accent_color: settings?.accent_color || '#333333',
    footer_text: settings?.footer_text || '',
    date_display_mode: settings?.date_display_mode || 'START_DATE_TIME',
    enabled: settings?.enabled !== false,
});

const TicketDesigner = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const eventImagesQuery = useGetEventImages(eventId);
    const updateMutation = useUpdateEventSettings();
    const formErrorHandle = useFormErrorResponseHandler();

    const existingLogo = eventImagesQuery.data?.find((image) => image.type === 'TICKET_LOGO');

    const form = useForm<TicketDesignSettings>({
        initialValues: formValuesFromSettings(undefined),
    });

    useEffect(() => {
        if (eventSettingsQuery.data && !form.isDirty()) {
            const values = formValuesFromSettings(eventSettingsQuery.data.ticket_design_settings);
            form.setValues(values);
            form.resetDirty(values);
        }
    }, [eventSettingsQuery.data]);

    const handleSubmit = (values: TicketDesignSettings) => {
        updateMutation.mutate(
            {
                eventSettings: {
                    ticket_design_settings: {
                        accent_color: values.accent_color,
                        logo_image_id: existingLogo?.id,
                        footer_text: values.footer_text || undefined,
                        date_display_mode: values.date_display_mode,
                        enabled: values.enabled,
                    }
                },
                eventId: eventId
            },
            {
                onSuccess: () => {
                    form.resetDirty(values);
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

    const isDisabled = updateMutation.isPending;
    const hasUnsavedChanges = form.isDirty();

    return (
        <DesignerShell
            title={t`Ticket Design`}
            hasUnsavedChanges={hasUnsavedChanges}
            isSaving={updateMutation.isPending}
            onSave={() => form.onSubmit(handleSubmit)()}
            onDiscard={() => form.reset()}
            previewActions={(
                <Tooltip
                    label={t`Print preview uses your last saved design`}
                    disabled={!hasUnsavedChanges}
                    position="bottom-end"
                    withArrow
                >
                    <Button
                        size="compact-sm"
                        variant="subtle"
                        leftSection={<IconPrinter size={14}/>}
                        onClick={() => window?.open(`/manage/event/${eventId}/ticket-designer/print`, '_blank')}
                    >
                        {t`Print Preview`}
                    </Button>
                </Tooltip>
            )}
            preview={(
                <TicketPreview
                    settings={form.values}
                    eventId={eventId}
                    logo={existingLogo?.url ? {id: existingLogo.id, url: existingLogo.url} : undefined}
                />
            )}
        >
            <DesignerSection icon={<IconPhoto/>} title={t`Logo`}>
                <Text size="xs" c="dimmed" mb="xs">
                    {t`Square, at least 200 × 200px`}
                </Text>
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
            </DesignerSection>

            <DesignerSection icon={<IconPalette/>} title={t`Color`}>
                <ColorInput
                    format="hexa"
                    label={t`Accent Color`}
                    description={t`Used for borders, highlights, and QR code styling`}
                    size="sm"
                    swatches={ACCENT_SWATCHES}
                    swatchesPerRow={10}
                    disabled={isDisabled}
                    {...form.getInputProps('accent_color')}
                />
            </DesignerSection>

            <DesignerSection icon={<IconCalendar/>} title={t`Event Date`}>
                <Select
                    label={t`Event date display`}
                    size="sm"
                    allowDeselect={false}
                    disabled={isDisabled}
                    data={[
                        {value: 'START_DATE_TIME', label: t`Only show start date and time`},
                        {value: 'DATE_RANGE', label: t`Show entire date range`},
                        {value: 'HIDDEN', label: t`Hide the date`},
                    ]}
                    {...form.getInputProps('date_display_mode')}
                />
            </DesignerSection>

            <DesignerSection icon={<IconTextCaption/>} title={t`Footer`}>
                <Textarea
                    label={t`Footer Text`}
                    description={t`Disclaimers, contact info or a thank-you note, on a single line`}
                    placeholder={t`Thank you for attending!`}
                    autosize
                    minRows={2}
                    maxLength={FOOTER_MAX_LENGTH}
                    disabled={isDisabled}
                    {...form.getInputProps('footer_text')}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                        }
                    }}
                    onChange={(e) => {
                        form.setFieldValue('footer_text', e.currentTarget.value.replace(/\n/g, ' '));
                    }}
                />
                <Text size="xs" c="dimmed" ta="right" mt={4}>
                    {form.values.footer_text.length} / {FOOTER_MAX_LENGTH}
                </Text>
            </DesignerSection>
        </DesignerShell>
    );
};

export default TicketDesigner;
