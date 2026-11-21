import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";
import {Switch, TextInput} from "@mantine/core";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";

export const SeoSettings = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const form = useForm({
        initialValues: {
            allow_search_engine_indexing: true,
            seo_title: '',
            seo_description: '',
            seo_keywords: '',
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            const values = {
                allow_search_engine_indexing: organizerSettingsQuery.data.allow_search_engine_indexing ?? true,
                seo_title: organizerSettingsQuery.data.seo_title ?? '',
                seo_description: organizerSettingsQuery.data.seo_description ?? '',
                seo_keywords: organizerSettingsQuery.data.seo_keywords ?? '',
            };
            form.setInitialValues(values);
            form.setValues(values);
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated SEO Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <SettingsPage
            title={t`SEO`}
            description={t`Customize the SEO settings for your organizer page`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                        <SettingsRow
                            label={t`SEO Title`}
                            description={t`Shown in search engine results and when your organizer page is shared on social media`}
                            htmlFor="organizer-seo-title"
                        >
                            <TextInput
                                id="organizer-seo-title"
                                {...form.getInputProps('seo_title')}
                                placeholder={t`My organizer title...`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`SEO Description`}
                            description={t`A short description shown in search engine results and when your organizer page is shared on social media`}
                            htmlFor="organizer-seo-description"
                        >
                            <TextInput
                                id="organizer-seo-description"
                                {...form.getInputProps('seo_description')}
                                placeholder={t`A short description of my organizer...`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`SEO Keywords`}
                            description={t`Comma separated keywords that help search engines categorize your organizer page`}
                            htmlFor="organizer-seo-keywords"
                        >
                            <TextInput
                                id="organizer-seo-keywords"
                                {...form.getInputProps('seo_keywords')}
                                placeholder={t`Amazing, Event, Keywords...`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Allow search engine indexing`}
                            description={t`Allow search engines to index your organizer page`}
                            htmlFor="organizer-seo-indexing"
                            inlineControl
                        >
                            <Switch
                                id="organizer-seo-indexing"
                                {...form.getInputProps('allow_search_engine_indexing', {type: 'checkbox'})}
                            />
                        </SettingsRow>
                    </fieldset>
                    <SettingsSaveFooter
                        isDirty={form.isDirty()}
                        isSaving={updateMutation.isPending}
                        onDiscard={form.reset}
                    />
                </form>
            </SettingsSection>
        </SettingsPage>
    );
}
