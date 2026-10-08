import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {useGetSeatMap} from "../../../../queries/useGetSeatMap.ts";
import {useUpdateSeatMap} from "../../../../mutations/useUpdateSeatMap.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {SeatMapLayout} from "../../../lib/types.ts";
import {SeatMapDesigner} from "../../../SeatMapDesigner";
import {useSeatMapVersion} from "../../../SeatMapDesigner/useSeatMapVersion.ts";

export default function OrganizerSeatMapDesigner() {
    const {organizerId, seatMapId} = useParams();
    const seatMapQuery = useGetSeatMap(organizerId, seatMapId);
    const seatMap = seatMapQuery.data;
    const updateMutation = useUpdateSeatMap();
    const version = useSeatMapVersion(seatMap?.version, () => seatMapQuery.refetch().then(result => result.data?.version));

    if (!seatMap) {
        return null;
    }

    const onSave = async (layout: SeatMapLayout, name: string) => {
        const sentVersion = version.current();
        try {
            const response = await updateMutation.mutateAsync({organizerId, seatMapId, name, layout, version: sentVersion});
            version.markSaved(response?.data?.version ?? null);
            showSuccess(t`Seat map saved`);
            return true;
        } catch (error) {
            if (!await version.offerLatestVersion(error, sentVersion)) {
                showError(firstApiError(error, t`The seat map could not be saved`));
            }
            return false;
        }
    };

    return (
        <SeatMapDesigner
            key={version.revision}
            initialLayout={seatMap.layout}
            initialName={seatMap.name}
            isNameEditable
            backTo={`/manage/organizer/${organizerId}/seat-maps`}
            isSaving={updateMutation.isPending}
            onSave={onSave}/>
    );
}
