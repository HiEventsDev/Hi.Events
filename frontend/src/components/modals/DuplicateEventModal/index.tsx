import {t} from "@lingui/macro";
import {EventDuplicatePayload, GenericModalProps, IdParam} from "../../../types.ts";
import {Button, Switch, TextInput} from "@mantine/core";
import {Modal} from "../../common/Modal";
import {useForm} from "@mantine/form";
import {useDuplicateEvent} from "../../../mutations/useDuplicateEvent.ts";
import {Editor} from "../../common/Editor";
import {InputGroup} from "../../common/InputGroup";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useNavigate} from "react-router-dom";
import {useEffect} from "react";
import {utcToTz} from "../../../utilites/dates.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";

interface DuplicateEventModalProps extends GenericModalProps {
    eventId: IdParam;
}

export const DuplicateEventModal = ({onClose, eventId}: DuplicateEventModalProps) => {
    const form = useForm({
        initialValues: {
            title: '',
            start_date: '',
            end_date: '',
            description: '',
            duplicate_tickets: true,
            duplicate_questions: true,
            duplicate_settings: true,
            duplicate_promo_codes: true,
        }
    });
    const mutation = useDuplicateEvent();
    const eventQuery = useGetEvent(eventId);
    const nav = useNavigate();


    useEffect(() => {
        if (eventQuery?.data) {
            form.setValues({
                title: eventQuery.data.title,
                description: eventQuery.data.description,
                start_date: utcToTz(eventQuery.data.start_date, eventQuery.data.timezone),
                end_date: utcToTz(eventQuery.data.end_date, eventQuery.data.timezone),
            });
        }
    }, [eventQuery.isFetched]);

    const handleDuplicate = (eventId: IdParam, duplicateData: EventDuplicatePayload) => {
        mutation.mutate({eventId, duplicateData}, {
            onSuccess: ({data}) => {
                nav(`/manage/event/${data.id}`);
                showSuccess(t`Event duplicated successfully`);
            }
        });
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Duplicate Event`}
            opened
            size={'lg'}
            withCloseButton
        >
            <form onSubmit={form.onSubmit((values) => handleDuplicate(eventId, values))}>
                <fieldset disabled={eventQuery.isLoading || mutation.isLoading}>
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

                    <Switch
                        {...form.getInputProps('duplicate_tickets', {type: 'checkbox'})}
                        label={t`Duplicate Tickets`}
                    />
                    <Switch
                        {...form.getInputProps('duplicate_questions', {type: 'checkbox'})}
                        label={t`Duplicate Questions`}
                    />
                    <Switch
                        {...form.getInputProps('duplicate_settings', {type: 'checkbox'})}
                        label={t`Duplicate Settings`}
                    />
                    <Switch
                        {...form.getInputProps('duplicate_promo_codes', {type: 'checkbox'})}
                        label={t`Duplicate Promo Codes`}
                    />
                </fieldset>
                <Button type="submit" fullWidth disabled={mutation.isLoading}>
                    {mutation.isLoading ? t`Working...` : t`Duplicate Event`}
                </Button>
            </form>
        </Modal>
    )
}