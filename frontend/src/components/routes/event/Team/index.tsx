import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {TeamAccessPanel} from "../../../common/TeamAccessPanel";

const EventTeam = () => {
    const {eventId} = useParams();

    return (
        <PageBody>
            <PageTitle subheading={t`Everyone who can see or manage this event.`}>
                {t`Team`}
            </PageTitle>
            <TeamAccessPanel scope={{type: 'event', id: eventId as string}}/>
        </PageBody>
    );
};

export default EventTeam;
