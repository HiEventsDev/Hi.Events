import {t} from "@lingui/macro";
import {Badge} from "@mantine/core";
import {ProfileAppearance} from "../../../../api/lineup.client.ts";

export const EventStatusBadge = ({status}: { status: ProfileAppearance['event_status'] }) => {
    switch (status) {
        case 'LIVE':
            return <Badge size="xs" variant="light" color="teal">{t`Live`}</Badge>;
        case 'DRAFT':
            return <Badge size="xs" variant="light" color="gray">{t`Draft`}</Badge>;
        case 'ARCHIVED':
            return <Badge size="xs" variant="light" color="gray">{t`Archived`}</Badge>;
        case 'PENDING_MANUAL_REVIEW':
            return <Badge size="xs" variant="light" color="yellow">{t`In review`}</Badge>;
        default:
            return null;
    }
};
