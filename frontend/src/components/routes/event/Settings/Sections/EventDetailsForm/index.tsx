import {t} from "@lingui/macro";
import {Button, Select, TextInput} from "@mantine/core";
import {SettingsBlock, SettingsPage, SettingsRow, SettingsSaveFooter, SettingsSection} from "../../../../../common/SettingsPage";
import {IconCalendarRepeat} from "@tabler/icons-react";
import {useForm} from "@mantine/form";
import {NavLink, useParams} from "react-router";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useEffect} from "react";
import {useUpdateEvent} from "../../../../../../mutations/useUpdateEvent.ts";
import {Event, EventType} from "../../../../../../types.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {Callout} from "../../../../../common/Callout";
import {Editor} from "../../../../../common/Editor";
import {utcToTz} from "../../../../../../utilites/dates.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {currenciesMap} from "../../../../../../../data/currencies.ts";
import {timezones} from "../../../../../../../data/timezones.ts";
import {getEventCategories} from "../../../../../../constants/eventCategories.ts";
import {DateTimeField} from "../../../../../common/DateTimeField";

export const EventDetailsForm = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const updateMutation = useUpdateEvent();
    const isRecurring = eventQuery.data?.type === EventType.RECURRING;
    const form = useForm({
        initialValues: {
            title: '',
            description: '',
            start_date: '',
            end_date: '',
            timezone: '',
            currency: '',
            category: '',
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventQuery?.data) {
            const values = {
                title: eventQuery.data.title,
                description: eventQuery.data.description,
                start_date: utcToTz(eventQuery.data.start_date, eventQuery.data.timezone),
                end_date: utcToTz(eventQuery.data.end_date, eventQuery.data.timezone),
                timezone: eventQuery.data.timezone,
                currency: eventQuery.data.currency,
                category: eventQuery.data.category,
            };
            form.setInitialValues(values as typeof form.values);
            form.setValues(values);
        }
    }, [eventQuery.isFetched]);

    const handleSubmit = (values: Partial<Event>) => {
        updateMutation.mutate({
            eventData: values,
            eventId: eventId,
        }, {
            onSuccess: () => {
                form.resetDirty();
                showSuccess(t`Successfully Updated Event`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <SettingsPage
            title={t`Event Details`}
            description={isRecurring
                ? t`Update event name and description`
                : t`Update event name, description and dates`}
        >
            <SettingsSection>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={eventQuery.isLoading || updateMutation.isPending}>
                        <SettingsRow label={t`Name`} htmlFor="event-details-name" required>
                            <TextInput
                                id="event-details-name"
                                {...form.getInputProps('title')}
                                placeholder={t`Summer Music Festival ${new Date().getFullYear()}`}
                                required
                            />
                        </SettingsRow>

                        <SettingsRow label={t`Category`} htmlFor="event-details-category">
                            <Select
                                id="event-details-category"
                                {...form.getInputProps('category')}
                                placeholder={t`Select a category`}
                                data={getEventCategories().map((category) => ({
                                    value: category.id,
                                    label: `${category.emoji} ${category.name}`,
                                }))}
                                searchable
                                clearable
                            />
                        </SettingsRow>

                        <SettingsBlock>
                            <Editor
                                label={t`Description`}
                                value={form.values.description || ''}
                                onChange={(value) => form.setFieldValue('description', value)}
                                error={form.errors?.description as string}
                            />
                        </SettingsBlock>

                        <SettingsBlock>
                            {isRecurring ? (
                                <Callout variant="info" title={t`Dates are managed per occurrence`}>
                                    {t`This event's dates and times are set on the occurrence schedule.`}
                                    <div style={{marginTop: '0.75rem'}}>
                                        <Button
                                            component={NavLink}
                                            to={'/manage/event/' + eventId + '/occurrences'}
                                            leftSection={<IconCalendarRepeat size={16}/>}
                                            variant="light"
                                        >
                                            {t`Manage schedule`}
                                        </Button>
                                    </div>
                                </Callout>
                            ) : (
                                <InputGroup>
                                    <DateTimeField
                                        {...form.getInputProps('start_date')}
                                        label={t`Start Date`}
                                        required
                                    />
                                    <DateTimeField
                                        {...form.getInputProps('end_date')}
                                        label={t`End Date`}
                                        defaultTime="23:59"
                                    />
                                </InputGroup>
                            )}
                        </SettingsBlock>

                        <SettingsRow
                            label={t`Currency`}
                            description={t`The currency used for this event's ticket prices.`}
                            htmlFor="event-details-currency"
                        >
                            <Select
                                id="event-details-currency"
                                searchable
                                data={currenciesMap}
                                {...form.getInputProps('currency')}
                                placeholder={t`EUR`}
                            />
                        </SettingsRow>

                        <SettingsRow
                            label={t`Timezone`}
                            description={t`The timezone used for this event's dates and times.`}
                            htmlFor="event-details-timezone"
                        >
                            <Select
                                id="event-details-timezone"
                                searchable
                                data={timezones}
                                {...form.getInputProps('timezone')}
                                placeholder={t`UTC`}
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
