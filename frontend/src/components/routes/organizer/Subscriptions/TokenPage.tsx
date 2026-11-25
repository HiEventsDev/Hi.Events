import {useParams} from "react-router";
import {Button, Stack, Text, Title} from "@mantine/core";
import {t} from "@lingui/macro";
import {Card} from "../../../common/Card";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {useOrganizerSubscriptionToken} from "../../../../mutations/useOrganizerSubscription";
import classes from "./Subscriptions.module.scss";

export default function SubscriptionTokenPage() {
    const {organizerId, token, action} = useParams();
    const confirm = action === 'confirm';
    const mutation = useOrganizerSubscriptionToken(organizerId, token || '', confirm);
    const valid = (confirm || action === 'unsubscribe') && !!token;

    return <div className={classes.tokenPage}>
        <Card><Stack align="center" gap="lg">
            <Title order={1}>{confirm ? t`Confirm event subscription` : t`Unsubscribe from this organizer`}</Title>
            {mutation.isSuccess ? <Text role="status">{confirm
                ? t`Subscription confirmed. You will receive emails about this organizer’s new events.`
                : t`You are unsubscribed from this organizer’s new events.`}</Text>
                : <>
                    <Text>{confirm ? t`Confirm to start receiving new-event emails from this organizer.` : t`Stop receiving new-event emails from this organizer. Your ticket and order emails will continue.`}</Text>
                    {(!valid || mutation.isError) && <Text c="red" role="alert">{confirm
                        ? t`This link is invalid, expired, or subscriptions are paused. Please subscribe again from the organizer page.`
                        : t`This unsubscribe link is invalid. Open the link from your email and try again.`}</Text>}
                    <Button disabled={!valid} loading={mutation.isPending} onClick={() => mutation.mutate()}>
                        {confirm ? t`Confirm subscription` : t`Unsubscribe`}
                    </Button>
                </>}
        </Stack></Card>
        <PoweredByFooter/>
    </div>;
}
