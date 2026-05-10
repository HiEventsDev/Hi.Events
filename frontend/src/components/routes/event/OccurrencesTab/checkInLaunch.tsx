import {modals} from "@mantine/modals";
import {Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {CheckInList} from "../../../../types.ts";

type LaunchArgs = {
    occurrenceId: number;
    checkInLists: CheckInList[] | undefined;
    onCreateForOccurrence: (occurrenceId: number) => void;
};

const openInNewTab = (shortId: string | number) => {
    if (typeof window === 'undefined') return;
    window.open(`/check-in/${shortId}`, '_blank');
};

/**
 * Launching check-in from a specific occurrence has three branches:
 *
 *  1. There's a list scoped to the occurrence — open it.
 *  2. There's only a global (no-occurrence) list — prompt: "use the all-occurrences
 *     list?" or "create a date-specific list?". The previous behaviour silently
 *     fell through to the global list, which lets staff scan attendees from
 *     other dates without realising it.
 *  3. No relevant list exists — open the create flow scoped to this occurrence.
 */
export const launchCheckInForOccurrence = ({
    occurrenceId,
    checkInLists,
    onCreateForOccurrence,
}: LaunchArgs): void => {
    const scoped = checkInLists?.find(list => list.event_occurrence_id === occurrenceId);
    if (scoped) {
        openInNewTab(scoped.short_id);
        return;
    }

    const global = checkInLists?.find(list => !list.event_occurrence_id);
    if (global) {
        modals.openConfirmModal({
            title: t`No date-specific check-in list`,
            children: (
                <>
                    <Text size="sm" mb="md">
                        {t`There's no check-in list scoped to this date. The "${global.name}" list checks in attendees across every date — staff scanning a ticket for a different date will still succeed.`}
                    </Text>
                    <Text size="sm">
                        {t`Use the all-dates list, or create a list for this date?`}
                    </Text>
                </>
            ),
            labels: {confirm: t`Use all-dates list`, cancel: t`Create for this date`},
            // The "cancel" button is the safer of the two — that's where Mantine
            // anchors keyboard focus by default, and it leads to creation.
            onCancel: () => onCreateForOccurrence(occurrenceId),
            onConfirm: () => openInNewTab(global.short_id),
        });
        return;
    }

    onCreateForOccurrence(occurrenceId);
};
