import {t} from "@lingui/macro";
import {useNavigate} from "react-router";
import {Stack} from "@mantine/core";
import {IconArrowRight, IconChevronRight, IconList, IconPlus, IconQrcode} from "@tabler/icons-react";
import {Modal} from "../../common/Modal";
import {CheckInList, GenericModalProps, IdParam} from "../../../types.ts";
import {openCheckInTab} from "../../routes/event/OccurrencesTab/checkInLaunch.tsx";
import classes from "./SelectCheckInListModal.module.scss";

interface SelectCheckInListModalProps extends GenericModalProps {
    eventId: IdParam;
    occurrenceId: number;
    lists: CheckInList[];
    onCreateForOccurrence: (occurrenceId: number) => void;
}

export const SelectCheckInListModal = ({
                                           onClose,
                                           eventId,
                                           occurrenceId,
                                           lists,
                                           onCreateForOccurrence,
                                       }: SelectCheckInListModalProps) => {
    const navigate = useNavigate();

    const defaultList = lists.find(list => list.is_system_default) ?? lists.find(list => !list.event_occurrence_id);
    const scopedLists = lists.filter(list => list.event_occurrence_id === occurrenceId);
    const orderedLists = [defaultList, ...scopedLists]
        .filter((list): list is CheckInList => Boolean(list))
        .filter((list, index, all) => all.findIndex(other => other.short_id === list.short_id) === index);

    const startCheckIn = (list: CheckInList) => {
        openCheckInTab(list.short_id, list.event_occurrence_id ? undefined : occurrenceId);
        onClose();
    };

    const handleCreate = () => {
        onCreateForOccurrence(occurrenceId);
        onClose();
    };

    const handleViewAll = () => {
        onClose();
        navigate(`/manage/event/${eventId}/check-in`);
    };

    return (
        <Modal opened onClose={onClose} heading={t`Start check-in`} size="sm">
            <Stack gap={4}>
                <Stack gap={8}>
                    {orderedLists.map((list, index) => {
                        const isPrimary = index === 0;
                        return (
                            <button
                                key={list.short_id}
                                type="button"
                                className={isPrimary ? classes.primaryAction : classes.listCard}
                                onClick={() => startCheckIn(list)}
                            >
                                <span className={isPrimary ? classes.primaryIcon : classes.listCardIcon}>
                                    <IconQrcode size={22}/>
                                </span>
                                <span className={classes.primaryText}>
                                    <span className={classes.primaryName}>{list.name}</span>
                                    <span className={classes.primaryMeta}>
                                        {list.event_occurrence_id ? t`This date only` : t`Recommended`}
                                    </span>
                                </span>
                                <IconArrowRight
                                    size={18}
                                    className={isPrimary ? classes.primaryArrow : classes.listCardArrow}
                                />
                            </button>
                        );
                    })}
                </Stack>

                <div className={classes.divider}/>

                <button type="button" className={classes.actionRow} onClick={handleCreate}>
                    <IconPlus size={18} className={classes.actionRowIcon}/>
                    <span className={classes.actionRowLabel}>{t`Create a list for this date`}</span>
                </button>

                <button type="button" className={classes.actionRow} onClick={handleViewAll}>
                    <IconList size={18} className={classes.actionRowIcon}/>
                    <span className={classes.actionRowLabel}>{t`View all check-in lists`}</span>
                    <IconChevronRight size={16} className={classes.actionRowChevron}/>
                </button>
            </Stack>
        </Modal>
    );
};
