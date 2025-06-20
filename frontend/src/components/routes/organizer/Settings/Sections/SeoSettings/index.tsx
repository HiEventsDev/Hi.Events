import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {EventSettings} from "../../../../../../types.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Button, Switch, TextInput} from "@mantine/core";
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
            form.setValues({
                allow_search_engine_indexing: organizerSettingsQuery.data.allow_search_engine_indexing,
                seo_title: organizerSettingsQuery.data.seo_title,
                seo_description: organizerSettingsQuery.data.seo_description,
                seo_keywords: organizerSettingsQuery.data.seo_keywords,
            });
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: Partial<EventSettings>) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated SEO Settings`);
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
                <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
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
                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>

                </fieldset>
            </form>
        </Card>
    );
}
