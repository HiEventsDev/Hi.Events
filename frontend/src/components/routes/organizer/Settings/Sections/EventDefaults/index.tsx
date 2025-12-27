import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {Button, Switch} from "@mantine/core";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {CustomSelect, ItemProps} from "../../../../../common/CustomSelect";
import {IconUser, IconUsers} from "@tabler/icons-react";
import {SelfServiceSettings} from "../../../../../common/SelfServiceSettings";

export const EventDefaults = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();

    const form = useForm({
        initialValues: {
            default_attendee_details_collection_method: 'PER_TICKET' as 'PER_TICKET' | 'PER_ORDER',
            default_show_marketing_opt_in: true,
            default_allow_attendee_self_edit: false,
        }
    });

    const attendeeCollectionOptions: ItemProps[] = [
        {
            icon: <IconUsers/>,
            label: t`Per ticket`,
            value: 'PER_TICKET',
            description: t`Collect attendee details for each ticket purchased.`,
        },
        {
            icon: <IconUser/>,
            label: t`Per order`,
            value: 'PER_ORDER',
            description: t`Use order details for all attendees. Attendee names and emails will match the buyer's information.`,
        },
    ];

    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (organizerSettingsQuery?.isFetched && organizerSettingsQuery?.data) {
            form.setValues({
                default_attendee_details_collection_method: organizerSettingsQuery.data.default_attendee_details_collection_method || 'PER_TICKET',
                default_show_marketing_opt_in: organizerSettingsQuery.data.default_show_marketing_opt_in ?? true,
                default_allow_attendee_self_edit: organizerSettingsQuery.data.default_allow_attendee_self_edit ?? false,
            });
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: { default_attendee_details_collection_method: 'PER_TICKET' | 'PER_ORDER'; default_show_marketing_opt_in: boolean; default_allow_attendee_self_edit: boolean }) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Event Defaults`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Event Defaults`}
                description={t`Set default settings for new events created under this organizer.`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                    <CustomSelect
                        optionList={attendeeCollectionOptions}
                        form={form}
                        name={'default_attendee_details_collection_method'}
                        label={t`Default attendee information collection`}
                        required
                    />

                    <Switch
                        mt="md"
                        label={t`Show marketing opt-in checkbox by default`}
                        description={t`When enabled, new events will display a marketing opt-in checkbox during checkout. This can be overridden per event.`}
                        {...form.getInputProps('default_show_marketing_opt_in', {type: 'checkbox'})}
                    />

                    <SelfServiceSettings
                        value={form.values.default_allow_attendee_self_edit}
                        onChange={(value) => form.setFieldValue('default_allow_attendee_self_edit', value)}
                        isDefault={true}
                    />

                    <Button
                        loading={updateMutation.isPending}
                        type={'submit'}
                    >
                        {t`Save`}
                    </Button>
                </fieldset>
            </form>
        </Card>
    );
}
