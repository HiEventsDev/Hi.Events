import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Button, TextInput} from "@mantine/core";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";

export const LegalSettings = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const form = useForm({
        initialValues: {
            terms_of_service_url: '',
            privacy_policy_url: '',
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            form.setValues({
                terms_of_service_url: organizerSettingsQuery.data.terms_of_service_url || '',
                privacy_policy_url: organizerSettingsQuery.data.privacy_policy_url || '',
            });
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: { terms_of_service_url: string; privacy_policy_url: string }) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Legal Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Legal`}
                description={t`Set custom Terms of Service and Privacy Policy URLs for your events`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                    <TextInput
                        {...form.getInputProps('terms_of_service_url')}
                        description={t`A link to your Terms of Service. This will be shown in event and checkout footers`}
                        label={t`Terms of Service URL`}
                        placeholder="https://example.com/terms"
                    />
                    <TextInput
                        {...form.getInputProps('privacy_policy_url')}
                        description={t`A link to your Privacy Policy. This will be shown in event and checkout footers`}
                        label={t`Privacy Policy URL`}
                        placeholder="https://example.com/privacy"
                    />
                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
