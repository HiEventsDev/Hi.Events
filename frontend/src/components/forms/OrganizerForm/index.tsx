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
import {trackEvent, AnalyticsEvents} from "../../../utilites/analytics.ts";

export const organizerCurrencyOptions = Object.entries(currencies).map(([key, value]) => ({
    value: value,
    label: `${key} (${value})`,
}));

interface OrganizerFormProps {
    onSuccess?: (organizer: Organizer) => void;
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
                    data={organizerCurrencyOptions}
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

export const useCreateOrganizerForm = ({onSuccess, prefillName}: {
    onSuccess?: (organizer: Organizer) => void,
    prefillName: boolean,
}) => {
    const organizerMutation = useCreateOrganizer();
    const formErrorHandler = useFormErrorResponseHandler();
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
                trackEvent(AnalyticsEvents.ORGANIZER_CREATED);
                onSuccess?.(organizer);
            },
            onError: (error: any) => formErrorHandler(form, error),
        });
    }

    useEffect(() => {
        if (!accountFetched) {
            return;
        }
        form.setFieldValue('currency', account?.currency_code ?? '');
        if (prefillName) {
            form.setFieldValue('name', account?.name ?? '');
        }
    }, [accountFetched]);

    useEffect(() => {
        if (!meFetched) {
            return;
        }
        form.setFieldValue('email', me?.email ?? '');
        form.setFieldValue('timezone', me?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone);
    }, [meFetched]);

    return {
        form,
        handleSubmit,
        isPending: organizerMutation.isPending,
        isReady: accountFetched && meFetched,
    };
}

export const OrganizerCreateForm = ({onSuccess}: OrganizerFormProps) => {
    const {form, handleSubmit, isPending, isReady} = useCreateOrganizerForm({onSuccess, prefillName: true});

    return (
        <LoadingContainer>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={isPending || !isReady}>
                    <OrganizerForm form={form as any}/>

                    <Button
                        type={'submit'}
                        fullWidth
                        size="lg"
                        loading={isPending}
                        leftSection={isPending ? null : <IconBuilding size={20}/>}
                        className={classes.primaryButton}
                        disabled={isPending}
                        style={{marginTop: '1.5rem'}}
                        aria-label={isPending ? t`Creating your organizer profile, please wait` : t`Continue to event creation`}
                    >
                        {isPending ? t`Creating Organizer...` : t`Continue Setup`}
                    </Button>
                </fieldset>
            </form>
        </LoadingContainer>
    );
}
