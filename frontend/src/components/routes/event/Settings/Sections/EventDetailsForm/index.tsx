import {t} from "@lingui/macro";
import {Button, Select, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useEffect} from "react";
import {useUpdateEvent} from "../../../../../../mutations/useUpdateEvent.ts";
import {Event} from "../../../../../../types.ts";
import {InputGroup} from "../../../../../common/InputGroup";
import {Card} from "../../../../../common/Card";
import {Editor} from "../../../../../common/Editor";
import {utcToTz} from "../../../../../../utilites/dates.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.tsx";
import {currenciesMap} from "../../../../../../../data/currencies.ts";
import {timezones} from "../../../../../../../data/timezones.ts";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";

export const EventDetailsForm = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const updateMutation = useUpdateEvent();
    const form = useForm({
        initialValues: {
            title: '',
            description: '',
            start_date: '',
            end_date: '',
            timezone: '',
            currency: '',
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
            });
        }
    }, [eventQuery.isFetched]);

    const handleSubmit = (values: Partial<Event>) => {
        updateMutation.mutate({
            eventData: values,
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
                heading={t`Basic Details`}
                description={t`Update event name, description and dates`}
            />
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventQuery.isLoading || updateMutation.isPending}>
                    <TextInput
                        {...form.getInputProps('title')}
                        label={t`Name`}
                        placeholder={t`Hi.Events Conference ${new Date().getFullYear()}`}
                        required
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
