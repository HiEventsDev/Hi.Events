import {useMemo, useState} from "react";
import {Button, MultiSelect, SegmentedControl, Select, Text} from "@mantine/core";
import {IconPrinter} from "@tabler/icons-react";
import {plural, t, Trans} from "@lingui/macro";
import {BadgeQuery, BadgeSheet, EventType, IdParam, ProductType, QueryFilters} from "../../../types.ts";
import {Modal, ModalFooter} from "../../common/Modal";
import {OccurrenceSelect} from "../../common/OccurrenceSelect";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventOccurrences} from "../../../queries/useGetEventOccurrences.ts";
import {useGetBadgeDesign} from "../../../queries/useGetBadgeDesign.ts";
import {useGetBadgeStats} from "../../../queries/useGetBadgeStats.ts";
import {useGetBadges} from "../../../queries/useGetBadges.ts";
import {getProductsFromEvent} from "../../../utilites/helpers.ts";
import {
    computeSheetGrid,
    countBadgePrintPages,
    getBadgeDimensions,
    getBadgeSheetLabel,
    getDesignBackPrinting,
    getPrintUnitSize,
    openBadgePrintWindow,
} from "../../../utilites/badges.ts";
import classes from "./PrintBadgesModal.module.scss";

const MAX_BATCH = 1000;

interface PrintBadgesModalProps {
    eventId: IdParam;
    onClose: () => void;
    defaultOccurrenceId?: string | null;
}

type Audience = 'unprinted' | 'everyone';

