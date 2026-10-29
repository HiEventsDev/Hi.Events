import {useState} from "react";
import {Alert, Anchor, Button, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {AxiosError} from "axios";
import {BoxOfficePublic, BoxOfficeSession, EventType} from "../../../../../types.ts";
import {useCreateBoxOfficeSession} from "../../../mutations/useCreateBoxOfficeSession.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {getRememberedOperatorName, getRememberedReaderId, rememberReaderId} from "../../../utilites/boxOfficeSession.ts";
import {OccurrenceSelect} from "../../../../../components/common/OccurrenceSelect";
import {isToday} from "../../../../../components/common/OccurrenceSelect/occurrenceSelectUtils.ts";
import {isHiEvents, isSsr} from "../../../../../utilites/helpers.ts";
import {useGetMe} from "../../../../../queries/useGetMe.ts";
import {PoweredByFooter} from "../../../../../components/common/PoweredByFooter";
import {NO_READER, ReaderPicker} from "./ReaderPicker.tsx";
import classes from "./StartSession.module.scss";

interface StartSessionProps {
    boxOffice: BoxOfficePublic;
    boxOfficeShortId: string;
    sessionExpired: boolean;
    previousSession?: BoxOfficeSession | null;
    onStarted: (session: BoxOfficeSession) => void;
}

interface StartSessionForm {
    operator_name: string;
    pin: string;
    event_occurrence_id: string | null;
    stripe_terminal_reader_id: string;
}

export const StartSession = ({boxOffice, boxOfficeShortId, sessionExpired, previousSession, onStarted}: StartSessionProps) => {
    const errorHandler = useFormErrorResponseHandler();
    const createSession = useCreateBoxOfficeSession();
    const {data: me} = useGetMe();
    const signedInName = me ? [me.first_name, me.last_name].filter(Boolean).join(' ') : null;
    const [generalError, setGeneralError] = useState<string | null>(null);
    const [usePin, setUsePin] = useState(false);
    const signInWithAccount = boxOffice.can_skip_pin && !!signedInName && !usePin;
    const namesThemselves = usePin || !signedInName;
    const needsOccurrence = boxOffice.event.type === EventType.RECURRING && boxOffice.occurrences.length > 0;
    const previousOccurrence = previousSession?.event_occurrence
        && boxOffice.occurrences.find(o => String(o.id) === String(previousSession.event_occurrence?.id));
    const defaultOccurrence = previousOccurrence || boxOffice.occurrences.find(o => isToday(o, boxOffice.event.timezone));
    const readers = boxOffice.readers;
    const rememberedReaderId = previousSession
        ? (previousSession.reader ? String(previousSession.reader.id) : null)
        : isSsr() ? null : getRememberedReaderId(boxOfficeShortId);
    const defaultReader = readers.find(r => String(r.id) === rememberedReaderId && r.status === 'online')
        ?? readers.find(r => r.status === 'online');

    const form = useForm<StartSessionForm>({
        initialValues: {
            operator_name: previousSession?.operator_name ?? (isSsr() ? '' : getRememberedOperatorName()),
            pin: '',
            event_occurrence_id: defaultOccurrence ? String(defaultOccurrence.id) : null,
            stripe_terminal_reader_id: defaultReader ? String(defaultReader.id) : NO_READER,
        },
        validate: {
            event_occurrence_id: (value) => needsOccurrence && !value ? t`Choose a date to sell tickets for` : null,
        },
    });

    const handleSubmit = (values: StartSessionForm) => {
        setGeneralError(null);
        createSession.mutate({
            boxOfficeShortId,
            payload: {
                operator_name: namesThemselves ? values.operator_name : signedInName!,
                pin: signInWithAccount ? null : values.pin,
                event_occurrence_id: values.event_occurrence_id ? Number(values.event_occurrence_id) : null,
                stripe_terminal_reader_id: values.stripe_terminal_reader_id === NO_READER ? null : Number(values.stripe_terminal_reader_id),
            },
        }, {
            onSuccess: ({data}) => {
                rememberReaderId(boxOfficeShortId, values.stripe_terminal_reader_id === NO_READER ? null : values.stripe_terminal_reader_id);
                onStarted(data);
            },
            onError: (error) => {
                if (error instanceof AxiosError && error.response?.status === 401) {
                    form.setFieldError('pin', t`Incorrect PIN`);
                    return;
                }
                if (error instanceof AxiosError && error.response?.status === 429) {
                    setGeneralError(error.response.data?.message ?? t`Too many attempts. Please wait a minute and try again.`);
                    return;
                }
                if (error instanceof AxiosError && error.response?.status === 403) {
                    setGeneralError(error.response.data?.message ?? t`This box office is not available right now.`);
                    return;
                }
                if (error instanceof AxiosError && error.response?.status === 409) {
                    setGeneralError(error.response.data?.message ?? t`This box office is not accepting sales right now.`);
                    return;
                }
                errorHandler(form, error);
            },
        });
    };

    const content = (
        <form className={previousSession ? classes.embedded : classes.card} onSubmit={form.onSubmit(handleSubmit)}>
            {!previousSession && (
                <div>
                    <div className={classes.eyebrow}>{t`Box office`}</div>
                    <h1 className={classes.title}>{boxOffice.name}</h1>
                    <p className={classes.eventTitle}>{boxOffice.event.title}</p>
                </div>
            )}

            {!previousSession && boxOffice.description && (
                <div className={classes.description}>{boxOffice.description}</div>
            )}

            {sessionExpired && (
                <Alert color="yellow" variant="light">{t`Your session expired. Sign in again to keep selling.`}</Alert>
            )}

            {generalError && (
                <Alert color="red" variant="light">{generalError}</Alert>
            )}

            {!boxOffice.has_pin && !signInWithAccount && (
                <Alert color="orange" variant="light">
                    {boxOffice.can_skip_pin
                        ? t`This box office has no PIN yet. Set one from the Box Office page before handing this device to staff.`
                        : t`This box office has no PIN yet. Ask the organizer to set one from the Box Office page.`}
                </Alert>
            )}

            {!namesThemselves ? (
                !signInWithAccount && <Text size="sm" c="dimmed">{t`Selling as ${signedInName}`}</Text>
            ) : (
                <TextInput
                    {...form.getInputProps('operator_name')}
                    label={t`Your name`}
                    description={t`Shown on the orders you sell`}
                    placeholder={t`e.g. Sam`}
                    autoComplete="name"
                    size="md"
                    required
                    data-testid="box-office-operator-name"
                />
            )}

            {!signInWithAccount && (
                <TextInput
                    {...form.getInputProps('pin')}
                    label={t`PIN`}
                    type="password"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={8}
                    size="md"
                    required
                    data-testid="box-office-pin"
                />
            )}

            {needsOccurrence && (
                <OccurrenceSelect
                    occurrences={boxOffice.occurrences}
                    timezone={boxOffice.event.timezone}
                    value={form.values.event_occurrence_id}
                    onChange={(value) => form.setFieldValue('event_occurrence_id', value)}
                    error={form.errors.event_occurrence_id}
                    label={t`Date`}
                    description={t`Which date are you selling for?`}
                    placeholder={t`Choose a date`}
                    size="md"
                />
            )}

            {boxOffice.card_payments_enabled && readers.length > 0 && (
                <ReaderPicker
                    readers={readers}
                    value={form.values.stripe_terminal_reader_id}
                    onChange={(value) => form.setFieldValue('stripe_terminal_reader_id', value)}
                    label={t`Card reader`}
                    description={t`Pick the reader next to you, or sell cash only`}
                />
            )}

            {readers.length > 0 && readers.every(r => r.status !== 'online') && (
                <Text size="xs" c="dimmed">{t`No reader is online. Check the reader's power and Wi-Fi, then reload this page.`}</Text>
            )}

            <Button
                type="submit"
                size="lg"
                fullWidth
                loading={createSession.isPending}
                disabled={!boxOffice.has_pin && !signInWithAccount}
                data-testid="box-office-start-button"
            >
                {signInWithAccount ? t`Start selling as ${signedInName}` : t`Start selling`}
            </Button>

            {signInWithAccount && (
                <div className={classes.pinFallback}>
                    {t`Not selling as ${signedInName}?`}
                    {' '}
                    <Anchor
                        component="button"
                        type="button"
                        className={classes.pinFallbackLink}
                        data-testid="box-office-use-pin-button"
                        onClick={() => setUsePin(true)}
                    >
                        {t`Sign in with a PIN`}
                    </Anchor>
                </div>
            )}
        </form>
    );

    return previousSession ? content : (
        <div className={classes.wrap}>
            <div className={classes.column}>
                {content}
                {!isHiEvents() && <PoweredByFooter/>}
            </div>
        </div>
    );
};
