import {useEffect, useMemo} from "react";
import {useParams} from "react-router";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {useUpdateEventSettings} from "../../../../mutations/useUpdateEventSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {EventSettings, HomepageSection, HomepageThemeSettings} from "../../../../types.ts";
import {cleanHomepageSections, resolveHomepageSections} from "../../../../utilites/homepageSections.ts";
import {SectionsEditor} from "./SectionsEditor";
import {SectionEmptyState, sectionEmptyState} from "./SectionsEditor/sectionEmptyState.ts";
import {useGetEventLineupPublic} from "../../../../queries/useGetEventLineupPublic.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Group, Stack, Text, TextInput} from "@mantine/core";
import {IconClick, IconHelp, IconLayout, IconLayoutList, IconPalette, IconPhoto, IconTypography} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {GET_EVENT_IMAGES_QUERY_KEY, useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {eventPreviewPath} from "../../../../utilites/urlHelper.ts";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_EVENT_PUBLIC_QUERY_KEY, useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import {ThemeColorControls} from "../../../common/ThemeColorControls";
import {ThemeFontControl} from "../../../common/ThemeFontControl";
import {getDefaultThemeSettings, validateThemeSettings} from "../../../../utilites/themeUtils.ts";
import {firstHomepageSectionsError, HomepageDesigner as HomepageDesignerShell, useHomepagePreview} from "../../../common/HomepageDesigner";
import {DesignerSection} from "../../../common/DesignerShell";
import {resolveEventLayout} from "../../../../constants/eventLayouts.ts";
import {LayoutPicker} from "./LayoutPicker";

interface FormValues {
    homepage_theme_settings: Partial<HomepageThemeSettings>;
    homepage_sections: HomepageSection[];
    continue_button_text: string;
    get_tickets_button_text: string;
}

const formValuesFromSettings = (settings: EventSettings): FormValues => ({
    homepage_theme_settings: validateThemeSettings(settings.homepage_theme_settings),
    homepage_sections: resolveHomepageSections(settings.homepage_sections),
    continue_button_text: settings.continue_button_text || '',
    get_tickets_button_text: settings.get_tickets_button_text || '',
});

const HomepageDesigner = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const eventImagesQuery = useGetEventImages(eventId);
    const updateMutation = useUpdateEventSettings();
    const publicEventQuery = useGetEventPublic(eventId);
    const lineupQuery = useGetEventLineupPublic(eventId);
    const formErrorHandle = useFormErrorResponseHandler();

    const existingCover = eventImagesQuery.data?.find((image) => image.type === 'EVENT_COVER');
    const sectionImageIds = (eventImagesQuery.data ?? [])
        .filter(image => image.type === 'EVENT_GALLERY' || image.type === 'EVENT_SPONSOR_LOGO')
        .map(image => image.id)
        .join('.');

    const form = useForm<FormValues>({
        initialValues: {
            homepage_theme_settings: getDefaultThemeSettings(),
            homepage_sections: resolveHomepageSections(),
            continue_button_text: '',
            get_tickets_button_text: '',
        }
    });

    useEffect(() => {
        if (eventSettingsQuery.data && !form.isDirty()) {
            const values = formValuesFromSettings(eventSettingsQuery.data);
            form.setValues(values);
            form.resetDirty(values);
        }
    }, [eventSettingsQuery.data]);

    const isDisabled = eventSettingsQuery.isLoading || updateMutation.isPending;

    const emptyStates = useMemo(() => {
        const imageIds = new Set((eventImagesQuery.data ?? []).map(image => Number(image.id)));
        const states = new Map<string, SectionEmptyState>();
        form.values.homepage_sections.forEach(section => {
            const state = sectionEmptyState(section, publicEventQuery.data, lineupQuery.data, imageIds);
            if (state) {
                states.set(section.id, state);
            }
        });
        return states;
    }, [form.values.homepage_sections, publicEventQuery.data, lineupQuery.data, eventImagesQuery.data]);

    const preview = useHomepagePreview({
        path: eventPreviewPath(eventId),
        ready: eventSettingsQuery.isFetched && eventImagesQuery.isFetched,
        imageVersion: `cover_image_id=${existingCover?.id}&section_images=${sectionImageIds}`,
        messageType: 'UPDATE_SETTINGS',
        settings: {
            homepage_theme_settings: validateThemeSettings(form.values.homepage_theme_settings),
            homepage_sections: cleanHomepageSections(form.values.homepage_sections),
            continue_button_text: form.values.continue_button_text,
            get_tickets_button_text: form.values.get_tickets_button_text,
        },
    });

    const handleSubmit = (values: FormValues) => {
        const validatedTheme = validateThemeSettings(values.homepage_theme_settings);

        const eventSettings: Partial<EventSettings> = {
            homepage_theme_settings: validatedTheme,
            homepage_sections: cleanHomepageSections(values.homepage_sections),
            continue_button_text: values.continue_button_text,
            get_tickets_button_text: values.get_tickets_button_text,
            homepage_primary_color: validatedTheme.accent,
            homepage_body_background_color: validatedTheme.background,
            homepage_background_type: validatedTheme.background_type,
        };

        updateMutation.mutate(
            {eventSettings, eventId: eventId},
            {
                onSuccess: () => {
                    form.resetDirty(values);
                    showSuccess(t`Successfully Updated Homepage Design`);
                },
                onError: (error) => {
                    formErrorHandle(form, error, firstHomepageSectionsError(error));
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

    return (
        <HomepageDesignerShell
            title={t`Homepage Design`}
            preview={preview}
            previewTitle={t`Event Preview`}
            hasUnsavedChanges={form.isDirty()}
            isSaving={updateMutation.isPending}
            onSave={() => form.onSubmit(handleSubmit)()}
            onDiscard={() => form.reset()}
        >
            <DesignerSection icon={<IconLayout/>} title={t`Layout`}>
                <LayoutPicker
                    theme={validateThemeSettings(form.values.homepage_theme_settings)}
                    coverIsLandscape={!!existingCover?.width && !!existingCover?.height && existingCover.width > existingCover.height * 1.25}
                    disabled={isDisabled}
                    onChange={layout => form.setFieldValue('homepage_theme_settings', {
                        ...form.values.homepage_theme_settings,
                        layout,
                    })}
                />
            </DesignerSection>

            <DesignerSection icon={<IconLayoutList/>} title={t`Sections`}>
                <SectionsEditor
                    variant="event"
                    ticketsBesideContent={resolveEventLayout(form.values.homepage_theme_settings.layout).ticketRail}
                    entityId={eventId!}
                    sections={form.values.homepage_sections}
                    images={eventImagesQuery.data ?? []}
                    emptyStates={emptyStates}
                    disabled={isDisabled}
                    onChange={update => form.setFieldValue('homepage_sections', update)}
                    onImagesChanged={handleImageChange}
                />
            </DesignerSection>

            <DesignerSection icon={<IconPhoto/>} title={t`Images`}>
                <Group justify="space-between" mb="xs">
                    <Text fw={500} size="sm">{t`Cover Image`}</Text>
                    <Tooltip label={t`We recommend dimensions of 1950px by 650px, a ratio of 3:1, and a maximum file size of 5MB`}>
                        <IconHelp size={16} style={{color: 'var(--mantine-color-gray-6)'}}/>
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
            </DesignerSection>

            <DesignerSection icon={<IconPalette/>} title={t`Theme & Colors`}>
                <ThemeColorControls
                    values={form.values.homepage_theme_settings}
                    onChange={(themeSettings) => form.setFieldValue('homepage_theme_settings', themeSettings)}
                    hasCoverImage={!!existingCover}
                    disabled={isDisabled}
                />
            </DesignerSection>

            <DesignerSection icon={<IconTypography/>} title={t`Typography`}>
                <ThemeFontControl
                    value={form.values.homepage_theme_settings.font_family}
                    onChange={(fontFamily) => form.setFieldValue('homepage_theme_settings', {
                        ...form.values.homepage_theme_settings,
                        font_family: fontFamily,
                    })}
                    disabled={isDisabled}
                />
            </DesignerSection>

            <DesignerSection icon={<IconClick/>} title={t`Button Text`}>
                <Stack gap="md">
                    <TextInput
                        label={t`Continue Button`}
                        description={t`Shown below the ticket list`}
                        placeholder={t`Continue`}
                        size="sm"
                        disabled={isDisabled}
                        {...form.getInputProps('continue_button_text')}
                    />
                    <TextInput
                        label={t`Floating Button`}
                        description={t`Shown on scroll and jumps to the tickets section`}
                        placeholder={t`Get Tickets`}
                        size="sm"
                        disabled={isDisabled}
                        {...form.getInputProps('get_tickets_button_text')}
                    />
                </Stack>
            </DesignerSection>
        </HomepageDesignerShell>
    );
};

export default HomepageDesigner;
