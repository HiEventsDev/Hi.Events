import {useRef, useState} from "react";
import {t} from "@lingui/macro";
import {Badge, Button, Checkbox, Group, PasswordInput, Select, Stack, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {Card} from "../../../../components/common/Card";
import {HeadingWithDescription} from "../../../../components/common/Card/CardHeading";
import {PhoneNumberInput} from "../../../../components/common/PhoneNumberInput";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {IdParam} from "../../../../types.ts";
import {firstApiError} from "../../../../utilites/apiErrors.ts";
import {getConfig} from "../../../../utilites/config.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useCrmAccess} from "../../hooks/useCrmAccess.ts";
import {useTextMessagesVisible} from "../../hooks/useTextMessagesVisible.ts";
import {
    useDeleteCrmSmsConnection,
    useListCrmSmsSenders,
    useSaveCrmSmsConnection,
    useSendCrmSmsTest,
} from "../../mutations/useCrmSmsConnectionMutations.ts";
import {useGetCrmSmsConnection} from "../../queries/useGetCrmSmsConnection.ts";
import {CrmSmsConnection, CrmSmsCountry, CrmSmsSender} from "../../types.ts";
import {SMS_COUNTRIES, smsCountryLabel, smsCountryRequirement} from "../../utilites/smsCountries.ts";
import classes from "./SmsSettings.module.scss";
import {Notice} from "../Notice";

interface ConnectionFormValues {
    account_sid: string;
    auth_token: string;
    messaging_service_sid: string;
    enabled_countries: CrmSmsCountry[];
}

const ConnectionForm = ({organizerId, connection, onSaved, onCancel}: {
    organizerId: IdParam,
    connection: CrmSmsConnection | null,
    onSaved: () => void,
    onCancel: () => void,
}) => {
    const listSenders = useListCrmSmsSenders();
    const save = useSaveCrmSmsConnection();
    const errorHandler = useFormErrorResponseHandler();
    const [senders, setSenders] = useState<CrmSmsSender[] | null>(null);
    const form = useForm<ConnectionFormValues>({
        initialValues: {
            account_sid: connection?.account_sid ?? '',
            auth_token: '',
            messaging_service_sid: connection?.messaging_service_sid ?? '',
            enabled_countries: connection?.enabled_countries ?? [],
        },
    });
    const keepsSavedToken = !!connection && form.values.account_sid === connection.account_sid;
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");

    const findSenders = () => listSenders.mutate({
        organizerId,
        accountSid: form.values.account_sid.trim(),
        authToken: form.values.auth_token.trim() || null,
    }, {
        onSuccess: ({data}) => {
            setSenders(data);
            if (data.length === 1) {
                form.setFieldValue('messaging_service_sid', data[0].sid);
            } else if (!data.some(sender => sender.sid === form.values.messaging_service_sid)) {
                form.setFieldValue('messaging_service_sid', '');
            }
        },
        onError: (error) => errorHandler(form, error),
    });

    const submit = (values: ConnectionFormValues) => save.mutate({
        organizerId,
        connection: {
            account_sid: values.account_sid.trim(),
            auth_token: values.auth_token.trim() || null,
            messaging_service_sid: values.messaging_service_sid,
            enabled_countries: values.enabled_countries,
        },
    }, {
        onSuccess: () => {
            showSuccess(t`Text messages are set up`);
            onSaved();
        },
        onError: (error) => errorHandler(form, error),
    });

    const savedSender = connection && keepsSavedToken
        ? [{sid: connection.messaging_service_sid, name: connection.messaging_service_sid}]
        : [];
    const senderOptions = (senders ?? savedSender)
        .map(sender => ({value: sender.sid, label: sender.name === sender.sid ? sender.sid : `${sender.name} (${sender.sid})`}));

    return (
        <form onSubmit={form.onSubmit(submit)}>
            <TextInput
                label={t`Twilio Account SID`}
                description={t`Your Account SID and Auth Token are on the dashboard of your Twilio Console.`}
                placeholder="AC…"
                required
                {...form.getInputProps('account_sid')}
            />
            <PasswordInput
                label={t`Auth Token`}
                description={keepsSavedToken ? t`Leave blank to keep the saved token.` : undefined}
                required={!keepsSavedToken}
                {...form.getInputProps('auth_token')}
            />
            <Button
                variant="default"
                mb="md"
                loading={listSenders.isPending}
                disabled={form.values.account_sid.trim() === '' || (!keepsSavedToken && form.values.auth_token.trim() === '')}
                onClick={findSenders}
            >
                {t`Find Messaging Services`}
            </Button>

            {senderOptions.length > 0 && (
                <Select
                    label={t`Messaging Service`}
                    description={t`Texts are sent from the numbers and sender names in this service. Choose one that only ${appName} uses.`}
                    data={senderOptions}
                    required
                    {...form.getInputProps('messaging_service_sid')}
                />
            )}
            {senders !== null && senders.length === 0 && (
                <Text size="sm" c="dimmed" mb="md">
                    {t`This Twilio account has no Messaging Services. Create one in Twilio, add your sender, then try again.`}
                </Text>
            )}

            <Checkbox.Group
                label={t`Countries you send to`}
                description={t`People with numbers from other countries are skipped.`}
                {...form.getInputProps('enabled_countries')}
            >
                <Stack gap="xs" mt="xs" mb="md">
                    {SMS_COUNTRIES.map(country => (
                        <Checkbox key={country} value={country} label={smsCountryLabel(country)} description={smsCountryRequirement(country)}/>
                    ))}
                </Stack>
            </Checkbox.Group>

            {form.values.messaging_service_sid !== '' && (
                <Notice title={t`Saving changes this Messaging Service at Twilio`}>
                    {t`Its incoming-message webhook is replaced with ${appName}'s, so replies like STOP are recorded here. Anything else that handles replies to this Messaging Service stops receiving them, and disconnecting later doesn't put the old webhook back.`}
                </Notice>
            )}

            <Group gap="sm">
                <Button type="submit" loading={save.isPending} disabled={form.values.messaging_service_sid === ''}>
                    {connection ? t`Save changes` : t`Connect Twilio`}
                </Button>
                {connection && <Button variant="default" onClick={onCancel}>{t`Cancel`}</Button>}
            </Group>
            {form.values.messaging_service_sid === '' && (
                <Text size="xs" c="dimmed" mt="xs">
                    {t`Find your Messaging Services and choose one before you connect.`}
                </Text>
            )}
        </form>
    );
};

const ConnectedSummary = ({organizerId, connection, canChange, canRemove, onEdit}: {
    organizerId: IdParam,
    connection: CrmSmsConnection,
    canChange: boolean,
    canRemove: boolean,
    onEdit: () => void,
}) => {
    const sendTest = useSendCrmSmsTest();
    const disconnect = useDeleteCrmSmsConnection();
    const appName = getConfig("VITE_APP_NAME", "Hi.Events");
    const [phone, setPhone] = useState('');
    const [testError, setTestError] = useState<string | null>(null);

    const handleTest = () => {
        setTestError(null);
        sendTest.mutate({organizerId, phone}, {
            onSuccess: () => showSuccess(t`Test text sent`),
            onError: error => setTestError(firstApiError(error, t`We couldn't send the test text. Please try again.`)),
        });
    };

    const handleDisconnect = () => confirmationDialog(
        t`Disconnect Twilio?`,
        () => disconnect.mutate({organizerId}, {
            onSuccess: () => showSuccess(t`Twilio disconnected`),
            onError: () => showError(t`We couldn't disconnect Twilio. Please try again.`),
        }),
        {
            confirm: t`Disconnect`,
            destructive: true,
            description: t`Text message campaigns can't be sent until you connect again. Your contacts and their text message consent are kept. Nothing changes at Twilio: the Messaging Service keeps sending its incoming messages to ${appName}, so set its webhook in Twilio yourself if you use it for something else. Replies sent while disconnected, including STOP, aren't recorded here, though Twilio still blocks texts to anyone who replied STOP.`,
        },
    );

    return (
        <Stack gap="md">
            <div className={classes.summary}>
                <div>
                    <Text size="xs" c="dimmed">{t`Twilio account`}</Text>
                    <Text className={classes.sid}>{connection.account_sid}</Text>
                </div>
                <div>
                    <Text size="xs" c="dimmed">{t`Messaging Service`}</Text>
                    <Text className={classes.sid}>{connection.messaging_service_sid}</Text>
                </div>
                <div>
                    <Text size="xs" c="dimmed">{t`Countries`}</Text>
                    <Group gap={6} mt={2}>
                        {connection.enabled_countries.map(country => (
                            <Badge key={country} variant="light" size="sm" radius="sm" tt="none">{smsCountryLabel(country)}</Badge>
                        ))}
                    </Group>
                </div>
            </div>

            {canChange && (
                <div className={classes.testRow}>
                    <PhoneNumberInput
                        label={t`Send a test text`}
                        description={t`Check a text reaches a phone in each country you send to.`}
                        defaultCountry={connection.enabled_countries[0]}
                        value={phone}
                        onChange={setPhone}
                        error={testError}
                    />
                    <Button variant="default" onClick={handleTest} loading={sendTest.isPending} disabled={phone === ''}>
                        {t`Send test`}
                    </Button>
                </div>
            )}
            {canRemove && (
                <Group gap="sm">
                    {canChange && <Button variant="default" onClick={onEdit}>{t`Edit`}</Button>}
                    <Button variant="subtle" color="red" onClick={handleDisconnect} loading={disconnect.isPending}>
                        {t`Disconnect`}
                    </Button>
                </Group>
            )}
        </Stack>
    );
};

export const SmsSettings = ({organizerId}: { organizerId: IdParam }) => {
    const access = useCrmAccess();
    const isAvailable = useTextMessagesVisible();
    const canChange = access.canChange && isAvailable;
    const canRemove = access.canRemove;
    const connectionQuery = useGetCrmSmsConnection(organizerId);
    const connection = connectionQuery.data ?? null;
    const [isEditing, setIsEditing] = useState(false);
    const card = useRef<HTMLDivElement>(null);

    const showSummary = () => {
        setIsEditing(false);
        requestAnimationFrame(() => card.current?.scrollIntoView({block: 'start'}));
    };

    return (
        <Card ref={card} className={classes.card}>
            <HeadingWithDescription
                heading={t`Text Messages`}
                description={t`Send text message campaigns through your own Twilio account. Twilio bills you for each message.`}
            />
            {!isAvailable && (
                <Notice title={t`Text messages aren't available on this account`}>
                    {t`Text message campaigns can't be sent, but your Twilio account is still connected. You can disconnect it here.`}
                </Notice>
            )}
            {connectionQuery.isLoading && <Text size="sm" c="dimmed">{t`Loading…`}</Text>}
            {connectionQuery.isError && (
                <Text size="sm" c="red">{t`We couldn't load your text message setup. Please refresh the page.`}</Text>
            )}
            {connectionQuery.isSuccess && (connection && !isEditing
                ? <ConnectedSummary organizerId={organizerId} connection={connection} canChange={canChange} canRemove={canRemove} onEdit={() => setIsEditing(true)}/>
                : canChange
                    ? <ConnectionForm organizerId={organizerId} connection={connection} onSaved={showSummary} onCancel={() => setIsEditing(false)}/>
                    : <Text size="sm" c="dimmed">{t`Text messages aren't set up yet.`}</Text>)}
        </Card>
    );
};
