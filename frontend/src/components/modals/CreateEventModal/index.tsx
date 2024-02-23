import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.ts";
import {useNavigate} from "react-router-dom";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {Event, GenericModalProps, Organizer} from "../../../types.ts";
import {useEffect, useState} from "react";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Anchor, Button, Select, SimpleGrid, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Modal} from "../../common/Modal";
import {useCreateEvent} from "../../../mutations/useCreateEvent.ts";
import {Editor} from "../../common/Editor";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {IconUsers} from "@tabler/icons-react";
import classes from "./CreateEventModal.module.scss";
import {OrganizerForm} from "../../forms/OrganizerForm";
import {Card} from "../../common/Card";

export const CreateEventModal = ({onClose}: GenericModalProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const navigate = useNavigate();
    const {data: account, isFetched: isAccountFetched} = useGetAccount();
    const organizersQuery = useGetOrganizers();
    const form = useForm<Partial<Event>>({
        initialValues: {
            title: '',
            status: '',
            start_date: '',
            end_date: undefined,
            description: undefined,
            organizer_id: undefined,
        }
    });
    const eventMutation = useCreateEvent();
    const [showCreateOrganizer, setShowCreateOrganizer] = useState(false);

    useEffect(() => {
        if (isAccountFetched) {
            form.setFieldValue('currency', account?.currency_code);
            form.setFieldValue('timezone', account?.timezone);
        }
    }, [isAccountFetched]);

    const handleCreate = (values: Partial<Event>) => {
        eventMutation.mutateAsync({
            eventData: values,
        }).then((data) => {
            showSuccess(t`Event created successfully 🎉`);
            navigate(`/manage/event/${data.data.id}/getting-started?new_event=true`)

        }).catch((error) => {
            errorHandler(form, error);
        });
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Create Event`}
            opened
            size={'lg'}
        >
            {showCreateOrganizer && (
                <Card>
                    <h3 className={classes.createOrganizerHeading}>
                        {t`Create Organizer`}
                    </h3>
                    <OrganizerForm
                        onCancel={() => setShowCreateOrganizer(false)}
                        onSuccess={(organizer: Organizer) => {
                            setShowCreateOrganizer(false);
                            form.setFieldValue('organizer_id', String(organizer.id));
                        }}/>
                </Card>
            )}
            {!showCreateOrganizer && (
                <>
                    <Select
                        {...form.getInputProps('organizer_id')}
                        label={t`Who is organizing this event?`}
                        required
                        leftSection={<IconUsers size={18}/>}
                        placeholder={t`Select organizer`}
                        data={organizersQuery.data?.data?.map((organizer) => ({
                            value: String(organizer.id),
                            label: organizer.name,
                        }))}
                        mb={0}
                    />
                    <div className={classes.createOrganizerLink}>
                        {t`or`} {'  '}
                        <Anchor href={'#'} variant={'transparent'} onClick={() => setShowCreateOrganizer(true)}>
                            {t`create an organizer`}
                        </Anchor>
                    </div>
                </>
            )}

            <form onSubmit={form.onSubmit(handleCreate)}>
                <TextInput
                    {...form.getInputProps('title')}
                    label={t`Name`}
                    placeholder={t`KittenTech Conference ${new Date().getFullYear()}`}
                    required
                />

                <Editor
                    label={t`Description`}
                    value={form.values.description || ''}
                    onChange={(value) => form.setFieldValue('description', value)}
                    error={form.errors.description as string}
                />

                <SimpleGrid mt={20} cols={2}>
                    <TextInput type={'datetime-local'}
                               {...form.getInputProps('start_date')}
                               label={t`Start Date`}
                               required
                    />
                    <TextInput type={'datetime-local'}
                               {...form.getInputProps('end_date')}
                               label={t`End Date`}
                    />
                </SimpleGrid>
                <Button loading={eventMutation.isLoading} mt={20} fullWidth type={'submit'}>
                    {t`Continue Event Setup`}
                </Button>
            </form>
        </Modal>
    );
}