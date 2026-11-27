import {t} from "@lingui/macro";
import {useLoaderData, useLocation, useParams} from "react-router";
import {Button, Loader} from "@mantine/core";
import {IconAlertCircle, IconCircleCheck, IconMailOff, IconMessageOff} from "@tabler/icons-react";
import {useIsHydrated} from "../../../../../hooks/useIsHydrated.ts";
import {apiErrorStatus} from "../../../../../utilites/apiErrors.ts";
import {formatPhoneNumber} from "../../../../../utilites/phoneNumber.ts";
import {useCrmResubscribe} from "../../../mutations/useCrmResubscribe.ts";
import {useCrmUnsubscribe} from "../../../mutations/useCrmUnsubscribe.ts";
import {useGetCrmUnsubscribeStatus} from "../../../queries/useGetCrmUnsubscribeStatus.ts";
import {TokenPageLoaderData} from "../../../routeLoaders/tokenPageRouteLoaders.ts";
import {StatusError, StatusHeading, StatusPage} from "../SubscribePage/StatusPage.tsx";

const CampaignUnsubscribe = () => {
    const {token} = useParams();
    const {notFound} = useLoaderData() as TokenPageLoaderData;
    const statusQuery = useGetCrmUnsubscribeStatus(token, !notFound);
    const isHydrated = useIsHydrated();
    const unsubscribe = useCrmUnsubscribe();
    const resubscribe = useCrmResubscribe();
    const status = statusQuery.data;
    const isTextMessageLink = useLocation().pathname.startsWith('/u/');

    const handleResubscribe = () => token && resubscribe.mutate(token, {
        onError: error => {
            if (apiErrorStatus(error) === 409) {
                void statusQuery.refetch();
            }
        },
    });

    const content = () => {
        if (statusQuery.isLoading) {
            return <Loader/>;
        }

        if (statusQuery.isError && apiErrorStatus(statusQuery.error) !== 404) {
            return (
                <>
                    <StatusHeading icon={IconAlertCircle} title={t`We couldn't load this page`} tone="error"/>
                    <p>
                        {isTextMessageLink
                            ? t`Something went wrong. Your text message preferences haven't changed. Please try again.`
                            : t`Something went wrong. Your email preferences haven't changed. Please try again.`}
                    </p>
                    <Button size="md" onClick={() => statusQuery.refetch()} loading={statusQuery.isFetching}>
                        {t`Try again`}
                    </Button>
                </>
            );
        }

        if (!status) {
            return (
                <>
                    <StatusHeading icon={IconAlertCircle} title={t`This link isn't valid`}/>
                    <p>
                        {isTextMessageLink
                            ? t`The link may be incomplete. Try opening it again from the text message.`
                            : t`The unsubscribe link may be incomplete. Try opening it again from the email.`}
                    </p>
                </>
            );
        }

        const isSms = status.channel === 'SMS';

        if (unsubscribe.isError) {
            return (
                <>
                    <StatusHeading
                        icon={IconAlertCircle}
                        title={isSms ? t`We couldn't opt you out` : t`We couldn't unsubscribe you`}
                        tone="error"
                    />
                    <p>
                        {isSms
                            ? t`Something went wrong on our side and you're still opted in to texts. Please try again.`
                            : t`Something went wrong on our side and you're still subscribed. Please try again.`}
                    </p>
                    <Button
                        size="md"
                        onClick={() => token && unsubscribe.mutate(token)}
                        loading={unsubscribe.isPending}
                        disabled={!isHydrated}
                    >
                        {t`Try again`}
                    </Button>
                </>
            );
        }

        const organizer = status.organizer_name;
        const email = status.email;
        const phone = formatPhoneNumber(status.phone);

        if (isSms && status.is_unsubscribed) {
            return (
                <>
                    <StatusHeading icon={IconCircleCheck} title={t`You've opted out of texts`}/>
                    <p>
                        {phone
                            ? t`${organizer} won't send marketing text messages to ${phone} any more.`
                            : t`${organizer} won't send you marketing text messages any more.`}
                    </p>
                </>
            );
        }

        if (isSms) {
            return (
                <>
                    <StatusHeading icon={IconMessageOff} title={t`Stop texts from ${organizer}`}/>
                    <p>
                        {phone
                            ? t`Stop marketing text messages from ${organizer} to ${phone}.`
                            : t`Stop marketing text messages from ${organizer}.`}
                    </p>
                    <Button
                        size="md"
                        onClick={() => token && unsubscribe.mutate(token)}
                        loading={unsubscribe.isPending}
                        disabled={!isHydrated}
                        data-testid="audience-unsubscribe-button"
                    >
                        {t`Stop texts`}
                    </Button>
                </>
            );
        }

        if (status.is_unsubscribed) {
            return (
                <>
                    <StatusHeading icon={IconCircleCheck} title={t`You're unsubscribed`}/>
                    <p>
                        {email
                            ? t`${organizer} won't send marketing emails to ${email} any more. You'll still get emails about tickets you buy.`
                            : t`${organizer} won't send you marketing emails any more. You'll still get emails about tickets you buy.`}
                    </p>
                    {resubscribe.isError && (
                        <StatusError>
                            {apiErrorStatus(resubscribe.error) === 409
                                ? t`This email address can't be subscribed again from this link.`
                                : t`We couldn't subscribe you again. Please try again.`}
                        </StatusError>
                    )}
                    {status.can_resubscribe && (
                        <Button
                            variant="default"
                            onClick={handleResubscribe}
                            loading={resubscribe.isPending}
                            disabled={!isHydrated}
                            data-testid="audience-resubscribe-button"
                        >
                            {t`Changed your mind? Resubscribe`}
                        </Button>
                    )}
                </>
            );
        }

        if (resubscribe.isSuccess) {
            const organizerName = organizer;

            return (
                <>
                    <StatusHeading icon={IconCircleCheck} title={t`You're subscribed again`}/>
                    <p>
                        {t`${organizerName} can send marketing emails to ${email} again. You can unsubscribe at any time from the link in their emails.`}
                    </p>
                </>
            );
        }

        return (
            <>
                <StatusHeading icon={IconMailOff} title={t`Unsubscribe from ${organizer}`}/>
                <p>
                    {email
                        ? t`Stop marketing emails from ${organizer} to ${email}. You'll still get emails about tickets you buy.`
                        : t`Stop marketing emails from ${organizer}. You'll still get emails about tickets you buy.`}
                </p>
                <Button
                    size="md"
                    onClick={() => token && unsubscribe.mutate(token)}
                    loading={unsubscribe.isPending}
                    disabled={!isHydrated}
                    data-testid="audience-unsubscribe-button"
                >
                    {t`Unsubscribe`}
                </Button>
            </>
        );
    };

    return <StatusPage>{content()}</StatusPage>;
};

export default CampaignUnsubscribe;
