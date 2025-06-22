import {useCreateOrganizer} from "../../../mutations/useCreateOrganizer.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {useForm, UseFormReturnType} from "@mantine/form";
import {Organizer} from "../../../types.ts";
import {useEffect} from "react";
import {LoadingContainer} from "../../common/LoadingContainer";
import {t} from "@lingui/macro";
import {Button, Select, Stack, TextInput} from "@mantine/core";
import {currencies} from "../../../../data/currencies.ts";
import {timezones} from "../../../../data/timezones.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {IconBuilding} from "@tabler/icons-react";
import classes from "../../routes/welcome/Welcome.module.scss";

interface OrganizerFormProps {
    onSuccess?: (organizer: Organizer) => void;
    onCancel?: () => void;
}

export const OrganizerForm = ({form}: { form: UseFormReturnType<Partial<Organizer>> }) => {
    return (
        <Stack gap={24}>
            <TextInput
                {...form.getInputProps('name')}
                required
                label={t`Organization Name`}
                placeholder={t`Awesome Events Ltd.`}
                size="lg"
            />
            <TextInput
                {...form.getInputProps('email')}
                required
                label={t`Contact Email`}
                placeholder={t`hello@awesome-events.com`}
                size="lg"
                type="email"
            />

            <div className={classes.dateTimeGrid}>
                <Select
                    {...form.getInputProps('currency')}
                    searchable
                    required
                    data={Object.entries(currencies).map(([key, value]) => ({
                        value: value,
                        label: `${key} (${value})`,
                    }))}
                    label={t`Currency`}
                    placeholder={t`Select currency`}
                    size="lg"
                />
                <Select
                    {...form.getInputProps('timezone')}
                    searchable
                    required
                    data={timezones}
                    label={t`Timezone`}
                    placeholder={t`Select timezone`}
                    size="lg"
                />
            </div>
        </Stack>
    )
}

export const OrganizerCreateForm = ({onSuccess, onCancel}: OrganizerFormProps) => {
    const organizerMutation = useCreateOrganizer();
    const {data: account, isFetched: accountFetched} = useGetAccount();
    const {data: me, isFetched: meFetched} = useGetMe();
    const form = useForm({
        initialValues: {
            name: '',
            email: '',
            currency: '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        }
    });

    const handleSubmit = (values: Partial<Organizer>) => {
        organizerMutation.mutate({
            organizerData: values,
        }, {
            onSuccess: ({data: organizer}) => {
                if (onSuccess) {
                    onSuccess(organizer);
                }
            },
            onError: (error: any) => {
                useFormErrorResponseHandler()(form, error);
            }
        });
    }

    useEffect(() => {
        if (meFetched) {
            form.setFieldValue('currency', String(account?.currency_code));
        }
        if (accountFetched) {
            form.setFieldValue('name', String(account?.name));
            form.setFieldValue('email', String(me?.email));
            form.setFieldValue('timezone', String(me?.timezone));
        }
    }, [accountFetched, meFetched]);

    return (
        <LoadingContainer>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerMutation.isPending || !accountFetched || !meFetched}>
                    <OrganizerForm form={form as any}/>

                    <Button
                        type={'submit'}
                        fullWidth
                        size="lg"
                        loading={organizerMutation.isPending}
                        leftSection={organizerMutation.isPending ? null : <IconBuilding size={20}/>}
                        className={classes.primaryButton}
                        disabled={organizerMutation.isPending}
                        style={{marginTop: '1.5rem'}}
                        aria-label={organizerMutation.isPending ? t`Creating your organizer profile, please wait` : t`Continue to event creation`}
                    >
                        {organizerMutation.isPending ? t`Creating Organizer...` : t`Continue Setup`}
                    </Button>
                </fieldset>
            </form>
        </LoadingContainer>
    );
}
