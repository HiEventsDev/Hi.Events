import {useCreateOrganizer} from "../../../mutations/useCreateOrganizer.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {useForm} from "@mantine/form";
import {Organizer} from "../../../types.ts";
import {useEffect} from "react";
import {LoadingContainer} from "../../common/LoadingContainer";
import {t} from "@lingui/macro";
import {InputGroup} from "../../common/InputGroup";
import {Button, Group, Select, TextInput} from "@mantine/core";
import {currencies} from "../../../../data/currencies.ts";
import {timezones} from "../../../../data/timezones.ts";

interface OrganizerFormProps {
    onSuccess?: (organizer: Organizer) => void;
    onCancel?: () => void;
}

export const OrganizerForm = ({onSuccess}: OrganizerFormProps) => {
    const organizerMutation = useCreateOrganizer();
    const {data: account, isFetched: accountFetched} = useGetAccount();
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
            }
        });
    }

    useEffect(() => {
        if (accountFetched) {
            form.setFieldValue('email', String(account?.email));
            form.setFieldValue('currency', String(account?.currency_code));
            form.setFieldValue('timezone', String(account?.timezone));
        }
    }, [accountFetched]);

    return (
        <LoadingContainer>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <fieldset disabled={organizerMutation.isLoading || !accountFetched}>
                    <InputGroup>
                        <TextInput
                            {...form.getInputProps('name')}
                            required
                            label={t`Organizer Name`}
                            placeholder={t`Awesome Organizer Ltd.`}
                        />
                        <TextInput
                            {...form.getInputProps('email')}
                            label={t`Email`}
                            placeholder={t`hello@awesome-events.com`}
                        />
                    </InputGroup>
                    <InputGroup>
                        <Select
                            {...form.getInputProps('currency')}
                            searchable
                            required
                            data={Object.entries(currencies).map(([key, value]) => ({
                                value: value,
                                label: key,
                            }))}
                            label={t`Currency`}
                            placeholder={t`EUR`}
                            description={t`The default currency for your events.`}
                        />
                        <Select
                            {...form.getInputProps('timezone')}
                            searchable
                            required
                            data={timezones}
                            label={t`Timezone`}
                            placeholder={t`UTC`}
                            description={t`The default timezone for your events.`}
                        />
                    </InputGroup>

                    <Group gap={10}>
                        <Button fullWidth loading={organizerMutation.isLoading}
                                type={'submit'}
                                color={'green'}>{t`Create Organizer`}
                        </Button>
                    </Group>

                </fieldset>
            </form>
        </LoadingContainer>
    );
}
