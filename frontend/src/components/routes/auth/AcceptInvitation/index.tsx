import {Anchor, Button, Checkbox, PasswordInput, Select, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {timezones} from "../../../../../data/timezones.ts";
import {useNavigate, useParams} from "react-router";
import {hasLength, isEmail, matchesField, useForm} from "@mantine/form";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetInvitation} from "../../../../queries/useGetInvitation.ts";
import {useEffect} from "react";
import {useAcceptInvitation} from "../../../../mutations/useAcceptInvitation.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {AcceptInvitationRequest} from "../../../../types.ts";
import {getConfig} from "../../../../utilites/config.ts";
import classes from "./AcceptInvitation.module.scss";

const AcceptInvitation = () => {
    const navigate = useNavigate();
    const {token} = useParams();
    const form = useForm({
        initialValues: {
            first_name: '',
            last_name: '',
            email: '',
            password: '',
            timezone: '',
            password_confirmation: '',
            terms: false,
            marketing_opt_in: true,
        },
        validate: {
            first_name: hasLength({min: 1, max: 50}, t`First name must be between 1 and 50 characters`),
            password: hasLength({min: 8}, t`Password must be a minimum  of 8 characters`),
            password_confirmation: matchesField('password', t`Passwords are not the same`),
            email: isEmail(t`Please check your email is valid`),
            terms: (value) => value === true ? null : t`You must agree to the terms and conditions`,
        },
    });
    const {data: user, isFetched, isError, error} = useGetInvitation(String(token));
    const errorHandler = useFormErrorResponseHandler();
    const acceptInvitationMutation = useAcceptInvitation();

    useEffect(() => {
        if (!isError) {
            return;
        }

        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-ignore
        showError(error?.response?.data?.message || t`Something went wrong`)
        navigate('/auth/login');
    }, [isError]);

    useEffect(() => {
        if (!user) {
            return;
        }

        if (user?.data?.status !== 'INVITED') {
            showSuccess(t`You have already accepted this invitation. Please login to continue.`);
            navigate('/auth/login');
        }

        form.setValues({
            first_name: user?.data?.first_name,
            last_name: user?.data?.last_name,
            email: user?.data?.email,
            timezone: user?.data?.timezone,
        })

    }, [isFetched]);

    const handleSubmit = (values: AcceptInvitationRequest) => {
        acceptInvitationMutation.mutate({
            userData: values,
            token: String(token),
        }, {
            onSuccess: () => {
                showSuccess(t`Welcome aboard! Please login to continue.`);
                navigate('/auth/login');
            },
            onError: (error) => errorHandler(form, error),
        });
    }

    return (
        <>
            <header className={classes.header}>
                <h2>{t`Accept invitation`}</h2>
                <p>{t`Complete your profile to join the team.`}</p>
            </header>
            <div className={classes.invitationCard}>
                <form onSubmit={form.onSubmit(handleSubmit)}>
                    <fieldset disabled={!isFetched}>
                        <div className={classes.inputGroup}>
                            <TextInput
                                required
                                {...form.getInputProps('first_name')}
                                label={t`First Name`}
                                placeholder={t`John`}
                            />
                            <TextInput
                                {...form.getInputProps('last_name')}
                                label={t`Last Name`}
                                placeholder={t`Smith`}
                            />
                        </div>

                        <div className={classes.inputGroup}>
                            <TextInput
                                disabled
                                required
                                {...form.getInputProps('email')}
                                label={t`Email`}
                            />
                            <Select
                                required
                                searchable
                                data={timezones}
                                {...form.getInputProps('timezone')}
                                label={t`Timezone`}
                                placeholder={t`Select timezone`}
                            />
                        </div>

                        <div className={classes.inputGroup}>
                            <PasswordInput
                                {...form.getInputProps('password')}
                                label={t`Password`}
                                placeholder={t`Create a password`}
                                required
                            />
                            <PasswordInput
                                {...form.getInputProps('password_confirmation')}
                                label={t`Confirm Password`}
                                placeholder={t`Confirm password`}
                                required
                            />
                        </div>

                        <Checkbox
                            {...form.getInputProps('terms', {type: 'checkbox'})}
                            label={(
                                <Trans>
                                    I agree to the{' '}
                                    <Anchor
                                        target={'_blank'}
                                        href={getConfig("VITE_TOS_URL", 'https://hi.events/terms-of-service')}
                                    >
                                        terms and conditions
                                    </Anchor>
                                </Trans>
                            )}
                        />

                        <Checkbox
                            mb="md"
                            {...form.getInputProps('marketing_opt_in', {type: 'checkbox'})}
                            label={<Trans>Receive product updates from {getConfig("VITE_APP_NAME", "Hi.Events")}.</Trans>}
                        />

                        <Button
                            color="secondary.5"
                            fullWidth
                            loading={acceptInvitationMutation.isPending}
                            type="submit"
                        >
                            {t`Accept Invitation`}
                        </Button>
                    </fieldset>
                </form>
            </div>
        </>
    );
}

export default AcceptInvitation;
