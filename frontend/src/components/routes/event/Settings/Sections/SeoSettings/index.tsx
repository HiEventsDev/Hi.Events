import {t} from "@lingui/macro";
import {Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";

export const SeoSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
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
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            const values = {
                allow_search_engine_indexing: eventSettingsQuery.data.allow_search_engine_indexing,
                seo_title: eventSettingsQuery.data.seo_title,
                seo_description: eventSettingsQuery.data.seo_description,
                seo_keywords: eventSettingsQuery.data.seo_keywords,
            };
            form.setInitialValues(values as typeof form.values);
            form.setValues(values);
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Seo Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <SettingsPage
            title={t`SEO`}
            description={t`Customize the SEO settings for this event`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                        <SettingsRow
                            label={t`SEO Title`}
                            description={t`The title of the event that will be displayed in search engine results and when sharing on social media. By default, the event title will be used`}
                            htmlFor="event-seo-title"
                        >
                            <TextInput
                                id="event-seo-title"
                                {...form.getInputProps('seo_title')}
                                placeholder={t`My amazing event title...`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`SEO Description`}
                            description={t`A short description of the event that will be displayed in search engine results and when sharing on social media. By default, the event description will be used`}
                            htmlFor="event-seo-description"
                        >
                            <TextInput
                                id="event-seo-description"
                                {...form.getInputProps('seo_description')}
                                placeholder={t`My amazing event description...`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`SEO Keywords`}
                            description={t`Comma seperated keywords that describe the event. These will be used by search engines to help categorize and index the event`}
                            htmlFor="event-seo-keywords"
                        >
                            <TextInput
                                id="event-seo-keywords"
                                {...form.getInputProps('seo_keywords')}
                                placeholder={t`Amazing, Event, Keywords...`}
                            />
                        </SettingsRow>
                        <SettingsRow
                            label={t`Allow search engine indexing`}
                            description={t`Allow search engines to index this event`}
                            htmlFor="event-seo-indexing"
                            inlineControl
                        >
                            <Switch
                                id="event-seo-indexing"
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
