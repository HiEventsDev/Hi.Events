import {t, Trans} from "@lingui/macro";
import {Alert, Anchor} from "@mantine/core";
import {IconInfoCircle} from "@tabler/icons-react";
import {NavLink, useParams} from "react-router";

export const CashlessDisabledNotice = () => {
    const {eventId} = useParams();

    return (
        <Alert icon={<IconInfoCircle size={16}/>} color="blue" mb="md" title={t`Cashless is switched off`}>
            <Trans>
                Attendees cannot top up and your sales points cannot take payment until you turn cashless on in{' '}
                <Anchor component={NavLink} to={`/manage/event/${eventId}/cashless/settings`}>
                    cashless settings
                </Anchor>.
            </Trans>
        </Alert>
    );
};
