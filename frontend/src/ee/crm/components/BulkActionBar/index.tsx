import {plural, t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Progress, Tooltip} from "@mantine/core";
import {IconDotsVertical, IconDownload, IconMailOff, IconTag, IconTagOff, IconTrash, IconX} from "@tabler/icons-react";
import {IdParam} from "../../../../types.ts";
import {CrmTag} from "../../types.ts";
import {TagPicker} from "./TagPicker.tsx";
import classes from "./BulkActionBar.module.scss";

export interface BulkProgress {
    label: string;
    percent: number;
}

export const BulkActionBar = ({
    organizerId,
    count,
    progress,
    isBusy,
    tagDisabledReason,
    removeDisabledReason,
    onAddTag,
    onRemoveTag,
    onExport,
    onUnsubscribe,
    onDelete,
    onClear,
}: {
    organizerId: IdParam,
    count: number,
    progress: BulkProgress | null,
    isBusy: boolean,
    tagDisabledReason: string | null,
    removeDisabledReason: string | null,
    onAddTag?: (tag: CrmTag) => void,
    onRemoveTag?: (tag: CrmTag) => void,
    onExport: () => void,
    onUnsubscribe?: () => void,
    onDelete: () => void,
    onClear: () => void,
}) => {
    if (progress) {
        return (
            <div className={classes.dock}>
                <div className={classes.bar} role="status">
                    <span className={classes.progressLabel}>{progress.label}</span>
                    <Progress className={classes.progress} value={Math.max(progress.percent, 3)} size="sm" animated aria-label={progress.label}/>
                    <span className={classes.percent}>{Math.round(progress.percent)}%</span>
                </div>
            </div>
        );
    }

    return (
        <div className={classes.dock}>
            <div className={classes.bar} role="toolbar" aria-label={t`Selected contacts`} data-testid="audience-bulk-bar">
                <span className={classes.count}>{plural(count, {one: '# selected', other: '# selected'})}</span>
                <span className={classes.divider}/>
                <div className={classes.actions}>
                    {onAddTag && (
                        <TagPicker
                            organizerId={organizerId}
                            label={t`Add tag`}
                            icon={<IconTag size={15}/>}
                            disabledReason={isBusy ? t`Working…` : tagDisabledReason}
                            allowCreate
                            onPick={onAddTag}
                        />
                    )}
                    {onRemoveTag && (
                        <TagPicker
                            organizerId={organizerId}
                            label={t`Remove tag`}
                            icon={<IconTagOff size={15}/>}
                            disabledReason={isBusy ? t`Working…` : tagDisabledReason}
                            allowCreate={false}
                            onPick={onRemoveTag}
                        />
                    )}
                    <Button variant="subtle" color="gray" size="compact-sm" leftSection={<IconDownload size={15}/>} onClick={onExport} disabled={isBusy}>
                        {t`Export`}
                    </Button>
                    <Menu position="top-end" shadow="md" withinPortal>
                        <Menu.Target>
                            <Tooltip label={removeDisabledReason} disabled={removeDisabledReason === null}>
                                <ActionIcon variant="subtle" color="gray" aria-label={t`More actions`} disabled={isBusy || removeDisabledReason !== null} data-testid="audience-bulk-more">
                                    <IconDotsVertical size={16}/>
                                </ActionIcon>
                            </Tooltip>
                        </Menu.Target>
                        <Menu.Dropdown>
                            {onUnsubscribe && (
                                <Menu.Item leftSection={<IconMailOff size={15}/>} onClick={onUnsubscribe} data-testid="audience-bulk-unsubscribe">
                                    {t`Unsubscribe from emails`}
                                </Menu.Item>
                            )}
                            <Menu.Item color="red" leftSection={<IconTrash size={15}/>} onClick={onDelete} data-testid="audience-bulk-delete">
                                {t`Delete`}
                            </Menu.Item>
                        </Menu.Dropdown>
                    </Menu>
                </div>
                <span className={classes.divider}/>
                <ActionIcon variant="subtle" color="gray" aria-label={t`Clear selection`} onClick={onClear}>
                    <IconX size={16}/>
                </ActionIcon>
            </div>
        </div>
    );
};
