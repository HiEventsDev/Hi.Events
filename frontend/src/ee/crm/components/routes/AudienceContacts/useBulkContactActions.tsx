import {useEffect, useRef, useState} from "react";
import {useQueryClient} from "@tanstack/react-query";
import {plural, t} from "@lingui/macro";
import {IdParam} from "../../../../../types.ts";
import {apiErrorStatus, firstApiError} from "../../../../../utilites/apiErrors.ts";
import {showError, showInfo, showSuccess, showSuccessWithUndo} from "../../../../../utilites/notifications.tsx";
import {
    CrmBulkRequest,
    invalidateAfterBulkChange,
    useStartCrmBulkOperation,
    useUndoCrmBulkOperation,
} from "../../../mutations/useCrmBulkMutations.ts";
import {useGetCrmBulkOperation} from "../../../queries/useGetCrmBulkOperation.ts";
import {CrmBulkOperation, CrmBulkSelection, CrmTag} from "../../../types.ts";
import {formatCount} from "../../../utilites/format.ts";
import {BulkProgress} from "../../BulkActionBar";

interface PendingConfirmation {
    action: 'UNSUBSCRIBE' | 'DELETE';
    count: number;
    changedCount: boolean;
}

const selectionChangedTotal = (error: unknown): number | null => {
    const data = (error as { response?: { data?: { error_code?: string, total?: number } } })?.response?.data;

    return apiErrorStatus(error) === 409 && data?.error_code === 'SELECTION_CHANGED' && typeof data.total === 'number' ? data.total : null;
};

