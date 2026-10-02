import {t} from "@lingui/macro";
import {useParams} from "react-router";
import {relabelConfirmationRequired} from "../../../../api/seat-map.client.ts";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useGetEventSeatMap} from "../../../../queries/useGetEventSeatMap.ts";
import {useUpdateEventSeatMap} from "../../../../mutations/useUpdateEventSeatMap.ts";
import {confirmationDialogAsync} from "../../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {SeatMapLayout} from "../../../lib/types.ts";
import {SeatMapDesigner} from "../../../SeatMapDesigner";
import {useSeatMapVersion} from "../../../SeatMapDesigner/useSeatMapVersion.ts";

const LISTED_RELABELS = 10;

const relabelSummary = (labels: string[]): string => {
    const listed = labels.slice(0, LISTED_RELABELS).join(', ');
    const remaining = labels.length - LISTED_RELABELS;
    return remaining > 0 ? t`${listed} and ${remaining} more` : listed;
};

export default function EventSeatMapDesigner() {
    const {eventId} = useParams();
    const event = useGetEvent(eventId).data;
    const eventSeatMapQuery = useGetEventSeatMap(eventId);
    const eventSeatMap = eventSeatMapQuery.data;
    const updateMutation = useUpdateEventSeatMap(eventId);
    const version = useSeatMapVersion(
        eventSeatMap?.version,
        () => eventSeatMapQuery.refetch().then(result => result.data?.version),
    );

    if (!event || !eventSeatMap) {
        return null;
    }

    const save = async (layout: SeatMapLayout, confirmRelabel: boolean) => {
        const sentVersion = version.current();
        try {
            const response = await updateMutation.mutateAsync({
                action: 'layout',
                layout,
                version: sentVersion,
                confirmRelabel,
            });
            const saved = response?.data;
            version.markSaved(saved !== undefined && 'version' in saved ? saved.version : null);
            showSuccess(t`Seat map saved`);
            return true;
        } catch (error) {
            if (relabelConfirmationRequired(error) || !await version.offerLatestVersion(error, sentVersion)) {
                throw error;
            }
            return false;
        }
    };

    const onSave = async (layout: SeatMapLayout) => {
        try {
            return await save(layout, false);
        } catch (error) {
            const relabel = relabelConfirmationRequired(error);

            if (!relabel) {
                showError(firstApiError(error, t`The seat map could not be saved`));
                return false;
            }

            const labels = relabel.relabelled_seat_labels;
            const confirmed = await confirmationDialogAsync(
                t`${labels.length} seats that are already sold or held will be renamed on their tickets: ${relabelSummary(labels)}`,
                {confirm: t`Rename and save`},
            );

            if (!confirmed) {
                return false;
            }

            try {
                return await save(layout, true);
            } catch (retryError) {
                showError(firstApiError(retryError, t`The seat map could not be saved`));
                return false;
            }
        }
    };

    return (
        <SeatMapDesigner
            key={version.revision}
            initialLayout={eventSeatMap.layout}
            initialName={t`Seat map for ${event.title}`}
            isNameEditable={false}
            backTo={`/manage/event/${eventId}/seating`}
            isSaving={updateMutation.isPending}
            onSave={onSave}/>
    );
}
