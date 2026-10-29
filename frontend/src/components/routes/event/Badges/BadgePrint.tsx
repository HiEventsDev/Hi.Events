import {useEffect, useMemo, useRef, useState} from "react";
import {useParams, useSearchParams} from "react-router";
import {Button, Group, Loader, Select, Switch, Text} from "@mantine/core";
import {IconCheck, IconPrinter} from "@tabler/icons-react";
import {plural, t, Trans} from "@lingui/macro";
import {Badge, BadgeQuery, BadgeSheet} from "../../../../types.ts";
import {useGetBadgeDesign} from "../../../../queries/useGetBadgeDesign.ts";
import {useGetBadges} from "../../../../queries/useGetBadges.ts";
import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {useGetEventQuestions} from "../../../../queries/useGetEventQuestions.ts";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {useMarkBadgesPrinted} from "../../../../mutations/useMarkBadgesPrinted.ts";
import {BadgePrintSheets} from "../../../common/NameBadge/BadgePrintSheets.tsx";
import {WriteAccess} from "../../../common/WriteAccess";
import {
    computeSheetGrid,
    countBadgePrintPages,
    getBadgeDimensions,
    getBadgeImages,
    getBadgeSheetLabel,
    getDesignBackPrinting,
    getPrintUnitSize,
} from "../../../../utilites/badges.ts";
import {waitForBadgesToRender} from "../../../../utilites/badgePrinting.ts";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {getProductsFromEvent} from "../../../../utilites/helpers.ts";
import {buildSampleBadge} from "./sampleBadge.ts";
import classes from "./BadgePrint.module.scss";

const MAX_BATCH = 1000;

const parseIds = (value: string | null): number[] | undefined => (
    value ? value.split(',').map(Number).filter((id) => Number.isFinite(id) && id > 0) : undefined
);

const parseSort = (value: string | null): BadgeQuery['sort_by'] => (
    value === 'first_name' || value === 'product' || value === 'created_at' ? value : 'last_name'
);

type PrintState = 'idle' | 'confirming' | 'done';

