import {useGetMe} from "../../../queries/useGetMe.ts";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {t, Trans} from "@lingui/macro";
import {Card} from "../../common/Card";
import {Button, Group, PinInput, SimpleGrid, Stack, Text, TextInput} from "@mantine/core";
import classes from "./Welcome.module.scss";
import {useForm} from "@mantine/form";
import {Event} from "../../../types.ts";
import {useCreateEvent} from "../../../mutations/useCreateEvent.ts";
import {NavLink, useNavigate} from "react-router";
import {useEffect, useState} from "react";
import {useGetEvents} from "../../../queries/useGetEvents.ts";
import {LoadingContainer} from "../../common/LoadingContainer";
import {OrganizerCreateForm} from "../../forms/OrganizerForm";
import {useConfirmEmailWithCode} from "../../../mutations/useConfirmEmailWithCode.ts";
import {useResendEmailConfirmation} from "../../../mutations/useResendEmailConfirmation.ts";
import {IconClock, IconMailCheck} from "@tabler/icons-react";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";

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

const ConfirmVerificationPin = () => {
    const {data: userData} = useGetMe();
    const confirmEmailMutation = useConfirmEmailWithCode();
    const resendMutation = useResendEmailConfirmation();
    const [resendCooldown, setResendCooldown] = useState(0);

    const form = useForm({
        initialValues: {
            pin: '',
        },
        validate: {
            pin: (value) => value.length !== 5 ? t`Please enter the 5-digit code` : null,
        }
    });

    useEffect(() => {
        if (resendCooldown > 0) {
            const timer = setTimeout(() => setResendCooldown(resendCooldown - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [resendCooldown]);

    const handleSubmit = (values: { pin: number }) => {
        confirmEmailMutation.mutate({
                userId: userData?.id || '',
                code: values.pin,
            }, {
                onSuccess: () => {
                    showSuccess(t`Email verified successfully!`);
                    form.reset();
                },
                onError: (error) => {
                    showError(error.response?.data?.message || t`Failed to verify email`);
                }
            }
        );
    }

    const handleResend = async () => {
        if (!userData?.id) return;

        try {
            await resendMutation.mutateAsync({userId: userData.id});
            showSuccess(t`A new verification code has been sent to your email`);
            setResendCooldown(30);
            form.reset();
        } catch (error: any) {
            if (error?.response?.status === 429) {
                const remainingSeconds = error.response.data?.message?.match(/\d+/)?.[0] || 30;
                setResendCooldown(parseInt(remainingSeconds));
                showError(error.response.data?.message || t`Please wait before requesting another code`);
            } else {
                showError(t`Failed to resend verification code`);
            }
        }
    }

    return (
        <>
            <h3 className={classes.sectionHeadings}>
                {t`Let's get started by confirming your email address`}
            </h3>
            <p className={classes.sectionDescription}>
                {t`We've sent a verification code to your email address. Please enter it below to verify your account.`}
            </p>

            <>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <Stack gap="lg">
                        <div>
                            <PinInput
                                {...form.getInputProps('pin')}
                                inputMode={'numeric'}
                                aria-label={t`Verification code`}
                                size="xl"
                                length={5}
                                placeholder="•"
                                type="number"
                                disabled={confirmEmailMutation.isPending}
                                error={!!form.errors.pin}
                                styles={{
                                    input: {
                                        textAlign: 'center',
                                        fontWeight: 600,
                                    }
                                }}
                            />
                        </div>

                        <Button
                            type={'submit'}
                            fullWidth
                            loading={confirmEmailMutation.isPending}
                            leftSection={<IconMailCheck size={20}/>}
                            color="green"
                        >
                            {confirmEmailMutation.isPending ? t`Verifying...` : t`Verify Email`}
                        </Button>

                        <Group justify="left">
                            <Text size="sm" c="dimmed">
                                {t`Didn't receive the code?`}
                            </Text>
                            <Button
                                ml={0}
                                variant="subtle"
                                size="xs"
                                onClick={handleResend}
                                disabled={resendCooldown > 0 || resendMutation.isPending}
                                loading={resendMutation.isPending}
                                leftSection={resendCooldown > 0 ? <IconClock size={16}/> : null}
                            >
                                {resendCooldown > 0
                                    ? t`Resend in ${resendCooldown}s`
                                    : t`Resend Code`}
                            </Button>
                        </Group>
                    </Stack>
                </form>
            </>
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
                {t`An event is the gathering or occasion you’re organizing. You can add more details later.`}
            </p>

            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={eventMutation.isPending}>
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
                        loading={eventMutation.isPending}
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

    const requiresVerification = userData
        && userData.enforce_email_confirmation_during_registration
        && !userData.is_email_verified;

    return (
        <>
            <h1>
                <Trans>
                    Welcome to Hi.Events, {userData?.first_name} 👋
                </Trans>
            </h1>
            <Card>
                {requiresVerification && <ConfirmVerificationPin/>}
                {(!requiresVerification && organizerExists) && <CreateEvent/>}
                {(!requiresVerification && !organizerExists) && <CreateOrganizer/>}
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
