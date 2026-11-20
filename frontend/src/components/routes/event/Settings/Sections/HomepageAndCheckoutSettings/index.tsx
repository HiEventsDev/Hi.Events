import {t} from "@lingui/macro";
import {NumberInput, Switch} from "@mantine/core";
import {SettingsBlock, SettingsPage, SettingsRow, SettingsSaveBar, SettingsSection, SettingsSections} from "../../../../../common/SettingsPage";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useEffect} from "react";
import {EventSettings, PhoneCollection} from "../../../../../../types.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {useUpdateEventSettings} from "../../../../../../mutations/useUpdateEventSettings.ts";
import {useGetEventSettings} from "../../../../../../queries/useGetEventSettings.ts";
import {Editor} from "../../../../../common/Editor";
import {isEmptyHtml} from "../../../../../../utilites/helpers.ts";
import {CustomSelect, ItemProps} from "../../../../../common/CustomSelect";
import {IconCoin, IconCoins, IconUser, IconUsers} from "@tabler/icons-react";
import {selfServiceCopy} from "../../../../../common/SelfServiceSettings";
import {getPhoneCollectionOptions} from "../../../../../../utilites/phoneCollection.tsx";
import {useTextMessagesPaused, useTextMessagesVisible} from "../../../../../../ee/crm/hooks/useTextMessagesVisible.ts";

