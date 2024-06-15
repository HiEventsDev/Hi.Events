import {PageBody} from "../../../common/PageBody";
import {Card} from "../../../common/Card";
import {t} from "@lingui/macro"
import {Button} from "@mantine/core";
import classes from "./GettingStarted.module.scss";
import {useParams} from "react-router-dom";
import {IconCheck} from "@tabler/icons-react";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {Tooltip} from "../../../common/Tooltip";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useUpdateEventStatus} from "../../../../mutations/useUpdateEventStatus.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";

const GettingStarted = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;
    const tickets = event?.tickets;
    const hasTickets = tickets && tickets.length > 0;
    const eventImagesQuery = useGetEventImages(eventId);
    const eventImages = eventImagesQuery.data;
    const hasImages = eventImages && eventImages.length > 0;
    const accountQuery = useGetAccount();
    const account = accountQuery.data;
    const statusToggleMutation = useUpdateEventStatus();

    const handleStatusToggle = () => {
        statusToggleMutation.mutate({
            eventId,
            status: event?.status === 'LIVE' ? 'DRAFT' : 'LIVE'
        }, {
            onSuccess: () => {
                showSuccess(t`Event status updated`);
            },
            onError: (error: any) => {
                showError(error?.response?.data?.message || t`Event status update failed. Please try again later`);
            }
        });
    }
    const Check = () => {
        return (
            <Tooltip label={t`Completed`}>
                <Button ml={10} variant={'light'} color={'green'}>
                    <IconCheck/>
                </Button>
            </Tooltip>
        );
    }

    return (
        <>
            <PageBody>
                <Card className={classes.headerCard}>
                    <h2>
                        {t`🎉 Congratulations on creating an event!`}
                    </h2>
                    <p>
                        {t`Before your event can go live, there are a few things you need to do.`}
                    </p>
                </Card>

                <div className={classes.actionItems}>
                    <Card>
                        <h2>
                            {t`🎟️ Add tickets`}
                        </h2>
                        <p>
                            {t`Create tickets for your event, set prices, and manage available quantity.`}
                        </p>

                        <Button variant={'light'} component={'a'} href={'/manage/event/' + eventId + '/tickets#create-ticket'}>
                            {hasTickets ? t`Add More tickets` : t`Add tickets`}
                        </Button>

                        {hasTickets && <Check/>}
                    </Card>
                    <Card>
                        <h2>
                            {t`⚡️ Set up your event`}
                        </h2>
                        <p>
                            {t`Add event details and and manage event settings.`}
                        </p>
                        <Button variant={'light'} component={'a'} href={'/manage/event/' + eventId + '/settings'}>
                            {event?.description ? t`Continue set up` : t`Set up your event`}
                        </Button>
                        {event?.description && <Check/>}
                    </Card>
                    <Card>
                        <h2>
                            {t`🎨 Customize your event page`}
                        </h2>
                        <p>
                            {t`Customize your event page to match your brand and style.`}
                        </p>
                        <Button component={'a'} variant={'light'}
                                href={'/manage/event/' + eventId + '/homepage-designer'}>
                            {t`Customize your event page`}
                        </Button>
                        {hasImages && <Check/>}
                    </Card>
                    <Card>
                        <h2>
                            {t`🚀 Set your event live`}
                        </h2>
                        <p>
                            {t`Once you're ready, set your event live and start selling tickets.`}
                        </p>
                        {event?.status !== 'LIVE' &&
                            (<Button variant={'light'} onClick={handleStatusToggle}>
                                    {t`Set your event live`}
                                </Button>
                            )}
                        {event?.status === 'LIVE' && <Check/>}
                    </Card>
                    <Card>
                        <h2>
                            {t`💳 Connect with Stripe`}
                        </h2>
                        <p>
                            {t`Connect your Stripe account to start receiving payments.`}
                        </p>
                        {!account?.stripe_connect_setup_complete && (
                            <Button variant={'light'} component={'a'} href={'/account/payment'}>
                                {t`Connect with Stripe`}
                            </Button>)
                        }
                        {account?.stripe_connect_setup_complete && <Check/>}
                    </Card>
                    <Card>
                        <h2>
                            {t`✉️ Confirm your email address`}
                        </h2>
                        <p>
                            {t`You must confirm your email address before your event can go live.`}
                        </p>
                        {!account?.is_account_email_confirmed && (
                            <Button variant={'light'} component={'a'} href={'/account/payment'}>
                                {t`Resend confirmation email`}
                            </Button>)}
                        {account?.is_account_email_confirmed && <Check/>}
                    </Card>
                </div>
            </PageBody>
        </>
    );
}

export default GettingStarted;