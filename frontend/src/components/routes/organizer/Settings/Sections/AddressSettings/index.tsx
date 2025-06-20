import {t} from "@lingui/macro";
import {Button, Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {Card} from "../../../../../common/Card";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import countries from "../../../../../../../data/countries.json";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

export const AddressSettings = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const form = useForm({
        initialValues: {
            location_details: {
                venue_name: '',
                address_line_1: '',
                address_line_2: '',
                city: '',
                state_or_region: '',
                zip_or_postal_code: '',
                country: '',
            },
        },
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery.data) {
            form.setValues({
                location_details: {
                    venue_name: organizerSettingsQuery.data.location_details?.venue_name || '',
                    address_line_1: organizerSettingsQuery.data.location_details?.address_line_1 || '',
                    address_line_2: organizerSettingsQuery.data.location_details?.address_line_2 || '',
                    city: organizerSettingsQuery.data.location_details?.city || '',
                    state_or_region: organizerSettingsQuery.data.location_details?.state_or_region || '',
                    zip_or_postal_code: organizerSettingsQuery.data.location_details?.zip_or_postal_code || '',
                    country: organizerSettingsQuery.data.location_details?.country || '',
                },
            });
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: any) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Address`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Address`}
                description={t`Your organizer address`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                    <TextInput
                        {...form.getInputProps('location_details.venue_name')}
                        label={t`Office or venue name`}
                        placeholder={t`Office or venue name`}
                    />
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('location_details.address_line_1')}
                            label={t`Address Line 1`}
                            placeholder={t`123 Main Street`}
                        />
                        <TextInput
                            {...form.getInputProps('location_details.address_line_2')}
                            label={t`Address Line 2`}
                            placeholder={t`Suite 100`}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('location_details.city')}
                            label={t`City`}
                            placeholder={t`San Francisco`}
                        />
                        <TextInput
                            {...form.getInputProps('location_details.state_or_region')}
                            label={t`State or Region`}
                            placeholder={t`California`}
                        />
                    </InputGroup>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('location_details.zip_or_postal_code')}
                            label={t`Zip or Postal Code`}
                            placeholder={t`94103`}
                        />
                        <Select searchable
                                data={countries}
                                {...form.getInputProps('location_details.country')}
                                label={t`Country`}
                                placeholder={t`United States`}
                        />
                    </InputGroup>

                    <Button loading={updateMutation.isPending} type={'submit'}>
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