export const PrintBadgesModal = ({eventId, onClose, defaultOccurrenceId = null}: PrintBadgesModalProps) => {
    const {data: event} = useGetEvent(eventId);
    const {data: design} = useGetBadgeDesign(eventId);
    const isRecurring = event?.type === EventType.RECURRING;
    const {data: occurrencesData} = useGetEventOccurrences(eventId, {pageNumber: 1, perPage: 100} as QueryFilters, isRecurring);
    const [audience, setAudience] = useState<Audience>('unprinted');
    const [productIds, setProductIds] = useState<string[]>([]);
    const [occurrenceId, setOccurrenceId] = useState<string | null>(defaultOccurrenceId);
    const [sortBy, setSortBy] = useState<NonNullable<BadgeQuery['sort_by']>>('last_name');
    const [sheet, setSheet] = useState<BadgeSheet | null>(null);
    const statsQuery = useGetBadgeStats(eventId, occurrenceId);
    const stats = statsQuery.data;

    const query: BadgeQuery = {
        product_ids: productIds,
        event_occurrence_id: occurrenceId,
        only_unprinted: audience === 'unprinted',
        sort_by: sortBy,
    };
    const countQuery = useGetBadges(eventId, {...query, per_page: 1});
    const matching = countQuery.data?.meta?.total ?? 0;
    const effectiveSheet = sheet ?? design?.sheet ?? 'A4';

    const ticketOptions = useMemo(() => (getProductsFromEvent(event) ?? [])
        .filter((product) => product.product_type === ProductType.Ticket)
        .map((product) => ({value: String(product.id), label: product.title})), [event]);

    const pages = useMemo(() => {
        if (!design || matching === 0) {
            return 0;
        }
        const backPrinting = getDesignBackPrinting(design);
        const grid = computeSheetGrid(getPrintUnitSize(getBadgeDimensions(design), backPrinting), effectiveSheet);
        return countBadgePrintPages(Math.min(matching, MAX_BATCH), grid, backPrinting);
    }, [design, matching, effectiveSheet]);

    const unprinted = (stats?.not_printed ?? 0) + (stats?.needs_reprint ?? 0);

    const handleOpen = () => {
        openBadgePrintWindow(eventId, {...query, sheet: effectiveSheet});
        onClose();
    };

    return (
        <Modal opened onClose={onClose} heading={t`Print badges`} size="lg">
            <div className={classes.stats}>
                <div className={classes.stat}>
                    <span className={classes.statValue}>{stats?.total ?? '–'}</span>
                    <span className={classes.statLabel}>{t`Attendees`}</span>
                </div>
                <div className={classes.stat}>
                    <span className={classes.statValue}>{stats?.printed ?? '–'}</span>
                    <span className={classes.statLabel}>{t`Printed`}</span>
                </div>
                <div className={classes.stat}>
                    <span className={classes.statValue}>{stats?.not_printed ?? '–'}</span>
                    <span className={classes.statLabel}>{t`Not printed`}</span>
                </div>
                <div className={classes.stat}>
                    <span className={classes.statValue} data-warning={(stats?.needs_reprint ?? 0) > 0 || undefined}>
                        {stats?.needs_reprint ?? '–'}
                    </span>
                    <span className={classes.statLabel}>{t`Need reprinting`}</span>
                </div>
            </div>

            <div className={classes.fields}>
                <div>
                    <Text size="sm" fw={500} mb={6}>{t`Who to print`}</Text>
                    <SegmentedControl
                        fullWidth
                        value={audience}
                        onChange={(value) => setAudience(value as Audience)}
                        data={[
                            {value: 'unprinted', label: t`Not printed yet (${unprinted})`},
                            {value: 'everyone', label: t`Everyone`},
                        ]}
                        data-testid="print-badges-audience"
                    />
                    <Text size="xs" c="dimmed" mt={6}>
                        {t`Includes anyone whose name or badge details changed since their badge was printed.`}
                    </Text>
                </div>

                {ticketOptions.length > 1 && (
                    <MultiSelect
                        label={t`Tickets`}
                        placeholder={productIds.length ? undefined : t`All tickets`}
                        data={ticketOptions}
                        value={productIds}
                        onChange={setProductIds}
                        clearable
                    />
                )}

                {isRecurring && event?.timezone && (occurrencesData?.data?.length ?? 0) > 0 && (
                    <OccurrenceSelect
                        label={t`Date`}
                        occurrences={occurrencesData?.data ?? []}
                        timezone={event.timezone}
                        value={occurrenceId}
                        onChange={setOccurrenceId}
                        placeholder={t`All dates`}
                        clearable
                    />
                )}

                <div className={classes.row}>
                    <Select
                        label={t`Order`}
                        value={sortBy}
                        onChange={(value) => value && setSortBy(value as NonNullable<BadgeQuery['sort_by']>)}
                        allowDeselect={false}
                        data={[
                            {value: 'last_name', label: t`Last name (A–Z)`},
                            {value: 'first_name', label: t`First name (A–Z)`},
                            {value: 'product', label: t`Ticket type`},
                            {value: 'created_at', label: t`Registration order`},
                        ]}
                    />
                    <Select
                        label={t`Paper`}
                        value={effectiveSheet}
                        onChange={(value) => value && setSheet(value as BadgeSheet)}
                        allowDeselect={false}
                        data={(['A4', 'LETTER', 'NONE'] as BadgeSheet[]).map((value) => ({
                            value,
                            label: getBadgeSheetLabel(value),
                        }))}
                    />
                </div>
            </div>

            <div className={classes.summary} data-testid="print-badges-summary">
                {matching === 0
                    ? <Text size="sm" c="dimmed">{t`No badges match these options.`}</Text>
                    : (
                        <Text size="sm">
                            <Trans>
                                <b>{plural(Math.min(matching, MAX_BATCH), {one: '# badge', other: '# badges'})}</b> on {plural(pages, {one: '# page', other: '# pages'})}
                            </Trans>
                        </Text>
                    )}
                {matching > MAX_BATCH && (
                    <Text size="xs" c="dimmed">
                        {t`Prints the first 1,000. Mark them as printed, then print the next batch.`}
                    </Text>
                )}
            </div>

            <ModalFooter>
                <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                <Button
                    leftSection={<IconPrinter size={16}/>}
                    onClick={handleOpen}
                    disabled={matching === 0}
                    data-testid="print-badges-open-preview-button"
                >
                    {t`Open print preview`}
                </Button>
            </ModalFooter>
        </Modal>
    );
};
