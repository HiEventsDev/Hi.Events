import {CheckInList} from "../../../../types.ts";

type LaunchArgs = {
    occurrenceId: number;
    checkInLists: CheckInList[] | undefined;
    onCreateForOccurrence: (occurrenceId: number) => void;
    onSelectList: (occurrenceId: number) => void;
};

export const openCheckInTab = (shortId: string | number, occurrenceId?: number): void => {
    if (typeof window === 'undefined') return;
    const suffix = occurrenceId ? `?occurrence=${occurrenceId}` : '';
    window.open(`/check-in/${shortId}${suffix}`, '_blank');
};

export const launchCheckInForOccurrence = ({
    occurrenceId,
    checkInLists,
    onCreateForOccurrence,
    onSelectList,
}: LaunchArgs): void => {
    const hasUsableList = checkInLists?.some(list =>
        !list.event_occurrence_id || list.event_occurrence_id === occurrenceId
    );
    if (hasUsableList) {
        onSelectList(occurrenceId);
        return;
    }

    onCreateForOccurrence(occurrenceId);
};
