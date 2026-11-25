import {useParams} from "react-router";
import {useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useEffect} from "react";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {SettingsBlock, SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";
import {Switch} from "@mantine/core";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings.ts";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings.ts";
import {CustomSelect, ItemProps} from "../../../../../common/CustomSelect";
import {IconUser, IconUsers} from "@tabler/icons-react";
import {selfServiceCopy} from "../../../../../common/SelfServiceSettings";
import {PhoneCollection} from "../../../../../../types.ts";
import {getPhoneCollectionOptions} from "../../../../../../utilites/phoneCollection.tsx";
import {useTextMessagesPaused, useTextMessagesVisible} from "../../../../../../ee/crm/hooks/useTextMessagesVisible.ts";
import {useSmsConnectionMissing} from "../../../../../../ee/crm/hooks/useSmsConnectionMissing.ts";
import {Callout} from "../../../../../common/Callout";

export const EventDefaults = () => {
    const {organizerId} = useParams();
    const organizerSettingsQuery = useGetOrganizerSettings(organizerId);
    const updateMutation = useUpdateOrganizerSettings();
    const showTextMessageOptIn = useTextMessagesVisible();
    const isTextMessageOptInPaused = useTextMessagesPaused();
    const isSmsConnectionMissing = useSmsConnectionMissing(organizerId);

    const form = useForm({
        initialValues: {
            default_attendee_details_collection_method: 'PER_ORDER' as 'PER_TICKET' | 'PER_ORDER',
            default_show_marketing_opt_in: true,
            default_allow_attendee_self_edit: false,
            default_phone_collection: 'OFF' as PhoneCollection,
            default_show_sms_opt_in: false,
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
            const values = {
                default_attendee_details_collection_method: organizerSettingsQuery.data.default_attendee_details_collection_method || 'PER_ORDER',
                default_show_marketing_opt_in: organizerSettingsQuery.data.default_show_marketing_opt_in ?? true,
                default_allow_attendee_self_edit: organizerSettingsQuery.data.default_allow_attendee_self_edit ?? false,
                default_phone_collection: organizerSettingsQuery.data.default_phone_collection ?? 'OFF',
                default_show_sms_opt_in: organizerSettingsQuery.data.default_show_sms_opt_in ?? false,
            };
            form.setInitialValues(values);
            form.setValues(values);
        }
    }, [organizerSettingsQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        updateMutation.mutate({
            organizerSettings: values,
            organizerId: organizerId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Event Defaults`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    const selfService = selfServiceCopy(true);
    const textOptInDescription = isTextMessageOptInPaused
        ? t`Text messages aren't available on this account at the moment, so checkout doesn't show this checkbox. Your choice is kept for when they are.`
        : form.values.default_phone_collection === 'OFF'
            ? t`Ask for the buyer's mobile number above to use this.`
            : t`New events that ask for a mobile number will let buyers agree to receive text messages. This can be changed per event.`;

    return (
        <SettingsPage
            title={t`Event Defaults`}
            description={t`Set default settings for new events created under this organizer.`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={organizerSettingsQuery.isLoading || updateMutation.isPending}>
                        <SettingsBlock>
                            <CustomSelect
                                optionList={attendeeCollectionOptions}
                                form={form}
                                name={'default_attendee_details_collection_method'}
                                label={t`Default attendee information collection`}
                                required
                            />
                        </SettingsBlock>
                        <SettingsBlock>
                            <CustomSelect
                                optionList={getPhoneCollectionOptions()}
                                form={form}
                                name={'default_phone_collection'}
                                label={t`Ask for buyer's mobile number by default`}
                                required
                            />
                        </SettingsBlock>
                        <SettingsRow
                            label={t`Show marketing opt-in checkbox by default`}
                            description={t`When enabled, new events will display a marketing opt-in checkbox during checkout. This can be overridden per event.`}
                            htmlFor="organizer-defaults-marketing-opt-in"
                            inlineControl
                        >
                            <Switch
                                id="organizer-defaults-marketing-opt-in"
                                {...form.getInputProps('default_show_marketing_opt_in', {type: 'checkbox'})}
                            />
                        </SettingsRow>
                        {showTextMessageOptIn && (
                            <SettingsRow
                                label={t`Show text message opt-in checkbox by default`}
                                description={textOptInDescription}
                                below={isSmsConnectionMissing && (
                                    <Callout variant="warning">
                                        {t`Connect a Twilio number in Settings › Text Messages, or buyers won't see this option.`}
                                    </Callout>
                                )}
                                htmlFor="organizer-defaults-sms-opt-in"
                                inlineControl
                            >
                                <Switch
                                    id="organizer-defaults-sms-opt-in"
                                    disabled={form.values.default_phone_collection === 'OFF'}
                                    {...form.getInputProps('default_show_sms_opt_in', {type: 'checkbox'})}
                                />
                            </SettingsRow>
                        )}
                        <SettingsRow
                            label={selfService.label}
                            description={selfService.description}
                            htmlFor="organizer-defaults-self-service"
                            inlineControl
                        >
                            <Switch
                                id="organizer-defaults-self-service"
                                {...form.getInputProps('default_allow_attendee_self_edit', {type: 'checkbox'})}
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
