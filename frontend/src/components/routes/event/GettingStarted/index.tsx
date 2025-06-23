import {PageBody} from "../../../common/PageBody";
import {Card} from "../../../common/Card";
import {t} from "@lingui/macro"
import {Button, Group, Progress, Text} from "@mantine/core";
import classes from "./GettingStarted.module.scss";
import {NavLink, useLocation, useNavigate, useParams} from "react-router";
import {IconCheck, IconConfetti} from "@tabler/icons-react";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {Tooltip} from "../../../common/Tooltip";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useUpdateEventStatus} from "../../../../mutations/useUpdateEventStatus.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {getProductsFromEvent} from "../../../../utilites/helpers.ts";
import {useEffect, useState} from 'react';
import ConfettiAnimation from "./ConfettiAnimaiton";
import {Browser, useBrowser} from "../../../../hooks/useGetBrowser.ts";

const GettingStarted = () => {
    const {eventId} = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const [showConfetti, setShowConfetti] = useState(false);
    const browser = useBrowser();

    useEffect(() => {
        const searchParams = new URLSearchParams(location.search);
        const isChromeOrFirefox = browser === Browser.Chrome || browser === Browser.Firefox;

        if (searchParams.get('new_event') === 'true' && isChromeOrFirefox) {
            setShowConfetti(true);

            setTimeout(() => {
                searchParams.delete('new_event');
                navigate({
                    pathname: location.pathname,
                    search: searchParams.toString()
                }, {replace: true});
            }, 2000);
        }
    }, [location, navigate]);

    const eventQuery = useGetEvent(eventId);
    const event = eventQuery.data;
    const products = getProductsFromEvent(event);
    const hasProducts = products && products.length > 0;
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

    const CompletedBadge = () => {
        return (
            <Tooltip label={t`Completed`}>
                <div className={classes.completedBadge}>
                    <IconCheck/>
                </div>
            </Tooltip>
        );
    }

    return (
        <>
            {showConfetti && <ConfettiAnimation duration={2000}/>}
            <PageBody>
                <Card className={classes.headerCard}>
                    <div className={classes.headerContent}>
                        <div className={classes.headerTitle}>
                            <Group gap={12} align="center">
                                <IconConfetti size={35} className={classes.confettiIcon}/>
                                <Text component="h1" className={classes.mainTitle}>
                                    {t`Congratulations on creating an event!`}
                                </Text>
                            </Group>
                            <Text component="p" className={classes.subtitle}>
                                {t`Before your event can go live, there are a few things you need to do. Complete all the steps below to get started.`}
                            </Text>

                            <div className={classes.progressBarContainer}>
                                <Progress
                                    value={[
                                        hasProducts,
                                        event?.description,
                                        account?.stripe_connect_setup_complete,
                                        hasImages,
                                        event?.status === 'LIVE',
                                        account?.is_account_email_confirmed
                                    ].filter(Boolean).length / 6 * 100}
                                    size="md"
                                    radius="xl"
                                    className={classes.progressBar}
                                    color="violet"
                                />
                            </div>
                        </div>
                    </div>
                </Card>

                <div className={classes.actionItems}>
                    <Card className={hasProducts ? classes.completedCard : ''}>
                        {hasProducts && <CompletedBadge/>}
                        <h2>
                            {t`🎟️ Add tickets`}
                        </h2>
                        <p>
                            {t`Create tickets for your event, set prices, and manage available quantity.`}
                        </p>

                        <Button variant={'light'} component={NavLink}
                                to={'/manage/event/' + eventId + '/products#create-product'}>
                            {hasProducts ? t`Add More tickets` : t`Add tickets`}
                        </Button>
                    </Card>

                    <Card className={event?.description ? classes.completedCard : ''}>
                        {event?.description && <CompletedBadge/>}
                        <h2>
                            {t`⚡️ Set up your event`}
                        </h2>
                        <p>
                            {t`Add event details and manage event settings.`}
                        </p>
                        <Button variant={'light'} component={NavLink} to={'/manage/event/' + eventId + '/settings'}>
                            {event?.description ? t`Continue set up` : t`Set up your event`}
                        </Button>
                    </Card>

                    <Card className={account?.stripe_connect_setup_complete ? classes.completedCard : ''}>
                        {account?.stripe_connect_setup_complete && <CompletedBadge/>}
                        <h2>
                            {t`💳 Connect with Stripe`}
                        </h2>
                        <p>
                            {t`Connect your Stripe account to start receiving payments.`}
                        </p>
                        {!account?.stripe_connect_setup_complete && (
                            <Button variant={'light'} component={NavLink} to={'/account/payment'}>
                                {t`Connect with Stripe`}
                            </Button>)
                        }
                    </Card>

                    <Card className={hasImages ? classes.completedCard : ''}>
                        {hasImages && <CompletedBadge/>}
                        <h2>
                            {t`🎨 Customize your event page`}
                        </h2>
                        <p>
                            {t`Customize your event page to match your brand and style.`}
                        </p>
                        <Button component={NavLink} variant={'light'}
                                to={'/manage/event/' + eventId + '/homepage-designer'}>
                            {t`Customize your event page`}
                        </Button>
                    </Card>

                    <Card className={event?.status === 'LIVE' ? classes.completedCard : ''}>
                        {event?.status === 'LIVE' && <CompletedBadge/>}
                        <h2>
                            {t`🚀 Set your event live`}
                        </h2>
                        <p>
                            {t`Once you're ready, set your event live and start selling products.`}
                        </p>
                        {event?.status !== 'LIVE' &&
                            (<Button variant={'light'} onClick={handleStatusToggle}>
                                    {t`Set your event live`}
                                </Button>
                            )}
                    </Card>
                </div>
            </PageBody>
        </>
    );
}

export default GettingStarted;