export const HomepageAndCheckoutSettings = () => {
    const {eventId} = useParams();
    const eventSettingsQuery = useGetEventSettings(eventId);
    const updateMutation = useUpdateEventSettings();
    const showTextMessageOptIn = useTextMessagesVisible();
    const isTextMessageOptInPaused = useTextMessagesPaused();
    const form = useForm({
        initialValues: {
            pre_checkout_message: '',
            post_checkout_message: '',
            order_timeout_in_minutes: 15,
            attendee_details_collection_method: 'PER_TICKET' as 'PER_TICKET' | 'PER_ORDER',
            show_marketing_opt_in: true,
            allow_copy_details_to_all_attendees: true,
            phone_collection: 'OFF' as PhoneCollection,
            show_sms_opt_in: false,
            price_display_mode: 'EXCLUSIVE',
            allow_attendee_self_edit: false,
        },
        transformValues: (values) => ({
            ...values,
            pre_checkout_message: isEmptyHtml(values.pre_checkout_message) ? null : values.pre_checkout_message,
            post_checkout_message: isEmptyHtml(values.post_checkout_message) ? null : values.post_checkout_message,
        }),
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
    const priceDisplayOptions: ItemProps[] = [
        {
            icon: <IconCoins/>,
            label: t`Include tax and fees in the price`,
            value: 'INCLUSIVE',
            description: t`The price displayed to the customer will include taxes and fees.`,
        },
        {
            icon: <IconCoin/>,
            label: t`Show tax and fees separately`,
            value: 'EXCLUSIVE',
            description: t`The price displayed to the customer will not include taxes and fees. They will be shown separately`,
        },
    ];
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventSettingsQuery?.isFetched && eventSettingsQuery?.data) {
            const values = {
                pre_checkout_message: eventSettingsQuery.data.pre_checkout_message,
                post_checkout_message: eventSettingsQuery.data.post_checkout_message,
                order_timeout_in_minutes: eventSettingsQuery.data.order_timeout_in_minutes,
                attendee_details_collection_method: eventSettingsQuery.data.attendee_details_collection_method || 'PER_TICKET',
                show_marketing_opt_in: eventSettingsQuery.data.show_marketing_opt_in ?? true,
                allow_copy_details_to_all_attendees: eventSettingsQuery.data.allow_copy_details_to_all_attendees ?? true,
                phone_collection: eventSettingsQuery.data.phone_collection ?? 'OFF',
                show_sms_opt_in: eventSettingsQuery.data.show_sms_opt_in ?? false,
                price_display_mode: eventSettingsQuery.data.price_display_mode,
                allow_attendee_self_edit: eventSettingsQuery.data.allow_attendee_self_edit ?? false,
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
                showSuccess(t`Successfully Updated Homepage Settings`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    const selfService = selfServiceCopy(false);
    const textOptInDescription = isTextMessageOptInPaused
        ? t`Text messages aren't available on this account at the moment, so checkout doesn't show this checkbox. Your choice is kept for when they are.`
        : form.values.phone_collection === 'OFF'
            ? t`Ask for the buyer's mobile number above to use this.`
            : t`Lets buyers with a mobile number in the United Kingdom, Ireland, the United States or Australia agree to receive text messages from this organizer. Only buyers who tick it can be sent marketing texts.`;

    return (
        <SettingsPage
            title={t`Checkout`}
            description={t`Customize the event homepage and checkout messaging`}
        >
            <form onSubmit={form.onSubmit(handleSubmit as any)}>
                <SettingsSections>
                    <fieldset disabled={eventSettingsQuery.isLoading || updateMutation.isPending}>
                        <SettingsSections>
                            <SettingsSection title={t`Messages`}>
                                <SettingsBlock>
                                    <Editor
                                        label={t`Pre Checkout message`}
                                        value={form.values.pre_checkout_message || ''}
                                        description={t`Shown to the customer before they checkout`}
                                        onChange={(value) => form.setFieldValue('pre_checkout_message', value)}
                                    />
                                </SettingsBlock>
                                <SettingsBlock>
                                    <Editor
                                        label={t`Post Checkout message`}
                                        value={form.values.post_checkout_message || ''}
                                        description={(
                                            <>
                                                {t`Shown to the customer after they checkout, on the order summary page.`}{' '}
                                                {t`This message will only be shown if order is completed successfully. Orders awaiting payment will not show this message`}
                                            </>
                                        )}
                                        onChange={(value) => form.setFieldValue('post_checkout_message', value)}
                                    />
                                </SettingsBlock>
                            </SettingsSection>

                            <SettingsSection title={t`Buyer and attendee details`}>
                                <SettingsBlock>
                                    <CustomSelect
                                        optionList={attendeeCollectionOptions}
                                        form={form}
                                        name={'attendee_details_collection_method'}
                                        label={t`Attendee information collection`}
                                        required
                                    />
                                </SettingsBlock>
                                <SettingsBlock>
                                    <CustomSelect
                                        optionList={getPhoneCollectionOptions()}
                                        form={form}
                                        name={'phone_collection'}
                                        label={t`Buyer's mobile number`}
                                        required
                                        dataTestId="phone-collection-select"
                                    />
                                </SettingsBlock>
                                <SettingsRow
                                    label={t`Allow buyers to copy their details to all attendees`}
                                    description={t`When enabled, buyers can copy their own name and email onto all attendees at once. Turn this off to remove the "All attendees" option; buyers can still copy to the first attendee, and the rest must be entered individually.`}
                                    htmlFor="event-checkout-copy-details"
                                    inlineControl
                                >
                                    <Switch
                                        id="event-checkout-copy-details"
                                        {...form.getInputProps('allow_copy_details_to_all_attendees', {type: 'checkbox'})}
                                    />
                                </SettingsRow>
                                <SettingsRow
                                    label={selfService.label}
                                    description={selfService.description}
                                    htmlFor="event-checkout-self-service"
                                    inlineControl
                                >
                                    <Switch
                                        id="event-checkout-self-service"
                                        {...form.getInputProps('allow_attendee_self_edit', {type: 'checkbox'})}
                                    />
                                </SettingsRow>
                            </SettingsSection>

                            <SettingsSection title={t`Pricing and order`}>
                                <SettingsBlock>
                                    <CustomSelect
                                        optionList={priceDisplayOptions}
                                        form={form}
                                        name={'price_display_mode'}
                                        label={t`Price display mode`}
                                        required
                                    />
                                </SettingsBlock>
                                <SettingsRow
                                    label={t`Order timeout`}
                                    description={t`How many minutes the customer has to complete their order. We recommend at least 15 minutes`}
                                    htmlFor="event-checkout-order-timeout"
                                >
                                    <NumberInput
                                        id="event-checkout-order-timeout"
                                        {...form.getInputProps('order_timeout_in_minutes')}
                                    />
                                </SettingsRow>
                            </SettingsSection>

                            <SettingsSection title={t`Marketing consent`}>
                                <SettingsRow
                                    label={t`Show marketing opt-in checkbox`}
                                    description={t`Display a checkbox allowing customers to opt-in to receive marketing communications from this event organizer.`}
                                    htmlFor="event-checkout-marketing-opt-in"
                                    inlineControl
                                >
                                    <Switch
                                        id="event-checkout-marketing-opt-in"
                                        {...form.getInputProps('show_marketing_opt_in', {type: 'checkbox'})}
                                    />
                                </SettingsRow>
                                {showTextMessageOptIn && (
                                    <SettingsRow
                                        label={t`Show text message opt-in checkbox`}
                                        description={textOptInDescription}
                                        htmlFor="event-checkout-sms-opt-in"
                                        inlineControl
                                    >
                                        <Switch
                                            id="event-checkout-sms-opt-in"
                                            disabled={form.values.phone_collection === 'OFF'}
                                            {...form.getInputProps('show_sms_opt_in', {type: 'checkbox'})}
                                        />
                                    </SettingsRow>
                                )}
                            </SettingsSection>
                        </SettingsSections>
                    </fieldset>
                    <SettingsSaveBar
                        isDirty={form.isDirty()}
                        isSaving={updateMutation.isPending}
                        onDiscard={form.reset}
                    />
                </SettingsSections>
            </form>
        </SettingsPage>
    );
}
