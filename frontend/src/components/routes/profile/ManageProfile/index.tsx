import {Card} from "../../../common/Card";
import {useForm, UseFormReturnType} from "@mantine/form";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {Alert, Button, PasswordInput, Select, Tabs, TextInput} from "@mantine/core";
import classes from "./ManageProfile.module.scss";
import {useEffect} from "react";
import {IconInfoCircle, IconPassword, IconUser} from "@tabler/icons-react";
import {timezones} from "../../../../../data/timezones.ts";
import {useUpdateMe} from "../../../../mutations/useUpdateMe.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {UserMeRequest} from "../../../../api/user.client.ts";
import {useCancelEmailChange} from "../../../../mutations/useCancelEmailChange.ts";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.ts";
import {t, Trans} from "@lingui/macro";

export const ManageProfile = () => {
    const {data: me, isFetching} = useGetMe();
    const mutation = useUpdateMe();
    const cancelEmailChangeMutation = useCancelEmailChange();
    const errorHandler = useFormErrorResponseHandler();
    const profileForm = useForm({
        initialValues: {
            first_name: me?.first_name,
            last_name: me?.last_name,
            email: me?.email,
            timezone: me?.timezone,
        },
    });

    const passwordForm = useForm({
        initialValues: {
            current_password: '',
            password: '',
            password_confirmation: '',
        }
    });

    useEffect(() => {
        profileForm.setValues({
            first_name: me?.first_name,
            last_name: me?.last_name,
            email: me?.email,
            timezone: me?.timezone,
        });
    }, [me]);


    const handleProfileFormSubmit = (formValues: Partial<UserMeRequest>, form: UseFormReturnType<any>) => {
        mutation.mutate({
            userData: formValues,
        }, {
            onSuccess: () => {
                showSuccess(t`Profile updated successfully`);
            },
            onError: (error) => {
                showError(t`Something went wrong. Please try again.`);
                errorHandler(form, error);
            }
        })
    }

    const handleCancelEmailChange = () => {
        cancelEmailChangeMutation.mutate({
            userId: me?.id
        }, {
            onSuccess: () => {
                showSuccess(t`Email change cancelled successfully`)
            },
            onError: () => {
                showError(t`Something went wrong. Please try again.`)
            }
        })
    }

    return (
        <div className={classes.container}>
            <h1>{t`Manage Profile`}</h1>
            <Card className={classes.tabsCard}>
                <Tabs defaultValue="profile">
                    <Tabs.List grow>
                        <Tabs.Tab value="profile" leftSection={<IconUser/>}>
                            {t`Profile`}
                        </Tabs.Tab>
                        <Tabs.Tab value="password" leftSection={<IconPassword/>}>
                            {t`Password`}
                        </Tabs.Tab>
                    </Tabs.List>
                    <Tabs.Panel value="profile">
                        <div className={classes.tabWrapper}>
                            {me?.has_pending_email_change && (
                                <Alert className={classes.emailChangeAlert} variant="light" color="blue"
                                       title={t`Email change pending`} icon={<IconInfoCircle/>}>
                                    <p>
                                        <Trans>Your email request change to <b>{me?.pending_email}</b> is pending.
                                            Please check your email to confirm</Trans>
                                    </p>
                                    <p>
                                        {t`If you did not request this change, please immediately change your password.`}
                                    </p>
                                    <p>
                                        <Button onClick={handleCancelEmailChange} size={'xs'}>
                                            {t`Cancel email change`}
                                        </Button>
                                    </p>
                                </Alert>
                            )}
                            <form
                                onSubmit={profileForm.onSubmit((values) => handleProfileFormSubmit(values, profileForm))}>
                                <fieldset disabled={isFetching}>
                                    <TextInput required {...profileForm.getInputProps('first_name')}
                                               label={t`First Name`}/>
                                    <TextInput required {...profileForm.getInputProps('last_name')}
                                               label={t`Last Name`}/>
                                    <TextInput required {...profileForm.getInputProps('email')} label={t`Email`}/>
                                    <Select
                                        required
                                        searchable
                                        data={timezones}
                                        {...profileForm.getInputProps('timezone')}
                                        label={t`Timezone`}
                                        placeholder={t`UTC`}
                                    />

                                    <Button fullWidth loading={mutation.isLoading}
                                            type={'submit'}>{t`Update profile`}</Button>
                                </fieldset>
                            </form>
                        </div>
                    </Tabs.Panel>

                    <Tabs.Panel value="password">
                        <div className={classes.tabWrapper}>
                            <form
                                onSubmit={passwordForm.onSubmit((values) => handleProfileFormSubmit(values, passwordForm))}>
                                <fieldset disabled={isFetching}>
                                    <PasswordInput
                                        required
                                        {...passwordForm.getInputProps('current_password')}
                                        label={t`Current Password`}/>
                                    <PasswordInput
                                        required
                                        {...passwordForm.getInputProps('password')}
                                        label={t`New Password`}/>
                                    <PasswordInput
                                        required
                                        {...passwordForm.getInputProps('password_confirmation')}
                                        label={t`Confirm New Password`}/>
                                    <Button fullWidth loading={mutation.isLoading}
                                            type={'submit'}>{t`Change password`}</Button>
                                </fieldset>
                            </form>
                        </div>
                    </Tabs.Panel>
                </Tabs>
            </Card>
        </div>
    );
}

export default ManageProfile;
