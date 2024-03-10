import {Button, Select, TextInput} from "@mantine/core";
import {currencies} from "../../../../../../../data/currencies.ts";
import {timezones} from "../../../../../../../data/timezones.ts";
import {useForm} from "@mantine/form";
import classes from "../../ManageAccount.module.scss";
import {useGetAccount} from "../../../../../../queries/useGetAccount.ts";
import {useEffect} from "react";
import {t} from "@lingui/macro";
import {Card} from "../../../../../common/Card";
import {HeadingCard} from "../../../../../common/HeadingCard";
import {useUpdateAccount} from "../../../../../../mutations/useUpdateAccount.ts";
import {showSuccess} from "../../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../../hooks/useFormErrorResponseHandler.ts";
import {Account} from "../../../../../../types.ts";
import {LoadingMask} from "../../../../../common/LoadingMask";

export const AccountSettings = () => {
    const form = useForm({
        initialValues: {
            name: '',
            currency_code: '',
            timezone: '',
            email: '',
        }
    });
    const accountQuery = useGetAccount();
    const updateMutation = useUpdateAccount();
    const formErrorHandler = useFormErrorResponseHandler();

    useEffect(() => {
        if (accountQuery.data) {
            form.setValues(accountQuery.data);
        }
    }, [accountQuery.isFetched]);

    const handleSubmit = (values: Account) => {
        updateMutation.mutate({
            accountData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Account updated successfully`);
            },
            onError: (error) => {
                formErrorHandler(form, error);
            }
        });
    }

    return (
        <>
            <HeadingCard
                heading={t`Account`}
                subHeading={t`Manage your account details and default settings`}
            />
            <Card className={classes.tabContent}>
                <LoadingMask/>
                <fieldset disabled={updateMutation.isLoading || accountQuery.isLoading}>
                    <form onSubmit={form.onSubmit(handleSubmit)}>
                        <TextInput
                            {...form.getInputProps('name')}
                            label={t`Account Name`}
                            placeholder={t`Name`}
                            description={t`Your account name is used on event pages and in emails.`}
                        />
                        <TextInput
                            type={'email'}
                            {...form.getInputProps('email')}
                            label={t`Account Email`}
                            placeholder={t`Email`}
                            description={t`Your account email in outgoing emails.`}
                        />
                        <Select
                            searchable
                            data={Object.entries(currencies).map(([key, value]) => ({
                                value: value,
                                label: key,
                            }))}
                            {...form.getInputProps('currency_code')}
                            label={t`Currency`}
                            placeholder={t`EUR`}
                            description={t`The default currency for your events.`}
                        />
                        <Select
                            mb={0}
                            searchable
                            data={timezones}
                            {...form.getInputProps('timezone')}
                            label={t`Timezone`}
                            placeholder={t`UTC`}
                            description={t`The default timezone for your events.`}
                        />
                        <div className={classes.footer}>
                            <Button disabled={updateMutation.isLoading} type={'submit'} fullWidth>{t`Save Settings`}</Button>
                        </div>
                    </form>
                </fieldset>
            </Card>
        </>
    );
}

export default AccountSettings;