export const useBulkContactActions = ({organizerId, selection, count, onFinished}: {
    organizerId: IdParam,
    selection: CrmBulkSelection | null,
    count: number,
    onFinished: () => void,
}) => {
    const queryClient = useQueryClient();
    const startOperation = useStartCrmBulkOperation();
    const undoOperation = useUndoCrmBulkOperation();
    const [confirmation, setConfirmation] = useState<PendingConfirmation | null>(null);
    const [runningId, setRunningId] = useState<number | null>(null);
    const tagRef = useRef<CrmTag | null>(null);
    const runningQuery = useGetCrmBulkOperation(organizerId, runningId ?? undefined);

    const undo = (operation: CrmBulkOperation) => undoOperation.mutateAsync({organizerId, operationId: operation.id}).then(
        () => showSuccess(t`Change undone`),
        error => showError(firstApiError(error, t`This change can no longer be undone.`)),
    );

    const announce = (operation: CrmBulkOperation) => {
        const tag = tagRef.current?.name ?? '';
        const skipped = operation.total - operation.changed;

        if (operation.status === 'FAILED') {
            const processed = formatCount(operation.processed);
            const total = formatCount(operation.total);

            showError(t`We couldn't finish. Contacts updated before it stopped: ${processed} of ${total}.`);
            return;
        }

        switch (operation.action) {
            case 'ADD_TAGS':
                if (operation.changed === 0) {
                    showInfo(t`Everyone selected already has the ${tag} tag`);
                } else {
                    showSuccessWithUndo(plural(operation.changed, {one: `Tagged # contact with ${tag}`, other: `Tagged # contacts with ${tag}`}), () => undo(operation), {undoLabel: t`Undo`, autoClose: 10000});
                }
                return;
            case 'REMOVE_TAGS':
                if (operation.changed === 0) {
                    showInfo(t`Nobody selected has the ${tag} tag`);
                } else {
                    showSuccessWithUndo(plural(operation.changed, {one: `Removed ${tag} from # contact`, other: `Removed ${tag} from # contacts`}), () => undo(operation), {undoLabel: t`Undo`, autoClose: 10000});
                }
                return;
            case 'UNSUBSCRIBE':
                if (operation.changed === 0) {
                    showInfo(t`Everyone selected had already unsubscribed or marked an email as spam`);
                } else if (skipped > 0) {
                    showSuccess([
                        plural(operation.changed, {one: 'Unsubscribed # contact.', other: 'Unsubscribed # contacts.'}),
                        plural(skipped, {
                            one: 'The other contact had already unsubscribed or marked an email as spam.',
                            other: 'The other # had already unsubscribed or marked an email as spam.',
                        }),
                    ].join(' '));
                } else {
                    showSuccess(plural(operation.changed, {one: 'Unsubscribed # contact', other: 'Unsubscribed # contacts'}));
                }
                return;
            case 'DELETE':
                showSuccess(plural(operation.changed, {one: 'Deleted # contact', other: 'Deleted # contacts'}));
        }
    };

    const finish = (operation: CrmBulkOperation) => {
        setRunningId(null);
        void invalidateAfterBulkChange(queryClient, organizerId);
        announce(operation);
        onFinished();
    };

    useEffect(() => {
        const operation = runningQuery.data;

        if (runningId !== null && operation && operation.id === runningId && operation.status !== 'RUNNING') {
            finish(operation);
        }
    }, [runningQuery.data, runningId]);

    useEffect(() => {
        if (runningId !== null && runningQuery.isError) {
            setRunningId(null);
            void invalidateAfterBulkChange(queryClient, organizerId);
            showError(t`We lost track of this update. Refresh the page to see what changed.`);
            onFinished();
        }
    }, [runningQuery.isError, runningId]);

    const run = (request: CrmBulkRequest) => {
        if (!selection) {
            return;
        }

        startOperation.mutate({organizerId, selection, request}, {
            onSuccess: ({data}) => {
                setConfirmation(null);

                if (data.status === 'RUNNING') {
                    setRunningId(data.id);
                } else {
                    finish(data);
                }
            },
            onError: error => {
                const changedTotal = selectionChangedTotal(error);

                if (changedTotal === 0) {
                    setConfirmation(null);
                    void invalidateAfterBulkChange(queryClient, organizerId);
                    showInfo(t`Nobody matches this selection any more`);
                    onFinished();
                    return;
                }

                if (changedTotal !== null && (request.action === 'UNSUBSCRIBE' || request.action === 'DELETE')) {
                    setConfirmation({action: request.action, count: changedTotal, changedCount: true});
                    return;
                }

                setConfirmation(null);
                showError(firstApiError(error, t`We couldn't update these contacts. Please try again.`));
            },
        });
    };

    const running = runningQuery.data?.status === 'RUNNING' ? runningQuery.data : null;
    const progress: BulkProgress | null = runningId === null ? null : {
        label: (() => {
            const total = running?.total ?? count;
            switch (running?.action) {
                case 'DELETE':
                    return plural(total, {one: 'Deleting # contact…', other: 'Deleting # contacts…'});
                case 'UNSUBSCRIBE':
                    return plural(total, {one: 'Unsubscribing # contact…', other: 'Unsubscribing # contacts…'});
                default:
                    return plural(total, {one: 'Updating # contact…', other: 'Updating # contacts…'});
            }
        })(),
        percent: running && running.total > 0 ? (running.processed / running.total) * 100 : 0,
    };

    return {
        progress,
        isBusy: startOperation.isPending || runningId !== null,
        confirmation,
        isConfirming: startOperation.isPending,
        addTag: (tag: CrmTag) => {
            tagRef.current = tag;
            run({action: 'ADD_TAGS', tagIds: [tag.id]});
        },
        removeTag: (tag: CrmTag) => {
            tagRef.current = tag;
            run({action: 'REMOVE_TAGS', tagIds: [tag.id]});
        },
        askUnsubscribe: () => setConfirmation({action: 'UNSUBSCRIBE', count, changedCount: false}),
        askDelete: () => setConfirmation({action: 'DELETE', count, changedCount: false}),
        confirm: () => confirmation && run({action: confirmation.action, confirmationCount: confirmation.count}),
        cancelConfirmation: () => setConfirmation(null),
    };
};
