import {t} from "@lingui/macro";
import {Button, Switch, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

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
            form.setValues({
                allow_search_engine_indexing: eventSettingsQuery.data.allow_search_engine_indexing,
                seo_title: eventSettingsQuery.data.seo_title,
                seo_description: eventSettingsQuery.data.seo_description,
                seo_keywords: eventSettingsQuery.data.seo_keywords,
            });
        }
    }, [eventSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            eventSettings: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Seo Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`SEO Settings`}
                description={t`Customize the SEO settings for this event`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isLoading}>
                    <TextInput
                        {...form.getInputProps('seo_title')}
                        description={t`The title of the event that will be displayed in search engine results and when sharing on social media. By default, the event title will be used`}
                        label={t`SEO Title`}
                        placeholder={t`My amazing event title...`}
                    />
                    <TextInput
                        {...form.getInputProps('seo_description')}
                        description={t`A short description of the event that will be displayed in search engine results and when sharing on social media. By default, the event description will be used`}
                        label={t`SEO Description`}
                        placeholder={t`My amazing event description...`}
                    />
                    <TextInput
                        {...form.getInputProps('seo_keywords')}
                        description={t`Comma seperated keywords that describe the event. These will be used by search engines to help categorize and index the event`}
                        label={t`SEO Keywords`}
                        placeholder={t`Amazing, Event, Keywords...`}
                    />
                    <Switch
                        {...form.getInputProps('allow_search_engine_indexing', {type: 'checkbox'})}
                        description={t`Allow search engines to index this event`}
                        label={t`Allow search engine indexing`}
                    />
                    <Button loading={updateMutation.isLoading} type={'submit'}>
                        {t`Save`}
                    </Button>

                </fieldset>
            </form>
        </Card>
    );
}