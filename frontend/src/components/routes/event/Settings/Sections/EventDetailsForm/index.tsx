import {t} from "@lingui/macro";
import {Button, Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useGetOrganizers} from "../../../../../../queries/useGetOrganizers.ts";
import {useEffect} from "react";
import {useUpdateEvent} from "../../../../../../mutations/useUpdateEvent.ts";
import {Event, OrganizerStatus} from "../../../../../../types.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {Card} from "../../../../../common/Card";
import {Editor} from "../../../../../common/Editor";
import {utcToTz} from "../../../../../../utilites/dates.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {currenciesMap} from "../../../../../../../data/currencies.ts";
import {timezones} from "../../../../../../../data/timezones.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {EventCategories} from "../../../../../../constants/eventCategories.ts";

export const EventDetailsForm = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const organizersQuery = useGetOrganizers();
    const updateMutation = useUpdateEvent();
    const form = useForm<{
        title: string;
        description: string;
        start_date: string;
        end_date: string;
        timezone: string;
        currency: string;
        category: string;
        organizer_id: string;
    }>({
        initialValues: {
            title: '',
            description: '',
            start_date: '',
            end_date: '',
            timezone: '',
            currency: '',
            category: '',
            organizer_id: '',
        }
    });
    const formErrorHandle = useFormErrorResponseHandler();

    useEffect(() => {
        if (eventQuery?.data) {
            form.setValues({
                title: eventQuery.data.title,
                description: eventQuery.data.description,
                start_date: utcToTz(eventQuery.data.start_date, eventQuery.data.timezone),
                end_date: utcToTz(eventQuery.data.end_date, eventQuery.data.timezone),
                timezone: eventQuery.data.timezone,
                currency: eventQuery.data.currency,
                category: eventQuery.data.category,
                organizer_id: eventQuery.data.organizer_id ? String(eventQuery.data.organizer_id) : '',
            });
        }
    }, [eventQuery.isFetched]);

    const handleSubmit = (values: typeof form.values) => {
        const eventData: Partial<Event> = {
            ...values,
            organizer_id: values.organizer_id ? Number(values.organizer_id) : undefined,
        };

        updateMutation.mutate({
            eventData,
            eventId: eventId,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully Updated Event`);
            },
            onError: (error) => {
                formErrorHandle(form, error);
            }
        });
    }

    return (
        <Card>
            <HeadingWithDescription
                heading={t`Event Details`}
                description={t`Update event name, description and dates`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventQuery.isLoading || updateMutation.isPending}>
                    <TextInput
                        {...form.getInputProps('title')}
                        label={t`Name`}
                        placeholder={t`Summer Music Festival ${new Date().getFullYear()}`}
                        required
                    />
                    
                    <Select
                        {...form.getInputProps('category')}
                        label={t`Category`}
                        placeholder={t`Select a category`}
                        data={EventCategories.map((category) => ({
                            value: category.id,
                            label: `${category.emoji} ${category.name}`,
                        }))}
                        searchable
                        clearable
                    />

                    <Select
                        {...form.getInputProps('organizer_id')}
                        label={t`Organizer`}
                        description={t`The brand this event is published under`}
                        placeholder={t`Select an organizer`}
                        data={(organizersQuery.data?.data ?? [])
                            .filter((org) => org.status !== OrganizerStatus.ARCHIVED)
                            .map((org) => ({
                                value: String(org.id),
                                label: org.name,
                            }))}
                        searchable
                        required
                        disabled={organizersQuery.isLoading}
                    />

                    <Editor
                        label={t`Description`}
                        value={form.values.description || ''}
                        onChange={(value) => form.setFieldValue('description', value)}
                        error={form.errors?.description as string}
                    />

                    <InputGroup>
                        <TextInput type={'datetime-local'}
                                   {...form.getInputProps('start_date')}
                                   label={t`Start Date`}
                                   required
                        />
                        <TextInput type={'datetime-local'}
                                   {...form.getInputProps('end_date')}
                                   label={t`End Date`}
                        />
                    </InputGroup>
                    <InputGroup>
                        <Select
                            searchable
                            data={currenciesMap}
                            {...form.getInputProps('currency')}
                            label={t`Currency`}
                            placeholder={t`EUR`}
                            disabled
                        />

                        <Select
                            searchable
                            data={timezones}
                            {...form.getInputProps('timezone')}
                            label={t`Timezone`}
                            placeholder={t`UTC`}
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
