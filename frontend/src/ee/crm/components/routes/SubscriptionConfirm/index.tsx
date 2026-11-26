import {plural, t} from "@lingui/macro";
import {useLoaderData, useParams} from "react-router";
import {Button, Loader} from "@mantine/core";
import {IconAlertCircle, IconCircleCheck, IconMailCheck} from "@tabler/icons-react";
import {useIsHydrated} from "../../../../../hooks/useIsHydrated.ts";
import {apiErrorStatus} from "../../../../../utilites/apiErrors.ts";
import {useConfirmSubscription} from "../../../mutations/useCrmSignupFormMutations.ts";
import {useGetSubscriptionConfirmation} from "../../../queries/useCrmSignupFormQueries.ts";
import {TokenPageLoaderData} from "../../../routeLoaders/tokenPageRouteLoaders.ts";
import {StatusError, StatusHeading, StatusPage} from "../SubscribePage/StatusPage.tsx";

const SubscriptionConfirm = () => {
    const {token} = useParams();
    const {notFound} = useLoaderData() as TokenPageLoaderData;
    const statusQuery = useGetSubscriptionConfirmation(token, !notFound);
    const confirm = useConfirmSubscription();
    const isHydrated = useIsHydrated();
    const status = confirm.data?.data ?? statusQuery.data;
    const isMalformedLink = notFound || apiErrorStatus(statusQuery.error) === 404;

    const content = () => {
        if (statusQuery.isLoading) {
            return <Loader/>;
        }

        if (isMalformedLink || status?.status === 'invalid') {
            return (
                <>
                    <StatusHeading icon={IconAlertCircle} title={t`This link isn't valid`}/>
                    <p>{t`The link may be incomplete. Try opening it again from the email.`}</p>
                </>
            );
        }

        if (statusQuery.isError || !status) {
            return (
                <>
                    <StatusHeading icon={IconAlertCircle} title={t`We couldn't load this page`} tone="error"/>
                    <p>{t`Something went wrong on our side and you haven't been subscribed yet. Please try again.`}</p>
                    <Button onClick={() => statusQuery.refetch()} loading={statusQuery.isFetching}>{t`Try again`}</Button>
                </>
            );
        }

        const organizerName = status.organizer_name ?? '';
        const email = status.masked_email ?? '';

        switch (status.status) {
            case 'confirmed':
                return (
                    <>
                        <StatusHeading icon={IconCircleCheck} title={t`You're subscribed`}/>
                        <p>
                            {status.success_message || (organizerName
                                ? t`You'll now get updates from ${organizerName}. You can unsubscribe at any time from the link in their emails.`
                                : t`You can unsubscribe at any time from the link in the emails you receive.`)}
                        </p>
                    </>
                );
            case 'expired':
                return (
                    <>
                        <StatusHeading icon={IconAlertCircle} title={t`This link has expired`}/>
                        <p>
                            {plural(status.link_lifetime_days, {
                                one: 'Confirmation links work for # day. Sign up again to get a new link.',
                                other: 'Confirmation links work for # days. Sign up again to get a new link.',
                            })}
                        </p>
                        {status.form_public_id && (
                            <Button component="a" href={`/subscribe/${status.form_public_id}`} variant="default">{t`Sign up again`}</Button>
                        )}
                    </>
                );
            default:
                return (
                    <>
                        <StatusHeading icon={IconMailCheck} title={t`Confirm your subscription`}/>
                        <p>
                            {organizerName
                                ? t`Confirm that ${email} should get updates from ${organizerName}.`
                                : t`Confirm that ${email} should get updates.`}
                        </p>
                        {confirm.isError && (
                            <StatusError>{t`We couldn't confirm your subscription. Please try again.`}</StatusError>
                        )}
                        <Button
                            size="md"
                            onClick={() => token && confirm.mutate(token)}
                            loading={confirm.isPending}
                            disabled={!isHydrated}
                            data-testid="subscription-confirm-button"
                        >
                            {t`Confirm subscription`}
                        </Button>
                    </>
                );
        }
    };

    return <StatusPage>{content()}</StatusPage>;
};

export default SubscriptionConfirm;
