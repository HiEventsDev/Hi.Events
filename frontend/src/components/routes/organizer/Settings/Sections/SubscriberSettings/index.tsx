import {Alert, Switch} from "@mantine/core";
import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {Card} from "../../../../../common/Card";
import {HeadingWithDescription} from "../../../../../common/Card/CardHeading";
import {WriteAccess} from "../../../../../common/WriteAccess";
import {useGetOrganizerSettings} from "../../../../../../queries/useGetOrganizerSettings";
import {useUpdateOrganizerSettings} from "../../../../../../mutations/useUpdateOrganizerSettings";
import {showError, showSuccess} from "../../../../../../utilites/notifications";

export default function SubscriberSettings() {
    const {organizerId} = useParams();
    const settings = useGetOrganizerSettings(organizerId);
    const update = useUpdateOrganizerSettings();
    return <Card>
        <HeadingWithDescription heading={t`New-event subscriptions`} description={t`Let visitors subscribe and receive an email when you first publish an event.`}/>
        {settings.isError && <Alert color="red">{t`Unable to load subscription settings.`}</Alert>}
        <WriteAccess><Switch label={t`Enable new-event notifications`} checked={settings.data?.new_event_notifications_enabled ?? false}
            description={t`Disabling pauses signups and queued announcements, but retains subscribers. Reenabling announces future events only.`}
            disabled={!settings.data || update.isPending}
            onChange={event => update.mutate({organizerId, organizerSettings: {new_event_notifications_enabled: event.currentTarget.checked}}, {
                onSuccess: () => showSuccess(t`Subscription settings updated`),
                onError: () => showError(t`Unable to update subscription settings.`),
            })}/></WriteAccess>
    </Card>;
}
