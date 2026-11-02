import {useCallback, useEffect, useRef, useState} from "react";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {Badge, BadgeSize, CheckInList} from "../types.ts";
import {publicCheckInClient} from "../api/check-in.client.ts";
import {GET_CHECK_IN_LIST_ATTENDEE_DETAIL_PUBLIC_QUERY_KEY} from "../queries/useGetCheckInListAttendeeDetailPublic.ts";
import {showError} from "../utilites/notifications.tsx";
import {isSsr} from "../utilites/helpers.ts";
import {BadgeBackPrinting, getDesignBackPrinting} from "../utilites/badges.ts";
import {printsOnlyFromATap, printWithoutPageAddress} from "../utilites/badgePrinting.ts";

export const GET_CHECK_IN_BADGE_QUERY_KEY = 'getCheckInListAttendeeBadgePublic';

const TEST_BADGE_KEY = 'test';

export type BadgeLabelSize = BadgeSize | 'DESIGN';

export interface BadgePrinterSettings {
    autoPrint: boolean;
    labelSize: BadgeLabelSize;
    backPrinting: BadgeBackPrinting | null;
    blackInkOnly: boolean;
}

export interface BadgePrintJob {
    badge: Badge;
    id: number;
    isTest: boolean;
}

interface PreparedBadge extends BadgePrintJob {
    key: string;
}

const DEFAULT_SETTINGS: BadgePrinterSettings = {autoPrint: false, labelSize: 'DESIGN', backPrinting: null, blackInkOnly: false};

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
    const [printsFromATap, setPrintsFromATap] = useState(false);
    const [queue, setQueue] = useState<BadgePrintJob[]>([]);
    const [prepared, setPrepared] = useState<PreparedBadge | null>(null);
    const [renderedPreparedId, setRenderedPreparedId] = useState<number | null>(null);
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

    useEffect(() => {
        setPrintsFromATap(printsOnlyFromATap());
    }, []);

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

    const nextJobId = () => nextJobIdRef.current++;

    const buildTestBadge = useCallback((): Badge | null => {
        if (!checkInList?.badge_design) {
            return null;
        }
        return {
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
        };
    }, [checkInList?.badge_design]);

    const fetchBadge = useCallback(async (attendeePublicId: string): Promise<Badge | null> => {
        if (!isEnabled || !checkInListShortId) {
            return null;
        }
        setLoadingPublicIds((current) => [...current, attendeePublicId]);
        setFailedPublicIds((current) => current.filter((id) => id !== attendeePublicId));
        try {
            const {data} = await queryClient.fetchQuery({
                queryKey: [GET_CHECK_IN_BADGE_QUERY_KEY, checkInListShortId, attendeePublicId],
                queryFn: () => publicCheckInClient.getAttendeeBadge(checkInListShortId, attendeePublicId),
                staleTime: 0,
            });
            return data;
        } catch {
            setFailedPublicIds((current) => [...current, attendeePublicId]);
            if (!isKiosk) {
                showError(t`Unable to load this badge`);
            }
            return null;
        } finally {
            setLoadingPublicIds((current) => current.filter((id) => id !== attendeePublicId));
        }
    }, [isEnabled, isKiosk, checkInListShortId, queryClient]);

    const isPrepared = (key: string) => prepared?.key === key && renderedPreparedId === prepared.id;

    const printPrepared = () => {
        if (!prepared) {
            return;
        }
        printWithoutPageAddress();
        if (!prepared.isTest) {
            markPrintedMutation.mutate(prepared.badge.attendee_public_id);
        }
    };

    const prepareBadge = useCallback(async (attendeePublicId: string) => {
        if (!printsFromATap) {
            return;
        }
        const badge = await fetchBadge(attendeePublicId);
        if (badge) {
            setPrepared({key: attendeePublicId, badge, id: nextJobId(), isTest: false});
        }
    }, [printsFromATap, fetchBadge]);

    const prepareTestBadge = useCallback(() => {
        const badge = buildTestBadge();
        if (printsFromATap && badge) {
            setPrepared({key: TEST_BADGE_KEY, badge, id: nextJobId(), isTest: true});
        }
    }, [printsFromATap, buildTestBadge]);

    const printBadge = (attendeePublicId: string) => {
        if (!printsFromATap) {
            fetchBadge(attendeePublicId).then((badge) => {
                if (badge) {
                    setQueue((current) => [...current, {badge, id: nextJobId(), isTest: false}]);
                }
            });
            return;
        }
        if (isPrepared(attendeePublicId)) {
            printPrepared();
            return;
        }
        prepareBadge(attendeePublicId);
    };

    const printTestBadge = () => {
        if (!printsFromATap) {
            const badge = buildTestBadge();
            if (badge) {
                setQueue((current) => [...current, {badge, id: nextJobId(), isTest: true}]);
            }
            return;
        }
        if (isPrepared(TEST_BADGE_KEY)) {
            printPrepared();
            return;
        }
        prepareTestBadge();
    };

    const finishJob = useCallback((finished: BadgePrintJob, printed: boolean) => {
        if (printed && !finished.isTest) {
            markPrintedMutation.mutate(finished.badge.attendee_public_id);
        }
        setQueue((current) => current.filter((queued) => queued.id !== finished.id));
    }, [markPrintedMutation]);

    const isPreparing = (key: string) => prepared?.key === key && renderedPreparedId !== prepared.id;

    const isPrinting = (attendeePublicId: string) => (
        loadingPublicIds.includes(attendeePublicId)
        || queue.some((queued) => queued.badge.attendee_public_id === attendeePublicId)
        || isPreparing(attendeePublicId)
    );

    return {
        isEnabled,
        isKiosk,
        printsFromATap,
        settings,
        autoPrint: settings.autoPrint && !printsFromATap,
        setAutoPrint: (autoPrint: boolean) => updateSettings({autoPrint}),
        setLabelSize: (labelSize: BadgeLabelSize) => updateSettings({labelSize}),
        hasBack: designBackPrinting !== 'NONE',
        backPrinting,
        setBackPrinting: (value: BadgeBackPrinting) => updateSettings({backPrinting: value}),
        setBlackInkOnly: (blackInkOnly: boolean) => updateSettings({blackInkOnly}),
        printBadge,
        prepareBadge,
        printTestBadge,
        prepareTestBadge,
        isTestBadgePreparing: isPreparing(TEST_BADGE_KEY),
        job: queue[0] ?? null,
        finishJob,
        prepared,
        markPreparedRendered: setRenderedPreparedId,
        isPrinting,
        hasFailed: (attendeePublicId: string) => failedPublicIds.includes(attendeePublicId),
        layout: checkInList?.badge_design,
        eventName: checkInList?.event?.title ?? '',
        images: checkInList?.event?.images,
        timezone: checkInList?.event?.timezone,
    };
};

export type BadgePrinter = ReturnType<typeof useBadgePrinter>;
