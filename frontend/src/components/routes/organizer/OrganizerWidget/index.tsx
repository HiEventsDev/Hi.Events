import {useParams} from "react-router";
import {WidgetEditor} from "../../../common/WidgetEditor";
import {LoadingMask} from "../../../common/LoadingMask";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";

const OrganizerWidget = () => {
    const {organizerId} = useParams();
    const organizerQuery = useGetOrganizer(organizerId);

    if (!organizerQuery.data) {
        return <LoadingMask/>;
    }

    return (
        <WidgetEditor
            target={{organizerId: String(organizerId)}}
            theme={organizerQuery.data.settings?.homepage_theme_settings}
            homepageDesignerPath={`/manage/organizer/${organizerId}/organizer-homepage-designer`}
        />
    );
};

export default OrganizerWidget;
