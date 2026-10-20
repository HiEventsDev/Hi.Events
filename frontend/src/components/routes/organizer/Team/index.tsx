import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {PageBody} from "../../../common/PageBody";
import {PageTitle} from "../../../common/PageTitle";
import {TeamAccessPanel} from "../../../common/TeamAccessPanel";

const OrganizerTeam = () => {
    const {organizerId} = useParams();

    return (
        <PageBody>
            <PageTitle subheading={t`Everyone who can manage this organizer and all of its events.`}>
                {t`Team`}
            </PageTitle>
            <TeamAccessPanel scope={{type: 'organizer', id: organizerId as string}}/>
        </PageBody>
    );
};

export default OrganizerTeam;
