import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {Button, Stack, Text, Title} from "@mantine/core";
import {IconMailOff, IconCircleCheck, IconAlertCircle} from "@tabler/icons-react";
import {Card} from "../../../common/Card";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {useUnsubscribeFromLifecycleEmails} from "../../../../mutations/useUnsubscribeFromLifecycleEmails";
import classes from "./Unsubscribe.module.scss";

const Unsubscribe = () => {
    const {token} = useParams();
    const unsubscribeMutation = useUnsubscribeFromLifecycleEmails();

    const content = () => {
        if (unsubscribeMutation.isSuccess) {
            return (
                <>
                    <IconCircleCheck size={40} className={classes.icon}/>
                    <Title order={2}>{t`You're unsubscribed`}</Title>
                    <Text c="dimmed">
                        {t`You won't receive any more of these emails. Emails about your orders and account will still be sent.`}
                    </Text>
                </>
            );
        }

        if (unsubscribeMutation.isError) {
            return (
                <>
                    <IconAlertCircle size={40} className={classes.icon}/>
                    <Title order={2}>{t`This link isn't valid`}</Title>
                    <Text c="dimmed">
                        {t`The unsubscribe link may be incomplete. Try opening it again from the email.`}
                    </Text>
                </>
            );
        }

        return (
            <>
                <IconMailOff size={40} className={classes.icon}/>
                <Title order={2}>{t`Unsubscribe from tips and reminders`}</Title>
                <Text c="dimmed">
                    {t`Stop receiving emails that help you set up and run your events. Emails about your orders and account will still be sent.`}
                </Text>
                <Button
                    size="md"
                    onClick={() => token && unsubscribeMutation.mutate(token)}
                    loading={unsubscribeMutation.isPending}
                    data-testid="lifecycle-email-unsubscribe-button"
                >
                    {t`Unsubscribe`}
                </Button>
            </>
        );
    };

    return (
        <div className={classes.container}>
            <Card className={classes.card}>
                <Stack align="center" gap="md">
                    {content()}
                </Stack>
            </Card>
            <PoweredByFooter/>
        </div>
    );
};

export default Unsubscribe;
