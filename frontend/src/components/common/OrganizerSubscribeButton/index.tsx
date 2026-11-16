import {useState} from "react";
import {Button, Stack, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {useLingui} from "@lingui/react";
import {Organizer} from "../../../types";
import {Modal, ModalFooter} from "../Modal";
import {useSubscribeToOrganizer} from "../../../mutations/useOrganizerSubscription";
import {showError} from "../../../utilites/notifications";

export const OrganizerSubscribeButton = ({organizer}: {organizer: Organizer}) => {
    const [opened, setOpened] = useState(false);
    const [email, setEmail] = useState('');
    const {i18n} = useLingui();
    const subscribe = useSubscribeToOrganizer(organizer.id);
    if (!organizer.settings?.new_event_notifications_enabled || organizer.status !== 'LIVE') return null;

    return <>
        <Button variant="light" onClick={() => {subscribe.reset(); setOpened(true);}}>{t`Subscribe to new events`}</Button>
        <Modal opened={opened} onClose={() => setOpened(false)} heading={t`Subscribe to new events`} size="md">
            {subscribe.isSuccess ? <Stack role="status">
                <Text>{t`Check your email for a confirmation link. Notifications start after you confirm.`}</Text>
                <ModalFooter><Button onClick={() => setOpened(false)}>{t`Close`}</Button></ModalFooter>
            </Stack> : <form onSubmit={event => {
                event.preventDefault();
                subscribe.mutate({email, locale: i18n.locale}, {onError: () => showError(t`Unable to subscribe. Please try again later.`)});
            }}>
                <Text mb="md">{t`Receive an email when ${organizer.name} publishes a new event. You can unsubscribe at any time.`}</Text>
                <TextInput required type="email" maxLength={255} label={t`Email address`} value={email} onChange={event => setEmail(event.currentTarget.value)} autoComplete="email"/>
                <ModalFooter>
                    <Button variant="default" onClick={() => setOpened(false)}>{t`Cancel`}</Button>
                    <Button type="submit" loading={subscribe.isPending}>{t`Subscribe`}</Button>
                </ModalFooter>
            </form>}
        </Modal>
    </>;
};
