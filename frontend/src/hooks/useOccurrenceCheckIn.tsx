import {useCallback, useEffect, useState} from "react";
import {t} from "@lingui/macro";
import {IdParam} from "../types.ts";
import {useGetEventCheckInLists} from "../queries/useGetCheckInLists.ts";
import {launchCheckInForOccurrence} from "../components/routes/event/OccurrencesTab/checkInLaunch.tsx";
import {CreateCheckInListModal} from "../components/modals/CreateCheckInListModal";
import {SelectCheckInListModal} from "../components/modals/SelectCheckInListModal";
import {showError} from "../utilites/notifications.tsx";

export const useOccurrenceCheckIn = (eventId: IdParam) => {
    const checkInListsQuery = useGetEventCheckInLists(eventId);
    const checkInLists = checkInListsQuery?.data?.data;

    const [createForOccurrenceId, setCreateForOccurrenceId] = useState<number | undefined>();
    const [selectForOccurrenceId, setSelectForOccurrenceId] = useState<number | null>(null);
    const [pendingOccurrenceId, setPendingOccurrenceId] = useState<number | null>(null);

    const launch = useCallback((occurrenceId: number) => {
        launchCheckInForOccurrence({
            occurrenceId,
            checkInLists,
            onCreateForOccurrence: setCreateForOccurrenceId,
            onSelectList: setSelectForOccurrenceId,
        });
    }, [checkInLists]);

    const launchCheckIn = useCallback((occurrenceId: number) => {
        if (checkInListsQuery.isLoading || checkInListsQuery.isFetching) {
            setPendingOccurrenceId(occurrenceId);
            return;
        }
        launch(occurrenceId);
    }, [checkInListsQuery.isLoading, checkInListsQuery.isFetching, launch]);

    useEffect(() => {
        if (pendingOccurrenceId === null) return;
        if (checkInListsQuery.isLoading || checkInListsQuery.isFetching) return;

        const occurrenceId = pendingOccurrenceId;
        setPendingOccurrenceId(null);

        if (checkInListsQuery.isError) {
            showError(t`We couldn't load the check-in lists. Please try again.`);
            return;
        }
        launch(occurrenceId);
    }, [pendingOccurrenceId, checkInListsQuery.isLoading, checkInListsQuery.isFetching, checkInListsQuery.isError, launch]);

    const checkInModals = (
        <>
            {selectForOccurrenceId !== null && (
                <SelectCheckInListModal
                    onClose={() => setSelectForOccurrenceId(null)}
                    eventId={eventId}
                    occurrenceId={selectForOccurrenceId}
                    lists={(checkInLists ?? []).filter(list =>
                        !list.event_occurrence_id || list.event_occurrence_id === selectForOccurrenceId
                    )}
                    onCreateForOccurrence={setCreateForOccurrenceId}
                />
            )}

            {createForOccurrenceId && (
                <CreateCheckInListModal
                    onClose={() => setCreateForOccurrenceId(undefined)}
                    initialOccurrenceId={createForOccurrenceId}
                />
            )}
        </>
    );

    return {launchCheckIn, checkInModals};
};