const BadgePrint = () => {
    const {eventId} = useParams();
    const [searchParams] = useSearchParams();
    const isSample = searchParams.get('sample') === '1';
    const designQuery = useGetBadgeDesign(eventId);
    const eventQuery = useGetEvent(eventId);
    const imagesQuery = useGetEventImages(eventId);
    const questionsQuery = useGetEventQuestions(eventId);
    const meQuery = useGetMe();
    const markPrintedMutation = useMarkBadgesPrinted();
    const sheetsRef = useRef<HTMLDivElement>(null);
    const [sheet, setSheet] = useState<BadgeSheet | null>(() => {
        const value = searchParams.get('sheet');
        return value === 'A4' || value === 'LETTER' || value === 'NONE' ? value : null;
    });
    const [showCutGuides, setShowCutGuides] = useState(true);
    const [isReady, setIsReady] = useState(false);
    const [printState, setPrintState] = useState<PrintState>('idle');
    const [page, setPage] = useState(1);

    const query: BadgeQuery = useMemo(() => ({
        attendee_ids: parseIds(searchParams.get('attendee_ids')),
        product_ids: parseIds(searchParams.get('product_ids')),
        order_id: searchParams.get('order_id'),
        event_occurrence_id: searchParams.get('event_occurrence_id'),
        only_unprinted: searchParams.get('only_unprinted') === '1',
        sort_by: parseSort(searchParams.get('sort_by')),
        page,
        per_page: MAX_BATCH,
    }), [searchParams, page]);

    const badgesQuery = useGetBadges(eventId, query, !isSample);
    const design = designQuery.data;
    const event = eventQuery.data;
    const effectiveSheet = sheet ?? design?.sheet ?? 'A4';
    const backPrinting = design ? getDesignBackPrinting(design) : 'NONE';
    const grid = design ? computeSheetGrid(getPrintUnitSize(getBadgeDimensions(design), backPrinting), effectiveSheet) : null;
    const perPage = grid?.perPage ?? 0;

    const badges: Badge[] = useMemo(() => {
        if (!design) {
            return [];
        }
        if (isSample) {
            const product = getProductsFromEvent(event)?.[0];
            const sample = buildSampleBadge({
                design,
                questions: questionsQuery.data ?? [],
                product,
                firstName: meQuery.data?.first_name,
                lastName: meQuery.data?.last_name,
            });
            return Array.from({length: perPage}, (_, index) => ({...sample, attendee_id: index}));
        }
        return badgesQuery.data?.data ?? [];
    }, [design, perPage, isSample, event, questionsQuery.data, meQuery.data, badgesQuery.data]);

    const total = isSample ? badges.length : (badgesQuery.data?.meta?.total ?? 0);
    const isLoading = designQuery.isLoading || eventQuery.isLoading || imagesQuery.isLoading
        || (!isSample && badgesQuery.isLoading);

    useEffect(() => {
        if (isLoading || !design || badges.length === 0) {
            return;
        }
        let cancelled = false;
        setIsReady(false);
        waitForBadgesToRender(sheetsRef.current, design.font_family).then(() => {
            if (!cancelled) {
                setIsReady(true);
            }
        });
        return () => {
            cancelled = true;
        };
    }, [isLoading, design, badges, effectiveSheet, showCutGuides]);

    useEffect(() => {
        if (typeof window === 'undefined' || isSample) {
            return;
        }
        const handleAfterPrint = () => setPrintState('confirming');
        window.addEventListener('afterprint', handleAfterPrint);
        return () => window.removeEventListener('afterprint', handleAfterPrint);
    }, [isSample]);

    const handleMarkPrinted = () => {
        markPrintedMutation.mutate({
            eventId,
            attendeeIds: badges.map((badge) => badge.attendee_id),
        }, {
            onSuccess: () => {
                setPrintState('done');
                showSuccess(t`Badges marked as printed`);
            },
            onError: () => showError(t`We couldn't mark these badges as printed. Please try again.`),
        });
    };

    const pageCount = grid ? countBadgePrintPages(badges.length, grid, backPrinting) : 0;
    const batchCount = Math.ceil(total / MAX_BATCH);

    const changePage = (nextPage: number) => {
        setPage(nextPage);
        setPrintState('idle');
    };

    return (
        <div className={classes.page}>
            <div className={classes.toolbar}>
                <div className={classes.toolbarMain}>
                    <div className={classes.titleBlock}>
                        <h1 className={classes.title}>{isSample ? t`Test print` : t`Print badges`}</h1>
                        <Text size="sm" c="dimmed" data-testid="badge-print-summary">
                            {isLoading
                                ? t`Loading badges…`
                                : (
                                    <Trans>
                                        {plural(badges.length, {one: '# badge', other: '# badges'})} · {plural(pageCount, {one: '# page', other: '# pages'})} · {event?.title}
                                    </Trans>
                                )}
                        </Text>
                    </div>
                    <Group gap="sm" wrap="wrap" className={classes.controls}>
                        <Select
                            size="sm"
                            className={classes.sheetSelect}
                            value={effectiveSheet}
                            onChange={(value) => value && setSheet(value as BadgeSheet)}
                            allowDeselect={false}
                            data={(['A4', 'LETTER', 'NONE'] as BadgeSheet[]).map((value) => ({value, label: getBadgeSheetLabel(value)}))}
                            aria-label={t`Paper`}
                        />
                        {(effectiveSheet !== 'NONE' || backPrinting === 'FOLD_OVER') && (
                            <Switch
                                label={backPrinting === 'FOLD_OVER' ? t`Fold and cutting guides` : t`Cutting guides`}
                                checked={showCutGuides}
                                onChange={(e) => setShowCutGuides(e.currentTarget.checked)}
                            />
                        )}
                        <Button
                            leftSection={isReady ? <IconPrinter size={16}/> : <Loader size={14} color="white"/>}
                            disabled={!isReady || badges.length === 0}
                            onClick={() => window.print()}
                            data-testid="badge-print-button"
                        >
                            {isReady ? t`Print` : t`Preparing…`}
                        </Button>
                    </Group>
                </div>

                {!isSample && query.only_unprinted && total > MAX_BATCH && (
                    <Text size="sm" className={classes.notice}>
                        {t`Showing the first 1,000 of ${total} badges. Mark these as printed, then print the next batch.`}
                    </Text>
                )}

                {!isSample && !query.only_unprinted && batchCount > 1 && (
                    <Group gap="xs" className={classes.notice} data-testid="badge-print-batches">
                        <Text size="sm">{t`Batch ${page} of ${batchCount}`}</Text>
                        <Button size="compact-sm" variant="default" disabled={page <= 1} onClick={() => changePage(page - 1)}>
                            {t`Previous batch`}
                        </Button>
                        <Button size="compact-sm" variant="default" disabled={page >= batchCount} onClick={() => changePage(page + 1)}>
                            {t`Next batch`}
                        </Button>
                    </Group>
                )}

                {backPrinting === 'DUPLEX' && badges.length > 0 && (
                    <Text size="sm" className={classes.notice} data-testid="badge-print-back-notice">
                        {t`Double-sided: turn on two-sided printing and flip on the long edge.`}
                    </Text>
                )}

                {backPrinting === 'FOLD_OVER' && badges.length > 0 && (
                    <Text size="sm" className={classes.notice} data-testid="badge-print-back-notice">
                        {t`Fold-over: print single-sided at 100% scale, then fold each badge in half with the front facing out.`}
                    </Text>
                )}

                {printState === 'confirming' && (
                    <WriteAccess>
                        <div className={classes.confirm} data-testid="badge-print-confirm">
                            <Text size="sm" fw={500}>
                                {t`Did all ${badges.length} badges print correctly?`}
                            </Text>
                            <Group gap="xs">
                                <Button size="compact-sm" variant="default" onClick={() => setPrintState('idle')}>
                                    {t`Not yet`}
                                </Button>
                                <Button
                                    size="compact-sm"
                                    leftSection={<IconCheck size={14}/>}
                                    loading={markPrintedMutation.isPending}
                                    onClick={handleMarkPrinted}
                                    data-testid="badge-mark-printed-button"
                                >
                                    {t`Mark as printed`}
                                </Button>
                            </Group>
                        </div>
                    </WriteAccess>
                )}

                {printState === 'done' && (
                    <div className={classes.confirm} data-variant="done">
                        <Text size="sm" fw={500}>
                            {(query.only_unprinted && total > 0) || page < batchCount
                                ? t`Marked as printed. The next batch is ready to print.`
                                : t`Marked as printed. You can close this tab.`}
                        </Text>
                    </div>
                )}
            </div>

            <div className={classes.stage} ref={sheetsRef}>
                {!isLoading && badges.length === 0 && (
                    <div className={classes.empty}>
                        <Text fw={500}>{t`No badges to print`}</Text>
                        <Text size="sm" c="dimmed">
                            {t`Everyone matching these options already has a printed badge, or there are no attendees yet.`}
                        </Text>
                    </div>
                )}
                {design && badges.length > 0 && (
                    <BadgePrintSheets
                        layout={design}
                        badges={badges}
                        eventName={event?.title ?? ''}
                        images={getBadgeImages(imagesQuery.data)}
                        timezone={event?.timezone}
                        sheet={effectiveSheet}
                        backPrinting={backPrinting}
                        showCutGuides={showCutGuides}
                    />
                )}
            </div>
        </div>
    );
};

export default BadgePrint;
