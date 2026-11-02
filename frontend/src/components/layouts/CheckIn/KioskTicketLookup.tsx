import {useEffect, useState} from "react";
import {Button, TextInput} from "@mantine/core";
import {isEmail, isNotEmpty, useForm} from "@mantine/form";
import {t} from "@lingui/macro";
import {AxiosError} from "axios";
import {IdParam, KioskTicket} from "../../../types.ts";
import {useFindKioskTickets} from "../../../mutations/useFindKioskTickets.ts";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import classes from "./KioskCheckIn.module.scss";

const GIVE_UP_AFTER_MS = 60000;

interface KioskTicketLookupProps {
    checkInListShortId: IdParam;
    onTicketChosen: (attendeePublicId: string) => void;
    onCancel: () => void;
}

export const KioskTicketLookup = ({checkInListShortId, onTicketChosen, onCancel}: KioskTicketLookupProps) => {
    const form = useForm({
        initialValues: {email: '', last_name: ''},
        validate: {
            email: isEmail(t`Please enter a valid email address`),
            last_name: isNotEmpty(t`Last name is required`),
        },
    });
    const findTickets = useFindKioskTickets();
    const handleFormError = useFormErrorResponseHandler();
    const [matches, setMatches] = useState<KioskTicket[] | null>(null);
    const [problem, setProblem] = useState<string | null>(null);
    const {email, last_name: lastName} = form.values;

    useEffect(() => {
        const timer = setTimeout(onCancel, GIVE_UP_AFTER_MS);
        return () => clearTimeout(timer);
    }, [email, lastName, matches, onCancel]);

    const handleSubmit = (values: typeof form.values) => {
        setMatches(null);
        setProblem(null);
        findTickets.mutate({checkInListShortId, email: values.email, lastName: values.last_name}, {
            onSuccess: ({data}) => {
                if (data.length === 1) {
                    onTicketChosen(data[0].public_id);
                    return;
                }
                setMatches(data);
            },
            onError: (error) => {
                const status = error instanceof AxiosError ? error.response?.status : undefined;
                if (status === 422) {
                    handleFormError(form, error);
                    return;
                }
                setProblem(status === 429
                    ? t`Too many attempts. Please wait a minute or see the registration desk.`
                    : t`Something went wrong. Please see the registration desk.`);
            },
        });
    };

    if (matches && matches.length > 1) {
        return (
            <div className={classes.lookup}>
                <h1 className={classes.idleTitle}>{t`Which ticket is yours?`}</h1>
                <div className={classes.lookupMatches}>
                    {matches.map((ticket) => (
                        <Button
                            key={ticket.public_id}
                            size="xl"
                            radius="xl"
                            variant="default"
                            fullWidth
                            onClick={() => onTicketChosen(ticket.public_id)}
                            data-testid="kiosk-lookup-match"
                        >
                            <span>{ticket.first_name}</span>
                            {ticket.product_title && <span className={classes.matchTicket}>{ticket.product_title}</span>}
                        </Button>
                    ))}
                </div>
                <Button variant="subtle" color="gray" size="lg" mt="lg" onClick={onCancel}>{t`Back`}</Button>
            </div>
        );
    }

    return (
        <form className={classes.lookup} onSubmit={form.onSubmit(handleSubmit)} noValidate>
            <h1 className={classes.idleTitle}>{t`Find your ticket`}</h1>
            <p className={classes.idleText}>{t`Enter the email address and last name on your ticket.`}</p>
            <div className={classes.lookupFields}>
                <TextInput
                    size="lg"
                    type="email"
                    autoComplete="off"
                    autoCapitalize="none"
                    label={t`Email address`}
                    {...form.getInputProps('email')}
                />
                <TextInput
                    size="lg"
                    autoComplete="off"
                    label={t`Last name`}
                    {...form.getInputProps('last_name')}
                />
            </div>
            {matches?.length === 0 && (
                <p className={classes.lookupProblem}>
                    {t`We couldn't find a ticket with those details. Check the spelling, or see the registration desk.`}
                </p>
            )}
            {problem && <p className={classes.lookupProblem}>{problem}</p>}
            <div className={classes.actions}>
                <Button
                    type="submit"
                    size="xl"
                    radius="xl"
                    color="teal"
                    className={classes.nextButton}
                    loading={findTickets.isPending}
                    data-testid="kiosk-lookup-submit"
                >
                    {t`Find my ticket`}
                </Button>
                <Button variant="subtle" color="gray" size="lg" onClick={onCancel}>{t`Back`}</Button>
            </div>
        </form>
    );
};
