import {useEffect} from "react";
import {useParams} from "react-router";
import {useGetOrganizerSettings} from "../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../mutations/useUpdateOrganizerSettings.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {HomepageThemeSettings, OrganizerSettings} from "../../../../types.ts";
import {showSuccess} from "../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Group, Stack, Text} from "@mantine/core";
import {IconHelp, IconPalette, IconPhoto, IconTypography} from "@tabler/icons-react";
import {Tooltip} from "../../../common/Tooltip";
import {GET_ORGANIZER_QUERY_KEY, useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {ImageUploadDropzone} from "../../../common/ImageUploadDropzone";
import {organizerPreviewPath} from "../../../../utilites/urlHelper.ts";
import {queryClient} from "../../../../utilites/queryClient.ts";
import {GET_ORGANIZER_PUBLIC_QUERY_KEY} from "../../../../queries/useGetOrganizerPublic.ts";
import {ThemeColorControls} from "../../../common/ThemeColorControls";
import {ThemeFontControl} from "../../../common/ThemeFontControl";
import {getDefaultThemeSettings, validateThemeSettings} from "../../../../utilites/themeUtils.ts";
import {HomepageDesigner, useHomepagePreview} from "../../../common/HomepageDesigner";
import {DesignerSection} from "../../../common/DesignerShell";

interface FormValues {
    homepage_theme_settings: Partial<HomepageThemeSettings>;
}

const OrganizerHomepageDesigner = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const organizerQuery = useGetOrganizer(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const formErrorHandle = useFormErrorResponseHandler();

    const existingLogo = organizerQuery.data?.images?.find((image) => image.type === 'ORGANIZER_LOGO');
    const existingCover = organizerQuery.data?.images?.find((image) => image.type === 'ORGANIZER_COVER');

    const form = useForm<FormValues>({
        initialValues: {
            homepage_theme_settings: getDefaultThemeSettings(),
        }
    });

    useEffect(() => {
        if (organizerSettingsQuery.data && !form.isDirty()) {
            const values = {
                homepage_theme_settings: validateThemeSettings(organizerSettingsQuery.data.homepage_theme_settings),
            };
            form.setValues(values);
            form.resetDirty(values);
        }
    }, [organizerSettingsQuery.data]);

    const isDisabled = organizerSettingsQuery.isLoading || updateMutation.isPending;

    const preview = useHomepagePreview({
        path: organizerPreviewPath(organizerId),
        ready: organizerSettingsQuery.isFetched && organizerQuery.isFetched,
        imageVersion: `cover_image_id=${existingCover?.id}&logo_image_id=${existingLogo?.id}`,
        messageType: 'UPDATE_ORGANIZER_SETTINGS',
        settings: {
            homepage_theme_settings: validateThemeSettings(form.values.homepage_theme_settings),
            logoUrl: existingLogo?.url,
            coverUrl: existingCover?.url,
        },
    });

    const handleSubmit = (values: FormValues) => {
        const organizerSettings: Partial<OrganizerSettings> = {
            homepage_theme_settings: validateThemeSettings(values.homepage_theme_settings),
        };

        updateMutation.mutate(
            {
                organizerSettings,
                organizerId: organizerId
            },
            {
                onSuccess: () => {
                    form.resetDirty(values);
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
            queryKey: [GET_ORGANIZER_PUBLIC_QUERY_KEY, organizerId],
        });
        queryClient.invalidateQueries({
            queryKey: [GET_ORGANIZER_QUERY_KEY, organizerId],
        });
    };

    return (
        <HomepageDesigner
            title={t`Homepage Design`}
            preview={preview}
            previewTitle={t`Organizer Homepage Preview`}
            hasUnsavedChanges={form.isDirty()}
            isSaving={updateMutation.isPending}
            onSave={() => form.onSubmit(handleSubmit)()}
            onDiscard={() => form.reset()}
        >
            <DesignerSection icon={<IconPhoto/>} title={t`Images`}>
                <Stack gap="lg">
                    <div>
                        <Group justify="space-between" mb="xs">
                            <Text fw={500} size="sm">{t`Cover Image`}</Text>
                            <Tooltip label={t`We recommend dimensions of 1950px by 650px, a ratio of 3:1, and a maximum file size of 5MB`}>
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
                        <Group justify="space-between" mb="xs">
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
        </HomepageDesigner>
    );
};

export default OrganizerHomepageDesigner;
