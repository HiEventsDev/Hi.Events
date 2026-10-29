import {useCallback, useEffect, useRef, useState} from "react";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {Badge, BadgeSize, CheckInList} from "../types.ts";
import {publicCheckInClient} from "../api/check-in.client.ts";
import {GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY} from "../queries/useGetCheckInListAttendeeDetailPublic.ts";
import {showError} from "../utilites/notifications.tsx";
import {isSsr} from "../utilites/helpers.ts";
import {BadgeBackPrinting, getDesignBackPrinting} from "../utilites/badges.ts";

export const GET_CHECK_IN_BADGE_QUERY_KEY = 'getCheckInListAttendeeBadgePublic';

export type BadgeLabelSize = BadgeSize | 'DESIGN';

export interface BadgePrinterSettings {
    autoPrint: boolean;
    labelSize: BadgeLabelSize;
    backPrinting: BadgeBackPrinting | null;
}

export interface BadgePrintJob {
    badge: Badge;
    id: number;
    isTest: boolean;
}

const DEFAULT_SETTINGS: BadgePrinterSettings = {autoPrint: false, labelSize: 'DESIGN', backPrinting: null};

const readSettings = (key: string): BadgePrinterSettings => {
    if (isSsr()) {
        return DEFAULT_SETTINGS;
    }
    try {
        const stored = localStorage.getItem(key);
        return stored ? {...DEFAULT_SETTINGS, ...JSON.parse(stored)} : DEFAULT_SETTINGS;
    } catch {
        return DEFAULT_SETTINGS;
    }
};

export const useBadgePrinter = (checkInListShortId: string | undefined, checkInList: CheckInList | undefined) => {
    const queryClient = useQueryClient();
    const storageKey = `badgePrinter:${checkInListShortId}`;
    const isEnabled = !!checkInList?.badge_printing_enabled && !!checkInList?.badge_design;
    const [settings, setSettings] = useState<BadgePrinterSettings>(() => readSettings(storageKey));
    const [queue, setQueue] = useState<BadgePrintJob[]>([]);
    const [loadingPublicIds, setLoadingPublicIds] = useState<string[]>([]);
    const [failedPublicIds, setFailedPublicIds] = useState<string[]>([]);
    const nextJobIdRef = useRef(1);
    const isKiosk = !!checkInList?.kiosk_mode;
    const designBackPrinting = checkInList?.badge_design ? getDesignBackPrinting(checkInList.badge_design) : 'NONE';
    const backPrinting: BadgeBackPrinting = designBackPrinting === 'NONE'
        ? 'NONE'
        : (settings.backPrinting ?? (designBackPrinting === 'FOLD_OVER' ? 'FOLD_OVER' : 'NONE'));

    useEffect(() => {
        setSettings(readSettings(storageKey));
    }, [storageKey]);

    const updateSettings = useCallback((patch: Partial<BadgePrinterSettings>) => {
        setSettings((current) => {
            const next = {...current, ...patch};
            try {
                localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                return next;
            }
            return next;
        });
    }, [storageKey]);

    const markPrintedMutation = useMutation({
        mutationFn: (attendeePublicId: string) => publicCheckInClient.markAttendeeBadgePrinted(checkInListShortId, attendeePublicId),
        onSuccess: (_, attendeePublicId) => queryClient.invalidateQueries({
            queryKey: [GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY, checkInListShortId, attendeePublicId],
        }),
    });

    const enqueue = useCallback((badge: Badge, isTest: boolean) => {
        const id = nextJobIdRef.current++;
        setQueue((current) => [...current, {badge, id, isTest}]);
    }, []);

    const printBadge = useCallback(async (attendeePublicId: string) => {
        if (!isEnabled || !checkInListShortId) {
            return;
        }
        setLoadingPublicIds((current) => [...current, attendeePublicId]);
        setFailedPublicIds((current) => current.filter((id) => id !== attendeePublicId));
        try {
            const {data} = await queryClient.fetchQuery({
                queryKey: [GET_CHECK_IN_BADGE_QUERY_KEY, checkInListShortId, attendeePublicId],
                queryFn: () => publicCheckInClient.getAttendeeBadge(checkInListShortId, attendeePublicId),
                staleTime: 0,
            });
            enqueue(data, false);
        } catch {
            setFailedPublicIds((current) => [...current, attendeePublicId]);
            if (!isKiosk) {
                showError(t`Unable to load this badge`);
            }
        } finally {
            setLoadingPublicIds((current) => current.filter((id) => id !== attendeePublicId));
        }
    }, [isEnabled, isKiosk, checkInListShortId, queryClient, enqueue]);

    const printTestBadge = useCallback(() => {
        if (!checkInList?.badge_design) {
            return;
        }
        enqueue({
            attendee_id: 0,
            attendee_public_id: 'A-TEST0001',
            order_id: 0,
            product_id: 0,
            event_occurrence_id: null,
            status: 'ACTIVE',
            first_name: t`Alex`,
            last_name: t`Morgan`,
            lines: [
                {source: 'FIRST_NAME', size: 'XL', text: t`Alex`},
                {source: 'LAST_NAME', size: 'L', text: t`Morgan`},
                {source: 'CUSTOM_TEXT', size: 'M', text: t`Test badge`},
            ],
            ribbon: {label: t`Test print`, color: checkInList.badge_design.accent_color},
            seat_label: null,
            badge_printed_at: null,
            badge_print_count: 0,
            print_status: 'NOT_PRINTED',
        }, true);
    }, [checkInList?.badge_design, enqueue]);

    const finishJob = useCallback((finished: BadgePrintJob, printed: boolean) => {
        if (printed && !finished.isTest) {
            markPrintedMutation.mutate(finished.badge.attendee_public_id);
        }
        setQueue((current) => current.filter((queued) => queued.id !== finished.id));
    }, [markPrintedMutation]);

    const isPrinting = useCallback((attendeePublicId: string) => (
        loadingPublicIds.includes(attendeePublicId)
        || queue.some((queued) => queued.badge.attendee_public_id === attendeePublicId)
    ), [loadingPublicIds, queue]);

    return {
        isEnabled,
        isKiosk,
        settings,
        setAutoPrint: (autoPrint: boolean) => updateSettings({autoPrint}),
        setLabelSize: (labelSize: BadgeLabelSize) => updateSettings({labelSize}),
        hasBack: designBackPrinting !== 'NONE',
        backPrinting,
        setBackPrinting: (value: BadgeBackPrinting) => updateSettings({backPrinting: value}),
        printBadge,
        printTestBadge,
        job: queue[0] ?? null,
        finishJob,
        isPrinting,
        hasFailed: (attendeePublicId: string) => failedPublicIds.includes(attendeePublicId),
        layout: checkInList?.badge_design,
        eventName: checkInList?.event?.title ?? '',
        images: checkInList?.event?.images,
        timezone: checkInList?.event?.timezone,
    };
};

export type BadgePrinter = ReturnType<typeof useBadgePrinter>;
