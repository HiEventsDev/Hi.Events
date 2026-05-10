import {useMemo, useState} from "react";
import {Drawer, TextInput} from "@mantine/core";
import {IconCalendar, IconCheck, IconChevronDown, IconSearch} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {EventOccurrence} from "../../../types.ts";
import {
    filterAndGroupOccurrences,
    formatOccurrenceLabel,
    isToday,
} from "../../common/OccurrenceSelect/occurrenceSelectUtils.ts";
import classes from "./OccurrenceFilterPill.module.scss";

interface OccurrenceFilterPillProps {
    occurrences: EventOccurrence[];
    activeOccurrenceId: number | null;
    timezone: string;
    onSelect: (occurrenceId: number | null) => void;
}

const MAX_VISIBLE = 50;

export const OccurrenceFilterPill = ({
                                         occurrences,
                                         activeOccurrenceId,
                                         timezone: tz,
                                         onSelect,
                                     }: OccurrenceFilterPillProps) => {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [search, setSearch] = useState('');

    const activeOccurrence = useMemo(
        () => activeOccurrenceId
            ? occurrences.find(o => o.id === activeOccurrenceId) ?? null
            : null,
        [activeOccurrenceId, occurrences],
    );

    const {grouped, totalFiltered, totalAvailable, truncated} = useMemo(
        () => filterAndGroupOccurrences(occurrences, {
            search,
            tz,
            filterCancelled: true,
            maxVisible: MAX_VISIBLE,
        }),
        [occurrences, search, tz],
    );

    const pillLabel = activeOccurrence ? formatOccurrenceLabel(activeOccurrence, tz) : t`All dates`;

    const handleSelect = (occurrenceId: number | null) => {
        onSelect(occurrenceId);
        setSheetOpen(false);
        setSearch('');
    };

    return (
        <>
            <button
                type="button"
                className={`${classes.pill} ${activeOccurrence ? classes.pillActive : ''}`}
                onClick={() => setSheetOpen(true)}
                aria-label={t`Filter by date`}
                aria-haspopup="dialog"
            >
                <IconCalendar size={14}/>
                <span className={classes.pillLabel}>{pillLabel}</span>
                <IconChevronDown size={14}/>
            </button>

            <Drawer
                opened={sheetOpen}
                onClose={() => setSheetOpen(false)}
                position="bottom"
                size="auto"
                padding={0}
                withCloseButton={false}
                // Bump above BottomNav (z-index 40 inside a position:fixed root).
                zIndex={400}
                overlayProps={{backgroundOpacity: 0.45, blur: 2}}
                classNames={{content: classes.sheetContent, body: classes.sheetBody}}
            >
                <div className={classes.handle}/>
                <div className={classes.sheetHeader}>{t`Filter by date`}</div>

                <div className={classes.searchWrap}>
                    <TextInput
                        value={search}
                        onChange={(e) => setSearch(e.currentTarget.value)}
                        placeholder={t`Search dates…`}
                        leftSection={<IconSearch size={14}/>}
                        size="sm"
                        autoFocus
                    />
                </div>

                <div className={classes.options}>
                    <button
                        type="button"
                        className={`${classes.option} ${activeOccurrenceId === null ? classes.optionSelected : ''}`}
                        onClick={() => handleSelect(null)}
                    >
                        <span className={classes.optionMain}>{t`All dates`}</span>
                        {activeOccurrenceId === null && <IconCheck size={16}/>}
                    </button>

                    {grouped.map(group => (
                        <div key={group.key} className={classes.group}>
                            <div className={classes.groupLabel}>{group.label}</div>
                            {group.items.map(occ => {
                                const selected = activeOccurrenceId === occ.id;
                                const todayOcc = isToday(occ, tz);
                                return (
                                    <button
                                        key={occ.id}
                                        type="button"
                                        className={`${classes.option} ${selected ? classes.optionSelected : ''} ${occ.is_past ? classes.optionPast : ''}`}
                                        onClick={() => handleSelect(occ.id as number)}
                                    >
                                        <span className={`${classes.optionMain} ${todayOcc ? classes.optionToday : ''}`}>
                                            {todayOcc && <span className={classes.todayBadge}>{t`Today`}</span>}
                                            {formatOccurrenceLabel(occ, tz)}
                                        </span>
                                        {selected && <IconCheck size={16}/>}
                                    </button>
                                );
                            })}
                        </div>
                    ))}

                    {totalFiltered === 0 && (
                        <div className={classes.empty}>{t`No dates match your search`}</div>
                    )}

                    {truncated && (
                        <div className={classes.truncationNote}>
                            {t`Showing ${MAX_VISIBLE} of ${totalAvailable} dates. Type to search.`}
                        </div>
                    )}
                </div>
            </Drawer>
        </>
    );
};
