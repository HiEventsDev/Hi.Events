import {t} from "@lingui/macro";
import {Button, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {IconAlertCircle, IconTicket} from "@tabler/icons-react";
import {useEffect} from "react";
import {useNavigate, useParams} from "react-router";
import {useFormErrorResponseHandler} from "../../../../hooks/useFormErrorResponseHandler.tsx";
import {useAcceptTicketTransfer} from "../../../../mutations/useAcceptTicketTransfer.ts";
import {useDeclineTicketTransfer} from "../../../../mutations/useDeclineTicketTransfer.ts";
import {useGetTicketTransferClaim} from "../../../../queries/useGetTicketTransferClaim.ts";
import {Product, TicketTransferClaim} from "../../../../types.ts";
import {prettyDate} from "../../../../utilites/dates.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {Card} from "../../../common/Card";
import {CheckoutProductQuestions} from "../../../common/CheckoutQuestion";
import {InputGroup} from "../../../common/InputGroup";
import {LoadingMask} from "../../../common/LoadingMask";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import classes from "./Claim.module.scss";

interface ClaimFormValues {
    first_name: string;
    last_name: string;
    products: { questions: { question_id: number; response: Record<string, unknown> }[] }[];
}

const unavailableMessage = (claim: TicketTransferClaim): { title: string; text: string } => {
    switch (claim.status) {
        case 'ACCEPTED':
            return {
                title: t`This ticket has already been accepted`,
                text: t`Check your inbox for the ticket we emailed to ${claim.recipient_email}.`,
            };
        case 'CANCELLED':
            return {
                title: t`This transfer was cancelled`,
                text: t`The sender cancelled it before it was accepted, so the ticket is still theirs.`,
            };
        case 'DECLINED':
            return {
                title: t`This transfer was declined`,
                text: t`The ticket stays with the person who sent it.`,
            };
        case 'EXPIRED':
            return {
                title: t`This transfer has expired`,
                text: t`It was not accepted in time, so the ticket is still with the sender. Ask them to send it again.`,
            };
        default:
            return {
                title: t`This ticket can no longer be accepted`,
                text: t`The ticket is no longer available to transfer. Contact the person who sent it for details.`,
            };
    }
};

const ClaimLayout = ({icon, title, subtitle, children}: {
    icon: React.ReactNode;
    title: string;
    subtitle: string;
    children?: React.ReactNode;
}) => (
    <CheckoutContent>
        <div className={classes.container}>
            <div className={classes.header}>
                {icon}
                <h1>{title}</h1>
                <p className={classes.subtitle}>{subtitle}</p>
            </div>
            {children}
        </div>
        <PoweredByFooter/>
    </CheckoutContent>
);

const AcceptForm = ({claim, token}: { claim: TicketTransferClaim; token: string }) => {
    const navigate = useNavigate();
    const formErrorHandler = useFormErrorResponseHandler();
    const acceptMutation = useAcceptTicketTransfer();
    const declineMutation = useDeclineTicketTransfer();
    const product = {id: claim.product_id} as Product;

    const form = useForm<ClaimFormValues>({
        initialValues: {first_name: '', last_name: '', products: [{questions: []}]},
        validate: {
            first_name: (value) => value.trim() ? null : t`First name is required`,
            last_name: (value) => value.trim() ? null : t`Last name is required`,
        },
    });

    useEffect(() => {
        form.setFieldValue('products', [{
            questions: claim.questions.map((question) => ({question_id: Number(question.id), response: {}})),
        }]);
    }, [claim.questions]);

    const accept = (values: ClaimFormValues) => {
        acceptMutation.mutate(
            {
                token,
                data: {
                    first_name: values.first_name.trim(),
                    last_name: values.last_name.trim(),
                    questions: values.products[0]?.questions ?? [],
                },
            },
            {
                onSuccess: (ticket) => {
                    showSuccess(t`The ticket is yours. We've also emailed it to you.`);
                    navigate(`/product/${ticket.event_id}/${ticket.attendee_short_id}`);
                },
                onError: (error: any) => {
                    if (error?.response?.status === 409) {
                        showError(error.response.data?.message || t`This ticket can no longer be accepted`);
                        return;
                    }
                    formErrorHandler(form, error);
                },
            },
        );
    };

    const decline = () => {
        if (!window.confirm(t`Decline this ticket? It will stay with the person who sent it.`)) {
            return;
        }

        declineMutation.mutate({token}, {
            onError: (error: any) => showError(error?.response?.data?.message || t`Something went wrong. Please try again.`),
        });
    };

    return (
        <Card className={classes.card}>
            <dl className={classes.summary}>
                <div>
                    <dt>{t`Event`}</dt>
                    <dd>{claim.event_title}</dd>
                </div>
                <div>
                    <dt>{t`Date`}</dt>
                    <dd>{prettyDate(claim.start_date, claim.event_timezone)}</dd>
                </div>
                <div>
                    <dt>{t`Ticket`}</dt>
                    <dd>{claim.ticket_title}</dd>
                </div>
                <div>
                    <dt>{t`From`}</dt>
                    <dd>{claim.sender_email}</dd>
                </div>
            </dl>

            <form onSubmit={form.onSubmit(accept)}>
                <Text size="sm" c="dimmed" className={classes.formIntro}>
                    {t`Tell us who the ticket is for. We'll send it to ${claim.recipient_email}.`}
                </Text>

                <InputGroup>
                    <TextInput {...form.getInputProps('first_name')} label={t`First name`} required/>
                    <TextInput {...form.getInputProps('last_name')} label={t`Last name`} required/>
                </InputGroup>

                {claim.questions.length > 0 && form.values.products[0]?.questions.length === claim.questions.length && (
                    <CheckoutProductQuestions
                        index={0}
                        product={product}
                        form={form}
                        questions={claim.questions}
                    />
                )}

                <Text size="sm" c="dimmed" className={classes.notice}>
                    {t`Accepting makes this ticket yours. The sender's copy stops working straight away. Accept before ${prettyDate(claim.expires_at, claim.event_timezone)}.`}
                </Text>

                <div className={classes.actions}>
                    <Button
                        variant="subtle"
                        color="gray"
                        onClick={decline}
                        loading={declineMutation.isPending}
                        data-testid="ticket-transfer-decline-button"
                    >
                        {t`Decline`}
                    </Button>
                    <Button type="submit" loading={acceptMutation.isPending} data-testid="ticket-transfer-accept-button">
                        {t`Accept ticket`}
                    </Button>
                </div>
            </form>
        </Card>
    );
};

export const TicketTransferClaimPage = () => {
    const {token} = useParams();
    const {data: claim, isLoading, isError} = useGetTicketTransferClaim(token);

    if (isLoading) {
        return <LoadingMask/>;
    }

    if (isError || !claim || !token) {
        return (
            <ClaimLayout
                icon={<IconAlertCircle size={48} className={classes.headerIconMuted}/>}
                title={t`Transfer not found`}
                subtitle={t`This link is not valid. Check the email you were sent, or ask the sender to transfer the ticket again.`}
            />
        );
    }

    if (!claim.can_accept) {
        const message = unavailableMessage(claim);

        return (
            <ClaimLayout
                icon={<IconAlertCircle size={48} className={classes.headerIconMuted}/>}
                title={message.title}
                subtitle={message.text}
            />
        );
    }

    return (
        <ClaimLayout
            icon={<IconTicket size={48} className={classes.headerIcon}/>}
            title={t`A ticket is waiting for you`}
            subtitle={t`${claim.sender_email} wants to give you a ticket for ${claim.event_title}.`}
        >
            <AcceptForm claim={claim} token={token}/>
        </ClaimLayout>
    );
};

export default TicketTransferClaimPage;
