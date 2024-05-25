import {useGetMe} from "../../../queries/useGetMe.ts";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {t, Trans} from "@lingui/macro";
import {Card} from "../../common/Card";
import {Button, SimpleGrid, TextInput} from "@mantine/core";
import classes from "./Welcome.module.scss";
import {useForm} from "@mantine/form";
import {Event} from "../../../types.ts";
import {useCreateEvent} from "../../../mutations/useCreateEvent.ts";
import {NavLink, useNavigate} from "react-router-dom";
import {useEffect} from "react";
import {useGetEvents} from "../../../queries/useGetEvents.ts";
import {LoadingContainer} from "../../common/LoadingContainer";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";

export const CreateOrganizer = () => {
    return (
        <>
            <h3 className={classes.sectionHeadings}>
                {t`Let's get started by creating your first organizer`}
            </h3>
            <p className={classes.sectionDescription}>
                {t`An organizer is the company or person who is hosting the event`}
            </p>
            <OrganizerCreateForm/>
        </>
    );
}

export const CreateEvent = () => {
    const form = useForm({
        initialValues: {
            title: '',
            start_date: undefined,
            end_date: undefined,
        }
    });
    const eventMutation = useCreateEvent();
    const navigate = useNavigate();
    const {data: organizers, isFetched: organizersFetched} = useGetOrganizers();
    const {data: events, isFetched: eventsFetched} = useGetEvents({
        pageNumber: 1,
    });

    const handleSubmit = (values: Partial<Event>) => {
        eventMutation.mutate({
            eventData: values,
        }, {
            onSuccess: (values) => {
                navigate(`/manage/event/${values.data.id}/getting-started?new_event=true`)
            }
        });
    }

    useEffect(() => {
        if (organizersFetched) {
            const organizerName = organizers?.data?.[0].name
            form.setFieldValue('organizer_id', organizers?.data?.[0].id);
            form.setFieldValue('title', t`${organizerName}'s first event`);
        }
    }, [organizersFetched]);

    useEffect(() => {
        if (eventsFetched && events && events.data.length > 0) {
            navigate(`/manage/events`);
        }
    }, [eventsFetched]);

    return (
        <LoadingContainer>
            <h3 className={classes.sectionHeadings}>
                {t`Now let's create your first event`}
            </h3>
            <p className={classes.sectionDescription}>
                {t`An event is the actual event you are hosting. You can add more details later.`}
            </p>

            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventMutation.isLoading}>
                    <TextInput
                        {...form.getInputProps('title')}
                        required
                        label={t`Name`}
                        placeholder={t`Awesome Event`}
                    />
                    <SimpleGrid cols={2} spacing={20}>
                        <TextInput
                            {...form.getInputProps('start_date')}
                            required
                            label={t`Start Date`}
                            placeholder={t`2024-01-01 10:00`}
                            type={'datetime-local'}
                        />
                        <TextInput
                            {...form.getInputProps('end_date')}
                            label={t`End Date`}
                            placeholder={t`2024-01-01 18:00`}
                            type={'datetime-local'}
                        />
                    </SimpleGrid>

                    <Button
                        type={'submit'}
                        color={'green'}
                        fullWidth
                        loading={eventMutation.isLoading}
                    >
                        {t`Create Event`}
                    </Button>
                </fieldset>
            </form>
        </LoadingContainer>
    );
}

const Welcome = () => {
    const {data: userData} = useGetMe();
    const organizersQuery = useGetOrganizers();
    const organizers = organizersQuery?.data?.data;
    const organizerExists = organizersQuery.isFetched && Number(organizers?.length) > 0;

    return (
        <>
            <h1>
                <Trans>
                    Welcome to Hi.Events, {userData?.first_name} 👋
                </Trans>
            </h1>
            <Card>
                {organizerExists ? <CreateEvent/> : <CreateOrganizer/>}
            </Card>
            {organizerExists && (
                <div className={classes.skip}>
                    <NavLink
                        to={'/manage/events'}
                    >
                        {t`Skip this step`}
                    </NavLink>
                </div>
            )}
        </>
    )
}

export default Welcome;